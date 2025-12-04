<?php
// Inicializa la tabla de usuarios y crea el usuario propietario inicial si no existe ninguno.
if (!function_exists('ap_bootstrap_owner')) {
    function ap_bootstrap_owner(PDO $pdo): void
    {
        // Crea tabla si no existe.
        $createSql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_owner TINYINT(1) NOT NULL DEFAULT 0,
            can_view_documents TINYINT(1) NOT NULL DEFAULT 1,
            can_create_documents TINYINT(1) NOT NULL DEFAULT 1,
            can_edit_documents TINYINT(1) NOT NULL DEFAULT 1,
            can_delete_documents TINYINT(1) NOT NULL DEFAULT 1,
            can_manage_payments TINYINT(1) NOT NULL DEFAULT 1,
            can_view_finances TINYINT(1) NOT NULL DEFAULT 1,
            can_manage_settings TINYINT(1) NOT NULL DEFAULT 1,
            can_manage_users TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        try {
            $pdo->exec($createSql);
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($count === 0) {
                $hash = password_hash('Canelo2025.', PASSWORD_DEFAULT);
                $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, is_owner, can_view_documents, can_create_documents, can_edit_documents, can_delete_documents, can_manage_payments, can_view_finances, can_manage_settings, can_manage_users) VALUES (:name, :email, :password_hash, 1, 1, 1, 1, 1, 1, 1, 1, 1)');
                $insert->execute([
                    ':name' => 'Administrador principal',
                    ':email' => 'alquileresprimavera@gmail.com',
                    ':password_hash' => $hash,
                ]);
                // Recordatorio: cambiar la contraseña tras el primer inicio de sesión.
            }
        } catch (PDOException $e) {
            error_log('Error en bootstrap de usuarios: ' . $e->getMessage());
        }
    }
}

if (isset($pdo) && $pdo instanceof PDO) {
    ap_bootstrap_owner($pdo);
}
