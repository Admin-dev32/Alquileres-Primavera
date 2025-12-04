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

    $docLabel = $document['doc_type'] === 'invoice' ? 'FACTURA' : 'ESTIMADO';
    $statusClass = $document['doc_type'] === 'invoice' ? 'text-danger' : 'text-primary';
    $documentDate = $document['document_date'] ?? '';
    $rentalEndDate = $document['rental_end_date'] ?? '';
    $saldoPendiente = max(0, (float) $document['total'] - $paymentsTotal);
?>
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <?php if (!empty($businessLogo)): ?>
            <img src="<?php echo htmlspecialchars($businessLogo); ?>" alt="Logo" style="max-height: 80px;" class="mb-2">
        <?php else: ?>
            <h2 class="mb-1"><?php echo htmlspecialchars($businessName); ?></h2>
        <?php endif; ?>
        <div class="text-muted">
            <div><?php echo htmlspecialchars($businessAddress); ?></div>
            <div>Teléfono: <?php echo htmlspecialchars($businessPhone); ?></div>
            <?php if ($businessWhatsapp !== ''): ?>
                <div>WhatsApp: <?php echo htmlspecialchars($businessWhatsapp); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <h3 class="fw-bold <?php echo $statusClass; ?> mb-2"><?php echo $docLabel; ?></h3>
        <div class="fs-5">Número: <strong><?php echo htmlspecialchars($document['doc_code']); ?></strong></div>
        <div class="fs-6">Fecha: <?php echo htmlspecialchars($documentDate); ?></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header fw-bold">Datos del cliente</div>
    <div class="card-body">
        <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($document['client_name']); ?></p>
        <?php if (!empty($document['client_company'])): ?>
            <p class="mb-1"><strong>Empresa:</strong> <?php echo htmlspecialchars($document['client_company']); ?></p>
        <?php endif; ?>
        <p class="mb-1"><strong>Dirección:</strong> <?php echo htmlspecialchars($document['client_address']); ?></p>
        <p class="mb-0"><strong>Teléfono:</strong> <?php echo htmlspecialchars($document['client_phone']); ?></p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header fw-bold">Datos del evento</div>
    <div class="card-body">
        <p class="mb-1"><strong>Representante:</strong> <?php echo htmlspecialchars($document['representative']); ?></p>
        <p class="mb-1"><strong>Tipo de evento:</strong> <?php echo htmlspecialchars($document['event_type']); ?></p>
        <?php if (!empty($rentalEndDate)): ?>
            <p class="mb-0"><strong>Fin del alquiler:</strong> <?php echo htmlspecialchars($rentalEndDate); ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header fw-bold">Artículos del alquiler</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Descripción</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Precio unitario</th>
                        <th class="text-end">Total línea</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) === 0): ?>
                        <tr>
                            <td colspan="4" class="text-center">No hay artículos registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td class="text-end"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="text-end">$<?php echo number_format((float) $item['unit_price'], 2); ?></td>
                                <td class="text-end">$<?php echo number_format((float) $item['line_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row mb-4 justify-content-end">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-bold">Subtotal:</span>
                    <span>$<?php echo number_format((float) $document['subtotal'], 2); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-bold">IVA:</span>
                    <span>$<?php echo number_format((float) $document['tax'], 2); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Total:</span>
                    <span class="fw-bold">$<?php echo number_format((float) $document['total'], 2); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 justify-content-end">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-bold">Estado:</span>
                    <span><?php echo htmlspecialchars($statusLabels[$document['status']] ?? $document['status']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
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
<div class="card mb-4">
    <div class="card-header fw-bold">Notas</div>
    <div class="card-body">
        <p class="mb-0"><?php echo nl2br(htmlspecialchars($document['notes'])); ?></p>
    </div>
</div>
<?php endif; ?>

<div class="d-flex gap-3 mb-4">
    <button type="button" class="btn btn-primary" onclick="window.print();">Imprimir</button>
    <a href="/documents/index.php" class="btn btn-secondary">Volver a la lista</a>
</div>

<?php
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Ocurrió un error al cargar el documento.</div>';
}

require_once __DIR__ . '/../templates/footer.php';
