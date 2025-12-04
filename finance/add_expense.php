<?php
require_once __DIR__ . '/../config/config.php';

$errorMessage = '';
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expenseDate = isset($_POST['expense_date']) ? trim($_POST['expense_date']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
    $paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($expenseDate === '' || $amount <= 0) {
        $errorMessage = 'Por favor completa todos los campos obligatorios.';
    } else {
        try {
            $insert = $pdo->prepare('INSERT INTO expenses (expense_date, category, description, amount, payment_method, notes) VALUES (:expense_date, :category, :description, :amount, :payment_method, :notes)');
            $insert->execute([
                ':expense_date' => $expenseDate,
                ':category' => $category,
                ':description' => $description,
                ':amount' => $amount,
                ':payment_method' => $paymentMethod,
                ':notes' => $notes,
            ]);

            header('Location: /finance/index.php');
            exit;
        } catch (PDOException $e) {
            $errorMessage = 'Ocurrió un error al guardar el gasto.';
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0">Registrar gasto</h1>
        <p class="text-muted mb-0">Registra rápidamente un nuevo gasto del negocio.</p>
    </div>
    <a href="/finance/index.php" class="btn btn-secondary">Volver</a>
</div>

<?php if ($errorMessage): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="expense_date" class="form-label fw-semibold">Fecha del gasto</label>
                    <input type="date" class="form-control" id="expense_date" name="expense_date" required value="<?php echo htmlspecialchars($today); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label for="category" class="form-label fw-semibold">Categoría</label>
                    <input type="text" class="form-control" id="category" name="category" placeholder="Combustible, Mantenimiento, etc.">
                </div>
            </div>

            <div class="mt-3">
                <label for="description" class="form-label fw-semibold">Descripción</label>
                <input type="text" class="form-control" id="description" name="description">
            </div>

            <div class="row g-3 mt-1">
                <div class="col-12 col-md-6">
                    <label for="amount" class="form-label fw-semibold">Monto</label>
                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                </div>
                <div class="col-12 col-md-6">
                    <label for="payment_method" class="form-label fw-semibold">Método de pago</label>
                    <input type="text" class="form-control" id="payment_method" name="payment_method" placeholder="Efectivo, Tarjeta, etc.">
                </div>
            </div>

            <div class="mt-3">
                <label for="notes" class="form-label fw-semibold">Notas</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Guardar gasto</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='/finance/index.php'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php';
