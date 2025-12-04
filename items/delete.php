<?php
require_once __DIR__ . '/../config/config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /items/index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM items WHERE id = :id');
    $stmt->execute([':id' => $id]);
} catch (PDOException $e) {
    echo 'Ocurrió un error al eliminar el artículo.';
    exit;
}

header('Location: /items/index.php');
exit;
