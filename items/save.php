<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /items/index.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : null;
$unitPrice = isset($_POST['unit_price']) ? $_POST['unit_price'] : '';
$unitLabel = isset($_POST['unit_label']) ? trim($_POST['unit_label']) : '';

if ($name === '' || !is_numeric($unitPrice) || (float) $unitPrice < 0) {
    echo 'Por favor completa los campos obligatorios.';
    exit;
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE items SET name = :name, description = :description, unit_price = :unit_price, unit_label = :unit_label WHERE id = :id');
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':unit_price' => $unitPrice,
            ':unit_label' => $unitLabel,
            ':id' => $id
        ]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO items (name, description, unit_price, unit_label) VALUES (:name, :description, :unit_price, :unit_label)');
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':unit_price' => $unitPrice,
            ':unit_label' => $unitLabel
        ]);
    }

    header('Location: /items/index.php');
    exit;
} catch (PDOException $e) {
    echo 'Ocurrió un error al guardar el artículo.';
    exit;
}
