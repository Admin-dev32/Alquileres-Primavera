<?php
require_once __DIR__ . '/../config/config.php';

$documents = [];
$errorMessage = '';
$today = date('Y-m-d');

try {
    $docsStmt = $pdo->query('SELECT id, doc_code, doc_type, client_name, total FROM documents ORDER BY created_at DESC LIMIT 100');
    $documents = $docsStmt->fetchAll();
} catch (PDOException $e) {
    $errorMessage = 'Ocurrió un error al cargar los documentos.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documentId = isset($_POST['document_id']) && $_POST['document_id'] !== '' ? (int) $_POST['document_id'] : null;
    $paymentDate = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : '';
    $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
    $method = isset($_POST['method']) ? trim($_POST['method']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($paymentDate === '' || $amount <= 0) {
        $errorMessage = 'Por favor completa todos los campos obligatorios.';
    } else {
        try {
            $insert = $pdo->prepare('INSERT INTO payments (document_id, payment_date, amount, method, notes) VALUES (:document_id, :payment_date, :amount, :method, :notes)');
            $insert->execute([
                ':document_id' => $documentId,
                ':payment_date' => $paymentDate,
                ':amount' => $amount,
                ':method' => $method,
                ':notes' => $notes,
            ]);

            if ($documentId !== null) {
                // Recalcular pagos y actualizar estado si está totalmente pagado
                $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total_pagado FROM payments WHERE document_id = :document_id');
                $sumStmt->execute([':document_id' => $documentId]);
                $totalPagado = (float) ($sumStmt->fetchColumn() ?? 0);

                $totalDocStmt = $pdo->prepare('SELECT total FROM documents WHERE id = :document_id');
                $totalDocStmt->execute([':document_id' => $documentId]);
                $docTotal = (float) ($totalDocStmt->fetchColumn() ?? 0);

                if ($docTotal > 0 && $totalPagado >= $docTotal) {
                    $updateStatus = $pdo->prepare('UPDATE documents SET status = :status WHERE id = :document_id');
                    $updateStatus->execute([
                        ':status' => 'paid',
                        ':document_id' => $documentId,
                    ]);
                }
            }

            header('Location: /finance/index.php');
            exit;
        } catch (PDOException $e) {
            $errorMessage = 'Ocurrió un error al guardar el pago.';
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0">Registrar pago</h1>
        <p class="text-muted mb-0">Ingresa un nuevo ingreso y asígnalo a un documento si es necesario.</p>
    </div>
    <a href="/finance/index.php" class="btn btn-secondary">Volver</a>
</div>

<?php if ($errorMessage): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="">
            <div class="mb-3">
                <label for="document_id" class="form-label fw-semibold">Documento (opcional)</label>
                <select name="document_id" id="document_id" class="form-select">
                    <option value="">Sin documento</option>
                    <?php foreach ($documents as $doc): ?>
                        <?php
                            $label = ($doc['doc_code'] ?? 'Sin código');
                            if (!empty($doc['client_name'])) {
                                $label .= ' - ' . $doc['client_name'];
                            }
                            $label .= ' - $' . number_format((float) $doc['total'], 2);
                        ?>
                        <option value="<?php echo htmlspecialchars($doc['id']); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="payment_date" class="form-label fw-semibold">Fecha del pago</label>
                    <input type="date" class="form-control" id="payment_date" name="payment_date" required value="<?php echo htmlspecialchars($today); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label for="amount" class="form-label fw-semibold">Monto</label>
                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                </div>
            </div>

            <div class="mt-3">
                <label for="method" class="form-label fw-semibold">Método de pago</label>
                <input type="text" class="form-control" id="method" name="method" placeholder="Efectivo, Transferencia, etc.">
            </div>

            <div class="mt-3">
                <label for="notes" class="form-label fw-semibold">Notas</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Guardar pago</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='/finance/index.php'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php';
