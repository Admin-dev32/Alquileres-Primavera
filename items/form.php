<?php
require_once __DIR__ . '/../config/config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$name = '';
$description = '';
$unitPrice = '';
$unitLabel = '';
$isEdit = false;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();
        if ($item) {
            $isEdit = true;
            $name = $item['name'];
            $description = $item['description'];
            $unitPrice = $item['unit_price'];
            $unitLabel = $item['unit_label'];
        } else {
            echo 'Artículo no encontrado.';
            exit;
        }
    } catch (PDOException $e) {
        echo 'Ocurrió un error al cargar el artículo.';
        exit;
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3"><?php echo $isEdit ? 'Editar artículo' : 'Nuevo artículo'; ?></h1>
    <p class="text-muted mb-0">Estos artículos te ayudan a llenar rápido tus estimados y facturas.</p>
  </div>
</div>

<form method="POST" action="save.php" class="card">
  <div class="card-body">
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
    <?php endif; ?>

    <div class="mb-3">
      <label for="name" class="form-label">Nombre del artículo</label>
      <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($name); ?>">
    </div>

    <div class="mb-3">
      <label for="description" class="form-label">Descripción</label>
      <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
    </div>

    <div class="mb-3">
      <label for="unit_price" class="form-label">Precio unitario</label>
      <input type="number" class="form-control" id="unit_price" name="unit_price" step="0.01" min="0" required value="<?php echo htmlspecialchars($unitPrice); ?>">
    </div>

    <div class="mb-3">
      <label for="unit_label" class="form-label">Unidad (ej. silla, mesa, día)</label>
      <input type="text" class="form-control" id="unit_label" name="unit_label" value="<?php echo htmlspecialchars($unitLabel); ?>">
    </div>
  </div>
  <div class="card-footer d-flex justify-content-end gap-2">
    <button type="button" class="btn btn-secondary" onclick="window.location.href='/items/index.php'">Cancelar</button>
    <button type="submit" class="btn btn-primary">Guardar</button>
  </div>
</form>

<?php require_once __DIR__ . '/../templates/footer.php';
