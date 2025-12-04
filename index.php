<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';
require_login();
include __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-3 text-primary">Bienvenida al sistema</h1>
                <p class="lead mb-4">Aquí puedes crear estimados, facturas y llevar el control de ingresos y gastos.</p>
                <div class="d-flex flex-column flex-md-row gap-3">
                    <a class="btn btn-primary btn-lg w-100" href="/documents/form.php?type=estimate">Nuevo Estimado</a>
                    <a class="btn btn-success btn-lg w-100" href="/documents/form.php?type=invoice">Nueva Factura</a>
                    <a class="btn btn-secondary btn-lg w-100" href="/finance/index.php">Ver Finanzas</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
