<?php
require_once __DIR__ . '/../config/config.php';

$type = isset($_GET['type']) ? trim($_GET['type']) : 'all';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';

$allowedTypes = ['estimate', 'invoice'];
$allowedStatus = ['draft', 'sent', 'paid', 'cancelled'];

if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

if (!in_array($status, $allowedStatus, true)) {
    $status = 'all';
}

$documents = [];
$errorLoading = false;

$sql = "SELECT * FROM documents WHERE is_deleted = 0";
$params = [];

if ($type !== 'all') {
    $sql .= " AND doc_type = :doc_type";
    $params[':doc_type'] = $type;
}

if ($status !== 'all') {
    $sql .= " AND status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorLoading = true;
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3">Documentos</h1>
    <p class="text-muted mb-0">Aquí puedes ver todos los estimados y facturas de Alquileres Primavera.</p>
  </div>
  <div class="text-end">
    <a href="/documents/form.php?type=estimate" class="btn btn-primary me-2">Nuevo Estimado</a>
    <a href="/documents/form.php?type=invoice" class="btn btn-success">Nueva Factura</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-3" method="GET" action="">
      <div class="col-md-4 col-sm-6">
        <label for="type" class="form-label">Tipo</label>
        <select class="form-select" id="type" name="type">
          <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>Todos</option>
          <option value="estimate" <?php echo $type === 'estimate' ? 'selected' : ''; ?>>Estimados</option>
          <option value="invoice" <?php echo $type === 'invoice' ? 'selected' : ''; ?>>Facturas</option>
        </select>
      </div>
      <div class="col-md-4 col-sm-6">
        <label for="status" class="form-label">Estado</label>
        <select class="form-select" id="status" name="status">
          <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Todos</option>
          <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Borrador</option>
          <option value="sent" <?php echo $status === 'sent' ? 'selected' : ''; ?>>Enviado</option>
          <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Pagado</option>
          <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
        </select>
      </div>
      <div class="col-md-4 col-sm-12 d-flex align-items-end">
        <button type="submit" class="btn btn-secondary">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<?php
if ($errorLoading) {
    echo '<div class="alert alert-danger">Ocurrió un error al cargar los documentos.</div>';
} elseif (empty($documents)) {
    echo '<div class="alert alert-info">No hay documentos registrados todavía.</div>';
} else {
    $statusLabels = [
        'draft' => 'Borrador',
        'sent' => 'Enviado',
        'paid' => 'Pagado',
        'cancelled' => 'Cancelado'
    ];
    $typeLabels = [
        'estimate' => 'Estimado',
        'invoice' => 'Factura'
    ];
    ?>
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-bordered mb-0">
            <thead>
              <tr>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Número</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Total</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
              <tr>
                <td><?php echo htmlspecialchars($typeLabels[$doc['doc_type']] ?? $doc['doc_type']); ?></td>
                <td>
                  <?php
                  $label = $statusLabels[$doc['status']] ?? $doc['status'];
                  $badgeClass = 'secondary';
                  if ($doc['status'] === 'paid') {
                      $badgeClass = 'success';
                  } elseif ($doc['status'] === 'sent') {
                      $badgeClass = 'info';
                  } elseif ($doc['status'] === 'cancelled') {
                      $badgeClass = 'danger';
                  }
                  ?>
                  <span class="badge bg-<?php echo $badgeClass; ?>">
                    <?php echo htmlspecialchars($label); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($doc['doc_code']); ?></td>
                <td><?php echo htmlspecialchars($doc['client_name']); ?></td>
                <td><?php echo htmlspecialchars($doc['document_date']); ?></td>
                <td><?php echo number_format((float)$doc['total'], 2, '.', ''); ?></td>
                <td class="d-flex flex-wrap gap-1">
                  <a class="btn btn-sm btn-primary" href="/documents/view.php?id=<?php echo urlencode($doc['id']); ?>">Ver</a>
                  <a class="btn btn-sm btn-outline-primary" href="/documents/form.php?id=<?php echo urlencode($doc['id']); ?>">Editar</a>
                  <a class="btn btn-sm btn-outline-danger" href="/documents/delete.php?id=<?php echo urlencode($doc['id']); ?>" onclick="return confirm('¿Seguro que deseas eliminar este documento?');">Eliminar</a>
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

