<?php
require_once __DIR__ . '/../config/config.php';

$token = $_GET['t'] ?? '';
if (!$token) {
    http_response_code(404);
    echo 'Documento no encontrado.';
    exit;
}

try {
    $docStmt = $pdo->prepare('SELECT * FROM documents WHERE public_token = :token LIMIT 1');
    $docStmt->execute([':token' => $token]);
    $document = $docStmt->fetch();

    if (!$document) {
        http_response_code(404);
        echo 'Documento no encontrado.';
        exit;
    }

    $itemsStmt = $pdo->prepare('SELECT * FROM document_items WHERE document_id = :id ORDER BY id ASC');
    $itemsStmt->execute([':id' => $document['id']]);
    $items = $itemsStmt->fetchAll();

    $settingsStmt = $pdo->query('SELECT * FROM settings LIMIT 1');
    $settings = $settingsStmt->fetch();
    $businessName = $settings['business_name'] ?? 'Alquileres Primavera';
    $businessAddress = $settings['business_address'] ?? '';
    $businessPhone = $settings['business_phone'] ?? '';
    $businessWhatsapp = $settings['business_whatsapp'] ?? '';
    $businessLogo = $settings['logo_path'] ?? ($settings['business_logo'] ?? '');

    $docLabel = $document['doc_type'] === 'invoice' ? 'Factura' : 'Estimado';
    $documentDate = $document['document_date'] ?? '';
    $rentalEndDate = $document['rental_end_date'] ?? '';

    $calculatedSubtotal = 0.0;
    foreach ($items as $calcItem) {
        $qty = isset($calcItem['quantity']) ? (float) $calcItem['quantity'] : 0;
        $price = isset($calcItem['unit_price']) ? (float) $calcItem['unit_price'] : 0;
        $days = isset($calcItem['rental_days']) ? (int) $calcItem['rental_days'] : 1;
        if ($days < 1) {
            $days = 1;
        }
        $calculatedSubtotal += $qty * $price * $days;
    }

    $calculatedSubtotal = round($calculatedSubtotal, 2);
    $subtotalDisplay = $calculatedSubtotal > 0 ? $calculatedSubtotal : (float) ($document['subtotal'] ?? 0);
    $totalDisplay = $subtotalDisplay;
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Ocurrió un error al cargar el documento.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($docLabel . ' ' . ($document['doc_code'] ?? '')); ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
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
            .d-print-none,
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
</head>
<body>
<div class="container my-4 document-wrapper">
    <div class="row align-items-center mb-3 document-header">
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
            <h3 class="fw-bold mb-1"><?php echo htmlspecialchars(strtoupper($docLabel)); ?></h3>
            <div>Número: <strong><?php echo htmlspecialchars($document['doc_code']); ?></strong></div>
            <div>Fecha: <?php echo htmlspecialchars($documentDate); ?></div>
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
                                    $days = isset($item['rental_days']) ? (int) $item['rental_days'] : 1;
                                    if ($days < 1) {
                                        $days = 1;
                                    }
                                    $lineTotal = (float) $item['unit_price'] * (float) $item['quantity'] * $days;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($days); ?></td>
                                    <td class="text-end">$<?php echo number_format((float) $item['unit_price'], 2); ?></td>
                                    <td class="text-end">$<?php echo number_format($lineTotal, 2); ?></td>
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
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">Total:</span>
                        <span class="fw-bold">$<?php echo number_format($totalDisplay, 2); ?></span>
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

    <div class="d-flex gap-2 d-print-none">
        <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
    </div>
</div>
</body>
</html>
