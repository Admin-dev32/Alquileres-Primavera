<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();
require_permission('manage_users');

$users = [];
$error = '';

try {
    $stmt = $pdo->query('SELECT id, name, email, is_owner, can_view_documents, can_create_documents, can_edit_documents, can_delete_documents, can_manage_payments, can_view_finances, can_manage_settings, can_manage_users FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Ocurrió un error al cargar los usuarios.';
    error_log($e->getMessage());
}

include __DIR__ . '/../templates/header.php';
?>

<h1 class="mb-3">Usuarios</h1>
<p class="text-muted">Administra los accesos y permisos del sistema.</p>
<div class="d-flex justify-content-end mb-3">
    <a href="/users/form.php" class="btn btn-primary">Crear usuario</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Permisos clave</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo ((int)$user['is_owner'] === 1) ? 'Propietario' : 'Usuario'; ?></td>
                        <td>
                            <span class="badge bg-secondary">Documentos: <?php echo (int)$user['can_view_documents']; ?></span>
                            <span class="badge bg-secondary">Pagos: <?php echo (int)$user['can_manage_payments']; ?></span>
                            <span class="badge bg-secondary">Finanzas: <?php echo (int)$user['can_view_finances']; ?></span>
                            <span class="badge bg-secondary">Config: <?php echo (int)$user['can_manage_settings']; ?></span>
                            <span class="badge bg-secondary">Usuarios: <?php echo (int)$user['can_manage_users']; ?></span>
                        </td>
                        <td class="text-nowrap">
                            <a href="/users/form.php?id=<?php echo (int)$user['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
