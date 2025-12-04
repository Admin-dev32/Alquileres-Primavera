<?php
require_once __DIR__ . '/../config/config.php';

$items = [];
$errorLoading = false;

try {
    $stmt = $pdo->query("SELECT * FROM items ORDER BY created_at DESC, id DESC");
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorLoading = true;
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3">Artículos / Productos</h1>
    <p class="text-muted mb-0">Aquí puedes gestionar los artículos que usas en tus estimados y facturas (sillas, mesas, manteles, etc.).</p>
  </div>
  <div>
    <a href="/items/form.php" class="btn btn-primary">Agregar artículo</a>
  </div>
</div>

<?php
if ($errorLoading) {
    echo '<div class="alert alert-danger">Ocurrió un error al cargar los artículos.</div>';
} elseif (empty($items)) {
    echo '<div class="alert alert-info">Todavía no hay artículos registrados.</div>';
} else {
    ?>
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-bordered mb-0">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Precio unitario</th>
                <th>Unidad</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo htmlspecialchars(mb_strimwidth($item['description'] ?? '', 0, 60, '...')); ?></td>
                <td><?php echo number_format((float)$item['unit_price'], 2, '.', ''); ?></td>
                <td><?php echo htmlspecialchars($item['unit_label']); ?></td>
                <td>
                  <a href="/items/form.php?id=<?php echo urlencode($item['id']); ?>" class="btn btn-sm btn-secondary me-1">Editar</a>
                  <a href="/items/delete.php?id=<?php echo urlencode($item['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Seguro que quieres eliminar este artículo?');">Eliminar</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
}
?>

<?php require_once __DIR__ . '/../templates/footer.php';
