<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../templates/header.php';

$documentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($documentId <= 0) {
    echo '<div class="alert alert-warning">Documento no encontrado.</div>';
    require_once __DIR__ . '/../templates/footer.php';
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = :id');
    $stmt->execute([':id' => $documentId]);
    $document = $stmt->fetch();

    if (!$document) {
        echo '<div class="alert alert-warning">Documento no encontrado.</div>';
        require_once __DIR__ . '/../templates/footer.php';
        exit;
    }

    $itemsStmt = $pdo->prepare('SELECT * FROM document_items WHERE document_id = :id ORDER BY id ASC');
    $itemsStmt->execute([':id' => $documentId]);
    $items = $itemsStmt->fetchAll();

    $paymentsTotal = 0.0;
    try {
        $payStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total_pagado FROM payments WHERE document_id = :document_id');
        $payStmt->execute([':document_id' => $documentId]);
        $paymentsTotal = (float) ($payStmt->fetchColumn() ?? 0);
    } catch (PDOException $e) {
        $paymentsTotal = 0.0;
    }

    $statusLabels = [
        'draft' => 'Borrador',
        'sent' => 'Enviado',
        'paid' => 'Pagado',
        'cancelled' => 'Cancelado',
    ];

    $settingsStmt = $pdo->query('SELECT * FROM settings LIMIT 1');
    $settings = $settingsStmt->fetch();

    $businessName = $settings['business_name'] ?? 'Alquileres Primavera';
    $businessAddress = $settings['business_address'] ?? '';
    $businessPhone = $settings['business_phone'] ?? '';
    $businessWhatsapp = $settings['business_whatsapp'] ?? '';
    $businessLogo = $settings['business_logo'] ?? '';
    $settingsIvaPercent = isset($settings['iva_percentage']) ? (float) $settings['iva_percentage'] : 13.0;

    $docLabel = $document['doc_type'] === 'invoice' ? 'FACTURA' : 'ESTIMADO';
    $statusClass = $document['doc_type'] === 'invoice' ? 'text-danger' : 'text-primary';
    $documentDate = $document['document_date'] ?? '';
    $rentalEndDate = $document['rental_end_date'] ?? '';
    $isEstimate = ($document['doc_type'] === 'estimate');
    $isInvoice = ($document['doc_type'] === 'invoice');

    if (empty($document['public_token'])) {
        $generateToken = static function (int $length = 40): string {
            $length = max(2, $length);
            $bytes = (int) ceil($length / 2);
            return substr(bin2hex(random_bytes($bytes)), 0, $length);
        };

        $newToken = $generateToken(40);
        $updateTokenStmt = $pdo->prepare('UPDATE documents SET public_token = :token WHERE id = :id');
        $updateTokenStmt->execute([
            ':token' => $newToken,
            ':id' => $documentId,
        ]);
        $document['public_token'] = $newToken;
    }

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
?>
<div class="invoice-page">
    <div class="row mb-3 align-items-center">
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
                <table class="table table-striped table-bordered mb-0">
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

    <div class="row mb-3 gy-2 justify-content-end">
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
        <?php if ($isEstimate): ?>
            <a href="convert_to_invoice.php?id=<?php echo $documentId; ?>" class="btn btn-warning">Convertir a factura</a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCopyPublicLink">Copiar enlace para cliente</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCopyInternalLink">Copiar enlace interno</button>
        <a href="/documents/index.php" class="btn btn-secondary">Volver a la lista</a>
    </div>
</div>

<style>
@media print {
    body {
        margin: 0;
        padding: 0;
    }
    .navbar, .btn, .no-print {
        display: none !important;
    }
    .container, .invoice-page {
        width: 100%;
        max-width: 100%;
        padding: 8px;
        margin: 0;
    }
    table {
        font-size: 12px;
    }
    h1, h2, h3, h4 {
        margin: 4px 0;
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
    echo '<div class="alert alert-danger">Ocurrió un error al cargar el documento.</div>';
}

require_once __DIR__ . '/../templates/footer.php';
