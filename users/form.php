<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();
require_permission('manage_users');

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$editing = $userId !== false && $userId !== null;
$error = '';
$success = '';

$fields = [
    'name' => '',
    'email' => '',
    'is_owner' => 0,
    'can_view_documents' => 1,
    'can_create_documents' => 1,
    'can_edit_documents' => 1,
    'can_delete_documents' => 1,
    'can_manage_payments' => 1,
    'can_view_finances' => 1,
    'can_manage_settings' => 1,
    'can_manage_users' => 1,
];

if ($editing) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $existing = $stmt->fetch();
        if (!$existing) {
            $error = 'Usuario no encontrado.';
            $editing = false;
        } else {
            foreach ($fields as $key => $value) {
                if (isset($existing[$key])) {
                    $fields[$key] = $existing[$key];
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Ocurrió un error al cargar el usuario.';
        error_log($e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields['name'] = trim($_POST['name'] ?? '');
    $fields['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $permissions = [
        'can_view_documents',
        'can_create_documents',
        'can_edit_documents',
        'can_delete_documents',
        'can_manage_payments',
        'can_view_finances',
        'can_manage_settings',
        'can_manage_users',
    ];
    foreach ($permissions as $perm) {
        $fields[$perm] = isset($_POST[$perm]) ? 1 : 0;
    }

    if ($fields['name'] === '' || $fields['email'] === '') {
        $error = 'Nombre y correo son obligatorios.';
    }
    if (!$error && $password !== '' && $password !== $passwordConfirm) {
        $error = 'Las contraseñas no coinciden.';
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();
            if ($editing) {
                $emailCheck = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id');
                $emailCheck->execute([':email' => $fields['email'], ':id' => $userId]);
                if ((int)$emailCheck->fetchColumn() > 0) {
                    throw new Exception('El correo ya está en uso.');
                }

                $updateSql = 'UPDATE users SET name = :name, email = :email, can_view_documents = :can_view_documents, can_create_documents = :can_create_documents, can_edit_documents = :can_edit_documents, can_delete_documents = :can_delete_documents, can_manage_payments = :can_manage_payments, can_view_finances = :can_view_finances, can_manage_settings = :can_manage_settings, can_manage_users = :can_manage_users WHERE id = :id';
                $params = [
                    ':name' => $fields['name'],
                    ':email' => $fields['email'],
                    ':can_view_documents' => $fields['can_view_documents'],
                    ':can_create_documents' => $fields['can_create_documents'],
                    ':can_edit_documents' => $fields['can_edit_documents'],
                    ':can_delete_documents' => $fields['can_delete_documents'],
                    ':can_manage_payments' => $fields['can_manage_payments'],
                    ':can_view_finances' => $fields['can_view_finances'],
                    ':can_manage_settings' => $fields['can_manage_settings'],
                    ':can_manage_users' => $fields['can_manage_users'],
                    ':id' => $userId,
                ];

                if ($password !== '') {
                    $updateSql = str_replace('WHERE id = :id', ', password_hash = :password_hash WHERE id = :id', $updateSql);
                    $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }

                $update = $pdo->prepare($updateSql);
                $update->execute($params);
            } else {
                $emailCheck = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
                $emailCheck->execute([':email' => $fields['email']]);
                if ((int)$emailCheck->fetchColumn() > 0) {
                    throw new Exception('El correo ya está en uso.');
                }
                if ($password === '') {
                    throw new Exception('La contraseña es obligatoria para crear usuario.');
                }
                $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, can_view_documents, can_create_documents, can_edit_documents, can_delete_documents, can_manage_payments, can_view_finances, can_manage_settings, can_manage_users) VALUES (:name, :email, :password_hash, :can_view_documents, :can_create_documents, :can_edit_documents, :can_delete_documents, :can_manage_payments, :can_view_finances, :can_manage_settings, :can_manage_users)');
                $insert->execute([
                    ':name' => $fields['name'],
                    ':email' => $fields['email'],
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':can_view_documents' => $fields['can_view_documents'],
                    ':can_create_documents' => $fields['can_create_documents'],
                    ':can_edit_documents' => $fields['can_edit_documents'],
                    ':can_delete_documents' => $fields['can_delete_documents'],
                    ':can_manage_payments' => $fields['can_manage_payments'],
                    ':can_view_finances' => $fields['can_view_finances'],
                    ':can_manage_settings' => $fields['can_manage_settings'],
                    ':can_manage_users' => $fields['can_manage_users'],
                ]);
                $userId = (int) $pdo->lastInsertId();
                $editing = true;
            }

            $pdo->commit();
            $success = 'Usuario guardado correctamente.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

include __DIR__ . '/../templates/header.php';
?>

<h1 class="mb-3"><?php echo $editing ? 'Editar usuario' : 'Crear usuario'; ?></h1>
<p class="text-muted">Define el acceso de cada persona al sistema.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="" class="row g-3">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?php echo (int) $userId; ?>">
            <?php endif; ?>
            <div class="col-md-6">
                <label class="form-label">Nombre</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($fields['name']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Correo electrónico</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($fields['email']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Contraseña <?php echo $editing ? '(deja en blanco para no cambiar)' : ''; ?></label>
                <input type="password" name="password" class="form-control" <?php echo $editing ? '' : 'required'; ?>>
            </div>
            <div class="col-md-6">
                <label class="form-label">Confirmar contraseña</label>
                <input type="password" name="password_confirm" class="form-control" <?php echo $editing ? '' : 'required'; ?>>
            </div>

            <div class="col-12">
                <h5 class="mt-3">Permisos</h5>
                <div class="row">
                    <?php
                    $permLabels = [
                        'can_view_documents' => 'Puede ver documentos',
                        'can_create_documents' => 'Puede crear documentos',
                        'can_edit_documents' => 'Puede editar documentos',
                        'can_delete_documents' => 'Puede eliminar documentos',
                        'can_manage_payments' => 'Puede gestionar pagos',
                        'can_view_finances' => 'Puede ver finanzas',
                        'can_manage_settings' => 'Puede gestionar configuración',
                        'can_manage_users' => 'Puede gestionar usuarios',
                    ];
                    foreach ($permLabels as $key => $label):
                    ?>
                        <div class="col-sm-6 col-md-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="<?php echo $key; ?>" name="<?php echo $key; ?>" <?php echo !empty($fields[$key]) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="<?php echo $key; ?>"><?php echo $label; ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Guardar usuario</button>
                <a href="/users/index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
