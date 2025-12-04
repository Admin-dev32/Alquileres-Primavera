<?php
require_once __DIR__ . '/../config/config.php';

$summary = [
    'ingresos' => 0,
    'gastos' => 0,
    'balance' => 0,
];
$payments = [];
$expenses = [];
$error = false;

try {
    $ingresosStmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) AS total FROM payments');
    $summary['ingresos'] = (float) ($ingresosStmt->fetchColumn());

    $gastosStmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) AS total FROM expenses');
    $summary['gastos'] = (float) ($gastosStmt->fetchColumn());

    $summary['balance'] = $summary['ingresos'] - $summary['gastos'];

    $paymentsStmt = $pdo->query('SELECT p.*, d.doc_code, d.client_name FROM payments p LEFT JOIN documents d ON p.document_id = d.id ORDER BY p.payment_date DESC, p.id DESC LIMIT 10');
    $payments = $paymentsStmt->fetchAll();

    $expensesStmt = $pdo->query('SELECT * FROM expenses ORDER BY expense_date DESC, id DESC LIMIT 10');
    $expenses = $expensesStmt->fetchAll();
} catch (PDOException $e) {
    $error = true;
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0">Finanzas</h1>
        <p class="text-muted mb-0">Aquí puedes ver un resumen de ingresos, gastos y registrar nuevos movimientos.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/finance/add_payment.php" class="btn btn-primary">Registrar pago</a>
        <a href="/finance/add_expense.php" class="btn btn-secondary">Registrar gasto</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">Ocurrió un error al cargar la información financiera.</div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-muted">Total ingresos</h5>
                    <p class="fs-3 fw-bold text-success mb-0"><?php echo number_format($summary['ingresos'], 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-muted">Total gastos</h5>
                    <p class="fs-3 fw-bold text-danger mb-0"><?php echo number_format($summary['gastos'], 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-muted">Balance</h5>
                    <p class="fs-3 fw-bold mb-0"><?php echo number_format($summary['balance'], 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="mb-0">Pagos recientes</h4>
                <a href="/finance/add_payment.php" class="btn btn-outline-primary btn-sm">Registrar pago</a>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <p class="text-muted mb-0">Todavía no hay pagos registrados.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Documento</th>
                                        <th>Cliente</th>
                                        <th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                            <td><?php echo $payment['doc_code'] ? htmlspecialchars($payment['doc_code']) : '(Sin documento)'; ?></td>
                                            <td><?php echo htmlspecialchars($payment['client_name'] ?? ''); ?></td>
                                            <td class="text-end fw-semibold"><?php echo number_format((float) $payment['amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="mb-0">Gastos recientes</h4>
                <a href="/finance/add_expense.php" class="btn btn-outline-secondary btn-sm">Registrar gasto</a>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($expenses)): ?>
                        <p class="text-muted mb-0">Todavía no hay gastos registrados.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Categoría</th>
                                        <th>Descripción</th>
                                        <th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($expense['expense_date']); ?></td>
                                            <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                            <td class="text-end fw-semibold"><?php echo number_format((float) $expense['amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/footer.php';
