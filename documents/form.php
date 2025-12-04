<?php
require_once __DIR__ . '/../config/config.php';

$docType = (isset($_GET['type']) && $_GET['type'] === 'invoice') ? 'invoice' : 'estimate';
$title = $docType === 'invoice' ? 'Nueva Factura' : 'Nuevo Estimado';
$today = date('Y-m-d');

$ivaPercent = 13.00;
try {
    $settingsStmt = $pdo->query('SELECT iva_percentage FROM settings LIMIT 1');
    $settingsRow = $settingsStmt->fetch();
    if ($settingsRow && isset($settingsRow['iva_percentage'])) {
        $ivaPercent = (float) $settingsRow['iva_percentage'];
    }
} catch (PDOException $e) {
    $ivaPercent = 13.00;
}

$products = [];
try {
    $productsStmt = $pdo->query('SELECT id, name, unit_price FROM items ORDER BY name ASC');
    $products = $productsStmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

require_once __DIR__ . '/../templates/header.php';
?>

<h1 class="mb-2"><?php echo htmlspecialchars($title); ?></h1>
<p class="text-muted mb-4">Llena los datos del cliente y del evento. Después podrás guardar el documento.</p>

<form action="save.php" method="POST" class="mb-5">
  <input type="hidden" name="doc_type" value="<?php echo htmlspecialchars($docType); ?>">

  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5 class="card-title mb-3">Datos del cliente</h5>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="client_name">Nombre del cliente</label>
          <input type="text" id="client_name" name="client_name" class="form-control form-control-lg" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="client_company">Empresa (opcional)</label>
          <input type="text" id="client_company" name="client_company" class="form-control form-control-lg">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="client_address">Dirección</label>
          <input type="text" id="client_address" name="client_address" class="form-control form-control-lg">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="client_phone">Teléfono</label>
          <input type="text" id="client_phone" name="client_phone" class="form-control form-control-lg">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5 class="card-title mb-3">Datos del evento</h5>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="representative">Representante</label>
          <input type="text" id="representative" name="representative" class="form-control form-control-lg">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="event_type">Tipo de evento</label>
          <input type="text" id="event_type" name="event_type" class="form-control form-control-lg">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="document_date">Fecha del documento</label>
          <input type="date" id="document_date" name="document_date" class="form-control form-control-lg" value="<?php echo $today; ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="rental_end_date">Día de finalización del alquiler</label>
          <input type="date" id="rental_end_date" name="rental_end_date" class="form-control form-control-lg">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="card-title mb-0">Artículos del alquiler</h5>
        <button type="button" class="btn btn-outline-primary" onclick="addItemRow()">Agregar artículo</button>
      </div>
      <p class="text-muted">Agrega los artículos del alquiler (sillas, mesas, manteles, etc.).</p>
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle" id="items-table">
          <thead class="table-light">
            <tr>
              <th>Descripción</th>
              <th style="width: 120px;">Cantidad</th>
              <th style="width: 120px;">Días</th>
              <th style="width: 150px;">Precio unitario</th>
              <th style="width: 150px;">Total línea</th>
              <th style="width: 80px;"></th>
            </tr>
          </thead>
          <tbody id="items-body"></tbody>
        </table>
      </div>
      <datalist id="productList">
        <?php foreach ($products as $product): ?>
          <option value="<?php echo htmlspecialchars($product['name']); ?>" data-price="<?php echo htmlspecialchars($product['unit_price']); ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>
  </div>

  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="card-title mb-0">Totales</h5>
        <small class="text-muted">IVA aplicado: <?php echo number_format($ivaPercent, 2); ?>% (editable en Configuración)</small>
      </div>
      <div class="row justify-content-end g-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold" for="subtotal">Subtotal</label>
          <input type="number" id="subtotal" name="subtotal" class="form-control form-control-lg text-end" step="0.01" readonly>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold" for="tax">IVA</label>
          <input type="number" id="tax" name="tax" class="form-control form-control-lg text-end" step="0.01" readonly>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold" for="total">Total</label>
          <input type="number" id="total" name="total" class="form-control form-control-lg text-end" step="0.01" readonly>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5 class="card-title mb-3">Notas</h5>
      <div class="mb-3">
        <label class="form-label fw-semibold" for="notes">Notas</label>
        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Escribe detalles adicionales..."></textarea>
      </div>
      <div class="d-flex gap-3">
        <button type="submit" class="btn btn-primary btn-lg">Guardar documento</button>
        <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='/documents/index.php'">Cancelar</button>
      </div>
    </div>
  </div>
</form>

<script>
  const IVA_PERCENT = <?php echo json_encode($ivaPercent); ?>;
  const PRODUCT_LIST = <?php echo json_encode($products); ?>;
  let itemIndex = 0;

  function createInput({ type, name, classes = '', step, min, readOnly = false }) {
    const input = document.createElement('input');
    input.type = type;
    input.name = name;
    input.className = classes;
    if (step) input.step = step;
    if (min !== undefined) input.min = min;
    if (readOnly) input.readOnly = true;
    return input;
  }

  function addItemRow() {
    const tbody = document.getElementById('items-body');
    const row = document.createElement('tr');
    const currentIndex = itemIndex++;

    const descCell = document.createElement('td');
    const descInput = createInput({
      type: 'text',
      name: `items[${currentIndex}][item_name]`,
      classes: 'form-control form-control-sm item-name',
    });
    descInput.setAttribute('list', 'productList');
    descCell.appendChild(descInput);

    const qtyCell = document.createElement('td');
    const qtyInput = createInput({
      type: 'number',
      name: `items[${currentIndex}][quantity]`,
      classes: 'form-control form-control-sm text-end',
      step: '0.01',
      min: '0',
    });
    qtyCell.appendChild(qtyInput);

    const daysCell = document.createElement('td');
    const daysInput = createInput({
      type: 'number',
      name: `items[${currentIndex}][rental_days]`,
      classes: 'form-control form-control-sm text-end rental-days',
      step: '1',
      min: '1',
    });
    daysInput.value = 1;
    daysCell.appendChild(daysInput);

    const priceCell = document.createElement('td');
    const priceInput = createInput({
      type: 'number',
      name: `items[${currentIndex}][unit_price]`,
      classes: 'form-control form-control-sm text-end',
      step: '0.01',
      min: '0',
    });
    priceCell.appendChild(priceInput);

    const totalCell = document.createElement('td');
    const totalInput = createInput({
      type: 'number',
      name: `items[${currentIndex}][line_total]`,
      classes: 'form-control form-control-sm text-end',
      step: '0.01',
      readOnly: true,
    });
    totalCell.appendChild(totalInput);

    const deleteCell = document.createElement('td');
    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'btn btn-danger btn-sm';
    deleteBtn.textContent = 'Eliminar';
    deleteBtn.addEventListener('click', () => {
      row.remove();
      recalculateTotals();
    });
    deleteCell.appendChild(deleteBtn);

    [descCell, qtyCell, daysCell, priceCell, totalCell, deleteCell].forEach(cell => row.appendChild(cell));
    tbody.appendChild(row);

    qtyInput.addEventListener('input', recalculateTotals);
    priceInput.addEventListener('input', recalculateTotals);
    daysInput.addEventListener('input', recalculateTotals);
    descInput.addEventListener('change', () => {
      fillProductPrice(descInput, priceInput);
      recalculateTotals();
    });
    descInput.addEventListener('blur', () => {
      fillProductPrice(descInput, priceInput);
      recalculateTotals();
    });
  }

  function fillProductPrice(descInput, priceInput) {
    const value = descInput.value.trim().toLowerCase();
    if (!value) return;
    const match = PRODUCT_LIST.find(prod => (prod.name || '').toLowerCase() === value);
    if (match && priceInput) {
      priceInput.value = parseFloat(match.unit_price).toFixed(2);
    }
  }

  function recalculateTotals() {
    const tbody = document.getElementById('items-body');
    const subtotalInput = document.getElementById('subtotal');
    const taxInput = document.getElementById('tax');
    const totalInput = document.getElementById('total');
    let subtotal = 0;

    tbody.querySelectorAll('tr').forEach(row => {
      const qty = parseFloat(row.querySelector("input[name*='[quantity]']")?.value) || 0;
      const price = parseFloat(row.querySelector("input[name*='[unit_price]']")?.value) || 0;
      const days = parseFloat(row.querySelector("input[name*='[rental_days]']")?.value) || 1;
      const lineTotal = qty * price * days;
      const lineTotalInput = row.querySelector("input[name*='[line_total]']");
      if (lineTotalInput) {
        lineTotalInput.value = lineTotal.toFixed(2);
      }
      subtotal += lineTotal;
    });

    const tax = subtotal * (IVA_PERCENT / 100);
    const total = subtotal + tax;

    subtotalInput.value = subtotal.toFixed(2);
    taxInput.value = tax.toFixed(2);
    totalInput.value = total.toFixed(2);
  }

  document.addEventListener('DOMContentLoaded', () => {
    addItemRow();
    recalculateTotals();
  });
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
