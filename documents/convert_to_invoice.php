<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();
require_permission('edit_documents');

$documentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($documentId <= 0) {
    header('Location: /documents/index.php');
    exit;
}

try {
    // Load estimate
    $docStmt = $pdo->prepare('SELECT * FROM documents WHERE id = :id AND doc_type = "estimate"');
    $docStmt->execute([':id' => $documentId]);
    $estimate = $docStmt->fetch();

    if (!$estimate) {
        echo 'Estimado no encontrado.';
        exit;
    }

    // Load estimate items
    $itemsStmt = $pdo->prepare('SELECT * FROM document_items WHERE document_id = :id ORDER BY id ASC');
    $itemsStmt->execute([':id' => $documentId]);
    $items = $itemsStmt->fetchAll();

    // Next invoice number
    $maxStmt = $pdo->query('SELECT MAX(doc_number) AS max_num FROM documents WHERE doc_type = "invoice"');
    $maxNum = $maxStmt->fetchColumn();
    $nextNumber = $maxNum ? ((int) $maxNum + 1) : 1;
    $docNumberPadded = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    $newDocCode = 'FAC-' . $docNumberPadded;

    $nowDate = date('Y-m-d');

    $pdo->beginTransaction();

    $insertDoc = $pdo->prepare('INSERT INTO documents (
        doc_type, doc_number, doc_code, status, document_date,
        client_name, client_company, client_address, client_phone,
        representative, event_type, rental_end_date,
        subtotal, tax, total, notes
    ) VALUES (
        :doc_type, :doc_number, :doc_code, :status, :document_date,
        :client_name, :client_company, :client_address, :client_phone,
        :representative, :event_type, :rental_end_date,
        :subtotal, :tax, :total, :notes
    )');

    $insertDoc->execute([
        ':doc_type' => 'invoice',
        ':doc_number' => $nextNumber,
        ':doc_code' => $newDocCode,
        ':status' => 'sent',
        ':document_date' => $nowDate,
        ':client_name' => $estimate['client_name'],
        ':client_company' => $estimate['client_company'],
        ':client_address' => $estimate['client_address'],
        ':client_phone' => $estimate['client_phone'],
        ':representative' => $estimate['representative'],
        ':event_type' => $estimate['event_type'],
        ':rental_end_date' => $estimate['rental_end_date'],
        ':subtotal' => $estimate['subtotal'],
        ':tax' => $estimate['tax'],
        ':total' => $estimate['total'],
        ':notes' => $estimate['notes'],
    ]);

    $newDocumentId = $pdo->lastInsertId();

    $insertItem = $pdo->prepare('INSERT INTO document_items (document_id, item_name, unit_price, quantity, line_total) VALUES (:document_id, :item_name, :unit_price, :quantity, :line_total)');

    foreach ($items as $item) {
        $insertItem->execute([
            ':document_id' => $newDocumentId,
            ':item_name' => $item['item_name'],
            ':unit_price' => $item['unit_price'],
            ':quantity' => $item['quantity'],
            ':line_total' => $item['line_total'],
        ]);
    }

    $pdo->commit();

    header('Location: view.php?id=' . $newDocumentId);
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'Ocurrió un error al convertir el estimado en factura.';
    exit;
}
