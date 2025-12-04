<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

try {
    try {
        $pdo->exec('ALTER TABLE document_items ADD COLUMN IF NOT EXISTS rental_days INT NOT NULL DEFAULT 1');
    } catch (PDOException $e) {
        // ignore if column already exists or cannot be altered here
    }

    $docType = trim($_POST['doc_type'] ?? '');
    $validTypes = ['estimate', 'invoice'];
    if (!in_array($docType, $validTypes, true)) {
        throw new Exception('Tipo de documento inválido.');
    }

    $clientName = trim($_POST['client_name'] ?? '');
    if ($clientName === '') {
        throw new Exception('El nombre del cliente es obligatorio.');
    }

    $documentDate = trim($_POST['document_date'] ?? '');
    if ($documentDate === '') {
        throw new Exception('La fecha del documento es obligatoria.');
    }

    $clientCompany = trim($_POST['client_company'] ?? '');
    $clientAddress = trim($_POST['client_address'] ?? '');
    $clientPhone = trim($_POST['client_phone'] ?? '');
    $representative = trim($_POST['representative'] ?? '');
    $eventType = trim($_POST['event_type'] ?? '');
    $rentalEndDate = trim($_POST['rental_end_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $itemsPosted = $_POST['items'] ?? [];
    if (!is_array($itemsPosted)) {
        $itemsPosted = [];
    }

    $ivaPercent = 13.0;
    try {
        $ivaStmt = $pdo->query('SELECT iva_percentage FROM settings LIMIT 1');
        $ivaRow = $ivaStmt->fetch();
        if ($ivaRow && isset($ivaRow['iva_percentage'])) {
            $ivaPercent = (float) $ivaRow['iva_percentage'];
        }
    } catch (PDOException $e) {
        $ivaPercent = 13.0;
    }

    $validItems = [];
    $subtotal = 0.0;

    foreach ($itemsPosted as $item) {
        $itemName = trim($item['item_name'] ?? '');
        $quantity = isset($item['quantity']) ? (float) $item['quantity'] : 0;
        $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : 0;
        $rentalDays = isset($item['rental_days']) ? (int) $item['rental_days'] : 1;
        if ($rentalDays < 1) {
            $rentalDays = 1;
        }

        if ($itemName !== '' && $quantity > 0 && $unitPrice >= 0) {
            $lineTotal = round($quantity * $unitPrice * $rentalDays, 2);
            $subtotal += $lineTotal;
            $validItems[] = [
                'item_name' => $itemName,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'rental_days' => $rentalDays,
                'line_total' => $lineTotal,
            ];
        }
    }

    if (count($validItems) === 0) {
        echo 'Debes agregar al menos un artículo al documento.';
        exit;
    }

    $subtotal = round($subtotal, 2);
    $tax = round($subtotal * ($ivaPercent / 100), 2);
    $total = round($subtotal + $tax, 2);

    $stmt = $pdo->prepare('SELECT MAX(doc_number) AS max_num FROM documents WHERE doc_type = :doc_type');
    $stmt->execute([':doc_type' => $docType]);
    $row = $stmt->fetch();
    $docNumber = isset($row['max_num']) && $row['max_num'] !== null ? ((int) $row['max_num']) + 1 : 1;

    $prefix = $docType === 'invoice' ? 'FAC-' : 'EST-';
    $docCode = $prefix . str_pad((string) $docNumber, 4, '0', STR_PAD_LEFT);
    $status = $docType === 'invoice' ? 'sent' : 'draft';

    $pdo->beginTransaction();

    $insertDocument = $pdo->prepare('INSERT INTO documents (doc_type, doc_number, doc_code, status, document_date, client_name, client_company, client_address, client_phone, representative, event_type, rental_end_date, subtotal, tax, total, notes) VALUES (:doc_type, :doc_number, :doc_code, :status, :document_date, :client_name, :client_company, :client_address, :client_phone, :representative, :event_type, :rental_end_date, :subtotal, :tax, :total, :notes)');

    $insertDocument->execute([
        ':doc_type' => $docType,
        ':doc_number' => $docNumber,
        ':doc_code' => $docCode,
        ':status' => $status,
        ':document_date' => $documentDate,
        ':client_name' => $clientName,
        ':client_company' => $clientCompany,
        ':client_address' => $clientAddress,
        ':client_phone' => $clientPhone,
        ':representative' => $representative,
        ':event_type' => $eventType,
        ':rental_end_date' => $rentalEndDate !== '' ? $rentalEndDate : null,
        ':subtotal' => $subtotal,
        ':tax' => $tax,
        ':total' => $total,
        ':notes' => $notes,
    ]);

    $documentId = $pdo->lastInsertId();

    $insertItem = $pdo->prepare('INSERT INTO document_items (document_id, item_name, unit_price, quantity, rental_days, line_total) VALUES (:document_id, :item_name, :unit_price, :quantity, :rental_days, :line_total)');

    foreach ($validItems as $item) {
        $insertItem->execute([
            ':document_id' => $documentId,
            ':item_name' => $item['item_name'],
            ':unit_price' => $item['unit_price'],
            ':quantity' => $item['quantity'],
            ':rental_days' => $item['rental_days'],
            ':line_total' => $item['line_total'],
        ]);
    }

    $pdo->commit();

    header('Location: view.php?id=' . $documentId);
    exit;
} catch (Exception $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'Ocurrió un error al guardar el documento.';
    // echo '<!-- ' . htmlspecialchars($e->getMessage()) . ' -->';
}
