<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();
require_permission('view_documents');

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$documentId) {
    header('Location: /documents/index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = :id AND is_deleted = 0 LIMIT 1');
    $stmt->execute([':id' => $documentId]);
    $document = $stmt->fetch();

    if (!$document) {
        require_once __DIR__ . '/../templates/header.php';
        echo '<div class="alert alert-warning">Documento no encontrado.</div>';
        echo '<a class="btn btn-secondary" href="/documents/index.php">Volver a la lista</a>';
        require_once __DIR__ . '/../templates/footer.php';
        exit;
    }

    $generateToken = static function (int $length = 40): string {
        $length = max(2, $length);
        $bytes = (int) ceil($length / 2);
        return substr(bin2hex(random_bytes($bytes)), 0, $length);
    };

    if (empty($document['public_token'])) {
        $newToken = $generateToken(40);
        $updateTokenStmt = $pdo->prepare('UPDATE documents SET public_token = :token WHERE id = :id');
        $updateTokenStmt->execute([
            ':token' => $newToken,
            ':id' => $documentId,
        ]);
        $document['public_token'] = $newToken;
    }

    $itemsStmt = $pdo->prepare('SELECT * FROM document_items WHERE document_id = :id ORDER BY id ASC');
    $itemsStmt->execute([':id' => $documentId]);
    $items = $itemsStmt->fetchAll();

    $payments = [];
    $paymentsTotal = 0.0;
    try {
        $payStmt = $pdo->prepare('SELECT * FROM payments WHERE document_id = :document_id ORDER BY payment_date ASC, id ASC');
        $payStmt->execute([':document_id' => $documentId]);
        $payments = $payStmt->fetchAll();
        $paymentsTotal = array_reduce($payments, static function ($carry, $row) {
            return $carry + (float) ($row['amount'] ?? 0);
        }, 0.0);
    } catch (PDOException $e) {
        error_log('Error al obtener pagos: ' . $e->getMessage());
    }

    $statusLabels = [
        'draft' => 'Borrador',
        'sent' => 'Enviado',
        'paid' => 'Pagado',
        'cancelled' => 'Cancelado',
        'partial' => 'Parcial',
        'pending' => 'Pendiente',
    ];

    $settingsStmt = $pdo->query('SELECT * FROM settings LIMIT 1');
    $settings = $settingsStmt->fetch();

    $businessName = $settings['business_name'] ?? 'Alquileres Primavera';
    $businessAddress = $settings['business_address'] ?? '';
    $businessPhone = $settings['business_phone'] ?? '';
    $businessWhatsapp = $settings['business_whatsapp'] ?? '';
    $businessLogo = $settings['logo_path'] ?? ($settings['business_logo'] ?? '');
    $settingsIvaPercent = isset($settings['iva_percentage']) ? (float) $settings['iva_percentage'] : 13.0;

    $docLabel = $document['doc_type'] === 'invoice' ? 'FACTURA' : 'ESTIMADO';
    $statusClass = $document['doc_type'] === 'invoice' ? 'text-danger' : 'text-primary';
    $documentDate = $document['document_date'] ?? '';
    $rentalEndDate = $document['rental_end_date'] ?? '';
    $isEstimate = ($document['doc_type'] === 'estimate');
    $isInvoice = ($document['doc_type'] === 'invoice');

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $publicUrl = $scheme . '://' . $host . '/public/document.php?t=' . urlencode($document['public_token']);

    $calculatedSubtotal = 0.0;
    foreach ($items as $calcItem) {
        $calcQty = isset($calcItem['quantity']) ? (float) $calcItem['quantity'] : 0;
        $calcPrice = isset($calcItem['unit_price']) ? (float) $calcItem['unit_price'] : 0;
        $calcDays = isset($calcItem['rental_days']) ? (int) $calcItem['rental_days'] : 1;
        if ($calcDays < 1) {
            $calcDays = 1;
        }
        $calculatedSubtotal += $calcQty * $calcPrice * $calcDays;
    }
    $calculatedSubtotal = round($calculatedSubtotal, 2);

    $ivaPercent = ($document['subtotal'] ?? 0) > 0
        ? round(((float) $document['tax'] / max((float) $document['subtotal'], 0.01)) * 100, 2)
        : $settingsIvaPercent;
    $subtotalDisplay = $calculatedSubtotal > 0 ? $calculatedSubtotal : (float) ($document['subtotal'] ?? 0);
    $taxDisplay = ($document['tax'] ?? null) !== null ? (float) $document['tax'] : round($subtotalDisplay * ($ivaPercent / 100), 2);
    $totalDisplay = ($document['total'] ?? null) !== null ? (float) $document['total'] : round($subtotalDisplay + $taxDisplay, 2);
    $saldoPendiente = max(0, $totalDisplay - $paymentsTotal);
    $flashMsg = isset($_GET['msg']) ? trim($_GET['msg']) : '';

    require_once __DIR__ . '/../templates/header.php';
?>
<div class="invoice-page document-wrapper">
    <?php if ($flashMsg === 'error'): ?>
        <div class="alert alert-danger">Ocurrió un error al procesar la solicitud.</div>
    <?php endif; ?>
    <div class="row mb-3 align-items-center document-header">
        <div class="col-md-6">
            <?php if (!empty($businessLogo)): ?>
                <img src="<?php echo htmlspecialchars($businessLogo); ?>" alt="Logo" style="max-height: 70px;" class="mb-2">
            <?php else: ?>
                <h2 class="mb-1"><?php echo htmlspecialchars($businessName); ?></h2>
            <?php endif; ?>
            <div class="text-muted small">
                <div><?php echo htmlspecialchars($businessAddress); ?></div>
                <div>Teléfono: <?php echo htmlspecialchars($businessPhone); ?></div>
                <?php if ($businessWhatsapp !== ''): ?>
                    <div>WhatsApp: <?php echo htmlspecialchars($businessWhatsapp); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <h3 class="fw-bold <?php echo $statusClass; ?> mb-1"><?php echo $docLabel; ?></h3>
            <div class="fs-6">Número: <strong><?php echo htmlspecialchars($document['doc_code']); ?></strong></div>
            <div class="fs-6">Fecha: <?php echo htmlspecialchars($documentDate); ?></div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header fw-bold py-2">Datos del cliente</div>
        <div class="card-body py-2">
            <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($document['client_name']); ?></p>
            <?php if (!empty($document['client_company'])): ?>
                <p class="mb-1"><strong>Empresa:</strong> <?php echo htmlspecialchars($document['client_company']); ?></p>
            <?php endif; ?>
            <p class="mb-1"><strong>Dirección:</strong> <?php echo htmlspecialchars($document['client_address']); ?></p>
            <p class="mb-0"><strong>Teléfono:</strong> <?php echo htmlspecialchars($document['client_phone']); ?></p>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header fw-bold py-2">Datos del evento</div>
        <div class="card-body py-2">
            <p class="mb-1"><strong>Representante:</strong> <?php echo htmlspecialchars($document['representative']); ?></p>
            <p class="mb-1"><strong>Tipo de evento:</strong> <?php echo htmlspecialchars($document['event_type']); ?></p>
            <?php if (!empty($rentalEndDate)): ?>
                <p class="mb-0"><strong>Fin del alquiler:</strong> <?php echo htmlspecialchars($rentalEndDate); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header fw-bold py-2">Artículos del alquiler</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-bordered mb-0 table-document-items">
                    <thead class="table-light">
                        <tr>
                            <th>Descripción</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">Días</th>
                            <th class="text-end">Precio unitario</th>
                            <th class="text-end">Total línea</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items) === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay artículos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <?php
                                    $itemDays = isset($item['rental_days']) ? (int) $item['rental_days'] : 1;
                                    if ($itemDays < 1) {
                                        $itemDays = 1;
                                    }
                                    $lineTotalCalc = (float) $item['unit_price'] * (float) $item['quantity'] * $itemDays;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($itemDays); ?></td>
                                    <td class="text-end">$<?php echo number_format((float) $item['unit_price'], 2); ?></td>
                                    <td class="text-end">$<?php echo number_format($lineTotalCalc, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mb-3 gy-2 justify-content-end document-totals">
        <div class="col-md-6 col-lg-4">
            <div class="card">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold">Subtotal:</span>
                        <span>$<?php echo number_format($subtotalDisplay, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold">IVA (<?php echo number_format($ivaPercent, 2); ?>%):</span>
                        <span>$<?php echo number_format($taxDisplay, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">Total:</span>
                        <span class="fw-bold">$<?php echo number_format($totalDisplay, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold">Estado:</span>
                        <span><?php echo htmlspecialchars($statusLabels[$document['status']] ?? $document['status']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold">Total pagado:</span>
                        <span>$<?php echo number_format($paymentsTotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">Saldo pendiente:</span>
                        <span class="fw-bold">$<?php echo number_format($saldoPendiente, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-bold py-2">Pagos de este documento</div>
        <div class="card-body">
            <?php if (empty($payments)): ?>
                <p class="text-muted mb-2">Todavía no hay pagos registrados.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-2">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th class="text-end">Monto</th>
                                <th>Método</th>
                                <th>Notas</th>
                                <?php if (user_has_permission('manage_payments')): ?>
                                    <th>Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                    <td class="text-end fw-semibold">$<?php echo number_format((float) $payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['method']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($payment['notes'] ?? '')); ?></td>
                                    <?php if (user_has_permission('manage_payments')): ?>
                                        <td class="text-nowrap">
                                            <a href="/documents/edit_payment.php?id=<?php echo (int) $payment['id']; ?>&document_id=<?php echo (int) $document['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                            <a href="/documents/delete_payment.php?id=<?php echo (int) $payment['id']; ?>&document_id=<?php echo (int) $document['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Seguro que deseas eliminar este pago?');">Eliminar</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <div class="fw-bold">Total pagado: $<?php echo number_format($paymentsTotal, 2); ?></div>
                    <div class="fw-bold">Saldo pendiente: $<?php echo number_format($saldoPendiente, 2); ?></div>
                </div>
                <?php if ($saldoPendiente <= 0): ?>
                    <span class="badge bg-success">Pagado</span>
                <?php endif; ?>
            </div>

            <?php if (user_has_permission('manage_payments')): ?>
            <form class="row g-2" method="POST" action="/documents/save_payment.php">
                <input type="hidden" name="document_id" value="<?php echo (int) $document['id']; ?>">
                <div class="col-12 col-md-3">
                    <label class="form-label">Fecha de pago</label>
                    <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" <?php echo $saldoPendiente <= 0 ? 'readonly' : 'required'; ?>>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Monto</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" max="<?php echo number_format(max($saldoPendiente, 0), 2, '.', ''); ?>" <?php echo $saldoPendiente <= 0 ? 'readonly' : 'required'; ?>>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Método</label>
                    <select name="method" class="form-select" <?php echo $saldoPendiente <= 0 ? 'disabled' : 'required'; ?>>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia bancaria">Transferencia bancaria</option>
                        <option value="Tarjeta">Tarjeta</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" class="form-control" rows="1" <?php echo $saldoPendiente <= 0 ? 'readonly' : ''; ?>></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success btn-sm" <?php echo $saldoPendiente <= 0 ? 'disabled' : ''; ?>>Guardar pago</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($document['notes'])): ?>
    <div class="card mb-3">
        <div class="card-header fw-bold py-2">Notas</div>
        <div class="card-body py-2">
            <p class="mb-0"><?php echo nl2br(htmlspecialchars($document['notes'])); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mb-3 no-print">
        <button type="button" class="btn btn-primary" onclick="window.print();">Imprimir</button>
        <?php if ($isEstimate && user_has_permission('edit_documents')): ?>
            <a href="convert_to_invoice.php?id=<?php echo $documentId; ?>" class="btn btn-warning">Convertir a factura</a>
        <?php endif; ?>
        <?php if (user_has_permission('edit_documents')): ?>
            <a href="/documents/form.php?id=<?php echo (int) $document['id']; ?>" class="btn btn-outline-primary btn-sm">Editar</a>
        <?php endif; ?>
        <?php if (user_has_permission('delete_documents')): ?>
            <a href="/documents/delete.php?id=<?php echo (int) $document['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Seguro que deseas eliminar este documento?');">Eliminar</a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCopyPublicLink">Copiar enlace para cliente</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCopyInternalLink">Copiar enlace interno</button>
        <a href="/documents/index.php" class="btn btn-secondary">Volver a la lista</a>
    </div>
</div>

<style>
@page {
    size: A4;
    margin: 10mm;
}

.document-wrapper {
    max-width: 800px;
    margin: 0 auto;
    font-size: 0.9rem;
}

.document-header h1,
.document-header h2,
.document-header h3 {
    margin-bottom: 0.25rem;
}

.table-document-items th,
.table-document-items td {
    padding: 0.25rem 0.35rem;
    font-size: 0.85rem;
}

.document-header,
.document-totals {
    page-break-inside: avoid;
}

@media print {
    .no-print,
    .navbar,
    .btn,
    .alert,
    footer {
        display: none !important;
    }
    body {
        margin: 0;
        padding: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .document-wrapper {
        box-shadow: none !important;
        border: none !important;
        width: 100%;
        max-width: 100%;
        padding: 8px;
    }
}
</style>

<script>
const publicUrl = <?php echo json_encode($publicUrl); ?>;
const internalUrl = window.location.href;

const btnPublic = document.getElementById('btnCopyPublicLink');
if (btnPublic && navigator.clipboard) {
    btnPublic.addEventListener('click', () => {
        navigator.clipboard.writeText(publicUrl)
            .then(() => alert('Enlace para cliente copiado.'))
            .catch(() => alert('No se pudo copiar el enlace.'));
    });
}

const btnInternal = document.getElementById('btnCopyInternalLink');
if (btnInternal && navigator.clipboard) {
    btnInternal.addEventListener('click', () => {
        navigator.clipboard.writeText(internalUrl)
            .then(() => alert('Enlace interno copiado.'))
            .catch(() => alert('No se pudo copiar el enlace.'));
    });
}
</script>

<?php
} catch (PDOException $e) {
    error_log('Error al cargar documento: ' . $e->getMessage());
    require_once __DIR__ . '/../templates/header.php';
    echo '<div class="alert alert-danger">Ocurrió un error al cargar el documento.</div>';
    echo '<a class="btn btn-secondary" href="/documents/index.php">Volver a la lista</a>';
    require_once __DIR__ . '/../templates/footer.php';
    exit;
}

require_once __DIR__ . '/../templates/footer.php';
