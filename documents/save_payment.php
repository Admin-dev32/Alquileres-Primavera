<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();
require_permission('manage_payments');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /documents/index.php');
    exit;
}

$documentId = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
$paymentDate = trim($_POST['payment_date'] ?? '');
$amount = $_POST['amount'] ?? '';
$method = trim($_POST['method'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$documentId) {
    header('Location: /documents/index.php');
    exit;
}

try {
    $docStmt = $pdo->prepare('SELECT id, total FROM documents WHERE id = :id AND is_deleted = 0 LIMIT 1');
    $docStmt->execute([':id' => $documentId]);
    $document = $docStmt->fetch();

    if (!$document) {
        header('Location: /documents/view.php?id=' . $documentId . '&msg=error');
        exit;
    }

    $amount = is_numeric($amount) ? (float) $amount : 0;
    if ($amount <= 0 || $paymentDate === '') {
        header('Location: /documents/view.php?id=' . $documentId . '&msg=error');
        exit;
    }

    $paymentsTotalStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE document_id = :id');
    $paymentsTotalStmt->execute([':id' => $documentId]);
    $pagadoActual = (float) $paymentsTotalStmt->fetchColumn();

    $saldoPendiente = (float) $document['total'] - $pagadoActual;
    if ($amount > $saldoPendiente) {
        $amount = $saldoPendiente;
    }

    if ($amount <= 0) {
        header('Location: /documents/view.php?id=' . $documentId . '&msg=error');
        exit;
    }

    $insert = $pdo->prepare('INSERT INTO payments (document_id, payment_date, amount, method, notes) VALUES (:document_id, :payment_date, :amount, :method, :notes)');
    $insert->execute([
        ':document_id' => $documentId,
        ':payment_date' => $paymentDate,
        ':amount' => $amount,
        ':method' => $method !== '' ? $method : 'Otro',
        ':notes' => $notes !== '' ? $notes : null,
    ]);

    $paymentsTotalStmt->execute([':id' => $documentId]);
    $pagadoTotal = (float) $paymentsTotalStmt->fetchColumn();

    $statusToSet = 'draft';
    if ($pagadoTotal >= (float) $document['total']) {
        $statusToSet = 'paid';
    } elseif ($pagadoTotal > 0) {
        $statusToSet = 'sent';
    } else {
        $statusToSet = 'draft';
    }

    $statusStmt = $pdo->prepare('UPDATE documents SET status = :status WHERE id = :id');
    $statusStmt->execute([
        ':status' => $statusToSet,
        ':id' => $documentId,
    ]);

    header('Location: /documents/view.php?id=' . $documentId);
    exit;
} catch (Exception $e) {
    error_log('Error al guardar pago: ' . $e->getMessage());
    header('Location: /documents/view.php?id=' . $documentId . '&msg=error');
    exit;
}
