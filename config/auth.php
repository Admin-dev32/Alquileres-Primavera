<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require_once __DIR__ . '/config.php';
}

function current_user(): ?array
{
    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $cachedUser = $user ?: null;
        return $cachedUser;
    } catch (PDOException $e) {
        error_log('Error al cargar usuario actual: ' . $e->getMessage());
        return null;
    }
}

function is_owner(): bool
{
    $user = current_user();
    return isset($user['is_owner']) && (int) $user['is_owner'] === 1;
}

function user_has_permission(string $permissionKey): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if (is_owner()) {
        return true;
    }

    $map = [
        'view_documents' => 'can_view_documents',
        'create_documents' => 'can_create_documents',
        'edit_documents' => 'can_edit_documents',
        'delete_documents' => 'can_delete_documents',
        'manage_payments' => 'can_manage_payments',
        'view_finances' => 'can_view_finances',
        'manage_settings' => 'can_manage_settings',
        'manage_users' => 'can_manage_users',
    ];

    if (!isset($map[$permissionKey])) {
        return false;
    }

    return !empty($user[$map[$permissionKey]]);
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function require_permission(string $permissionKey): void
{
    if (!current_user()) {
        header('Location: /auth/login.php');
        exit;
    }
    if (!user_has_permission($permissionKey)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso denegado</title>';
        echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head><body class="bg-light">';
        echo '<div class="container py-5"><div class="alert alert-danger">Acceso denegado.</div>';
        echo '<a class="btn btn-secondary" href="javascript:history.back();">Volver</a></div></body></html>';
        exit;
    }
}
