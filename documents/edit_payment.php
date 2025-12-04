<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();
require_permission('manage_payments');

$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$documentId = filter_input(INPUT_GET, 'document_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);

if (!$paymentId || !$documentId) {
    header('Location: /documents/index.php');
    exit;
}

$alertError = '';
$payment = null;

try {
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = :id AND document_id = :document_id LIMIT 1');
    $stmt->execute([
        ':id' => $paymentId,
        ':document_id' => $documentId,
    ]);
    $payment = $stmt->fetch();

    if (!$payment) {
        require_once __DIR__ . '/../templates/header.php';
        echo '<div class="alert alert-warning">Pago no encontrado.</div>';
        echo '<a class="btn btn-secondary" href="/documents/view.php?id=' . (int) $documentId . '">Volver al documento</a>';
        require_once __DIR__ . '/../templates/footer.php';
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $paymentDate = trim($_POST['payment_date'] ?? '');
        $amount = is_numeric($_POST['amount'] ?? null) ? (float) $_POST['amount'] : 0;
        $method = trim($_POST['method'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($paymentDate === '' || $amount <= 0) {
            $alertError = 'Por favor completa los campos obligatorios.';
        } else {
            $docStmt = $pdo->prepare('SELECT id, total FROM documents WHERE id = :id AND is_deleted = 0 LIMIT 1');
            $docStmt->execute([':id' => $documentId]);
            $document = $docStmt->fetch();

            if (!$document) {
                header('Location: /documents/view.php?id=' . $documentId . '&msg=error');
                exit;
            }

            $otrosPagosStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE document_id = :doc_id AND id <> :payment_id');
            $otrosPagosStmt->execute([
                ':doc_id' => $documentId,
                ':payment_id' => $paymentId,
            ]);
            $pagadoSinActual = (float) $otrosPagosStmt->fetchColumn();
            $saldoPendiente = (float) $document['total'] - $pagadoSinActual;

            if ($amount > $saldoPendiente) {
                $amount = $saldoPendiente;
            }

            if ($amount <= 0) {
                $alertError = 'El monto debe ser mayor a 0.';
            } else {
                $update = $pdo->prepare('UPDATE payments SET payment_date = :payment_date, amount = :amount, method = :method, notes = :notes WHERE id = :id AND document_id = :document_id');
                $update->execute([
                    ':payment_date' => $paymentDate,
                    ':amount' => $amount,
                    ':method' => $method !== '' ? $method : 'Otro',
                    ':notes' => $notes !== '' ? $notes : null,
                    ':id' => $paymentId,
                    ':document_id' => $documentId,
                ]);

                $totalPagadoStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE document_id = :doc_id');
                $totalPagadoStmt->execute([':doc_id' => $documentId]);
                $pagadoTotal = (float) $totalPagadoStmt->fetchColumn();

                $status = 'draft';
                if ($pagadoTotal >= (float) $document['total']) {
                    $status = 'paid';
                } elseif ($pagadoTotal > 0) {
                    $status = 'sent';
                }

                $statusStmt = $pdo->prepare('UPDATE documents SET status = :status WHERE id = :id');
                $statusStmt->execute([
                    ':status' => $status,
                    ':id' => $documentId,
                ]);

                header('Location: /documents/view.php?id=' . $documentId);
                exit;
            }
        }
    }
} catch (PDOException $e) {
    error_log('Error al editar pago: ' . $e->getMessage());
    $alertError = 'Ocurrió un error al cargar el pago.';
}

$payment = $payment ?: [
    'payment_date' => date('Y-m-d'),
    'amount' => '',
    'method' => 'Efectivo',
    'notes' => '',
];

require_once __DIR__ . '/../templates/header.php';
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h1 class="h4 mb-3">Editar pago</h1>
        <?php if ($alertError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($alertError); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="id" value="<?php echo (int) $paymentId; ?>">
            <input type="hidden" name="document_id" value="<?php echo (int) $documentId; ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha de pago</label>
                    <input type="date" name="payment_date" class="form-control" value="<?php echo htmlspecialchars($payment['payment_date']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monto</label>
                    <input type="number" name="amount" step="0.01" min="0.01" class="form-control" value="<?php echo htmlspecialchars($payment['amount']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Método</label>
                    <select name="method" class="form-select" required>
                        <?php
                        $metodos = ['Efectivo', 'Transferencia bancaria', 'Tarjeta', 'Otro'];
                        foreach ($metodos as $metodo):
                            $sel = ($payment['method'] ?? '') === $metodo ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($metodo) . '" ' . $sel . '>' . htmlspecialchars($metodo) . '</option>';
                        endforeach;
                        ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" rows="3" class="form-control"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
                <a href="/documents/view.php?id=<?php echo (int) $documentId; ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php';
