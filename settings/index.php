<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'Configuración';
$alertaExito = '';
$alertaError = '';

try {
    $columnFixes = [
        'business_email' => "ALTER TABLE settings ADD COLUMN business_email VARCHAR(255) DEFAULT NULL",
        'default_notes' => "ALTER TABLE settings ADD COLUMN default_notes TEXT NULL",
        'iva_percentage' => "ALTER TABLE settings ADD COLUMN iva_percentage DECIMAL(5,2) NOT NULL DEFAULT 13.00"
    ];

    foreach ($columnFixes as $columnName => $alterSql) {
        try {
            $check = $pdo->prepare('SHOW COLUMNS FROM settings LIKE :col');
            $check->execute([':col' => $columnName]);
            if (!$check->fetch()) {
                $pdo->exec($alterSql);
            }
        } catch (PDOException $e) {
            error_log('Ajuste de columna fallido (' . $columnName . '): ' . $e->getMessage());
        }
    }

    $stmt = $pdo->query('SELECT * FROM settings LIMIT 1');
    $settings = $stmt->fetch();

    if (!$settings) {
        $insert = $pdo->prepare("INSERT INTO settings (business_name, iva_percentage, created_at) VALUES ('Alquileres Primavera', 13.00, NOW())");
        $insert->execute();
        $stmt = $pdo->query('SELECT * FROM settings LIMIT 1');
        $settings = $stmt->fetch();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $businessName = trim($_POST['business_name'] ?? '');
        $businessAddress = trim($_POST['business_address'] ?? '');
        $businessPhone = trim($_POST['business_phone'] ?? '');
        $businessWhatsapp = trim($_POST['business_whatsapp'] ?? '');
        $businessEmail = trim($_POST['business_email'] ?? '');
        $ivaPercentage = trim($_POST['iva_percentage'] ?? '');
        $defaultNotes = trim($_POST['default_notes'] ?? '');

        if ($ivaPercentage === '' || !is_numeric($ivaPercentage) || (float)$ivaPercentage < 0) {
            $alertaError = 'Por favor ingresa un IVA válido.';
        } else {
            $update = $pdo->prepare('UPDATE settings SET business_name = :business_name, business_address = :business_address, business_phone = :business_phone, business_whatsapp = :business_whatsapp, business_email = :business_email, iva_percentage = :iva_percentage, default_notes = :default_notes WHERE id = :id');
            $update->execute([
                ':business_name' => $businessName,
                ':business_address' => $businessAddress,
                ':business_phone' => $businessPhone,
                ':business_whatsapp' => $businessWhatsapp,
                ':business_email' => $businessEmail,
                ':iva_percentage' => (float)$ivaPercentage,
                ':default_notes' => $defaultNotes,
                ':id' => $settings['id']
            ]);
            $alertaExito = 'Configuración guardada correctamente.';

            $stmt = $pdo->query('SELECT * FROM settings LIMIT 1');
            $settings = $stmt->fetch();
        }
    }
} catch (PDOException $e) {
    error_log('Error en configuración: ' . $e->getMessage());
    $alertaError = 'Ocurrió un error al cargar o guardar la configuración.';
}

include __DIR__ . '/../templates/header.php';
?>

<div class="container my-4">
    <h1 class="mb-3">Configuración</h1>
    <p class="text-muted">Actualiza los datos principales del negocio y el IVA aplicado en tus documentos.</p>

    <?php if ($alertaExito): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($alertaExito); ?></div>
    <?php endif; ?>

    <?php if ($alertaError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($alertaError); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nombre del negocio</label>
                        <input type="text" name="business_name" class="form-control" value="<?php echo htmlspecialchars($settings['business_name'] ?? ''); ?>" placeholder="Alquileres Primavera" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Correo electrónico</label>
                        <input type="email" name="business_email" class="form-control" value="<?php echo htmlspecialchars($settings['business_email'] ?? ''); ?>" placeholder="correo@ejemplo.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Dirección</label>
                        <input type="text" name="business_address" class="form-control" value="<?php echo htmlspecialchars($settings['business_address'] ?? ''); ?>" placeholder="Dirección del negocio">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Teléfono</label>
                        <input type="text" name="business_phone" class="form-control" value="<?php echo htmlspecialchars($settings['business_phone'] ?? ''); ?>" placeholder="7777-7777">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">WhatsApp</label>
                        <input type="text" name="business_whatsapp" class="form-control" value="<?php echo htmlspecialchars($settings['business_whatsapp'] ?? ''); ?>" placeholder="WhatsApp del negocio">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">IVA (%)</label>
                        <input type="number" name="iva_percentage" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($settings['iva_percentage'] ?? '0'); ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notas por defecto para facturas y estimados</label>
                        <textarea name="default_notes" class="form-control" rows="3" placeholder="Texto que aparecerá en tus documentos de forma predeterminada."><?php echo htmlspecialchars($settings['default_notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Guardar configuración</button>
                    <a href="/index.php" class="btn btn-secondary btn-lg">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
