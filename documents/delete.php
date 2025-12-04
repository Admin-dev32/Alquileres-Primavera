<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();
require_permission('delete_documents');

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$documentId) {
    header('Location: /documents/index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE documents SET is_deleted = 1 WHERE id = :id');
    $stmt->execute([':id' => $documentId]);
} catch (PDOException $e) {
    error_log('Error al eliminar documento: ' . $e->getMessage());
}

header('Location: /documents/index.php');
exit;
