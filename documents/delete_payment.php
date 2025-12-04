<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();
require_permission('manage_payments');

$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$documentId = filter_input(INPUT_GET, 'document_id', FILTER_VALIDATE_INT);

if (!$paymentId || !$documentId) {
    header('Location: /documents/index.php');
    exit;
}

try {
    $docStmt = $pdo->prepare('SELECT id, total FROM documents WHERE id = :id AND is_deleted = 0 LIMIT 1');
    $docStmt->execute([':id' => $documentId]);
    $document = $docStmt->fetch();

    if ($document) {
        $delete = $pdo->prepare('DELETE FROM payments WHERE id = :id AND document_id = :document_id');
        $delete->execute([
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
    }
} catch (PDOException $e) {
    error_log('Error al eliminar pago: ' . $e->getMessage());
}

header('Location: /documents/view.php?id=' . (int) $documentId);
exit;
