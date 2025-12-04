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
    $productsStmt = $pdo->query('SELECT id, name, unit_price AS price FROM items ORDER BY name ASC');
    $products = $productsStmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

 $documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
 $editing = false;
 $documentData = [
     'client_name' => '',
     'client_company' => '',
     'client_address' => '',
     'client_phone' => '',
     'representative' => '',
     'event_type' => '',
     'document_date' => $today,
     'rental_end_date' => '',
     'notes' => '',
 ];
 $existingItems = [];

 if ($documentId) {
     try {
         $docStmt = $pdo->prepare('SELECT * FROM documents WHERE id = :id AND is_deleted = 0 LIMIT 1');
         $docStmt->execute([':id' => $documentId]);
         $found = $docStmt->fetch();
         if ($found) {
             $editing = true;
             $docType = $found['doc_type'];
             $title = $docType === 'invoice' ? 'Editar Factura' : 'Editar Estimado';
             $documentData = [
                 'client_name' => $found['client_name'] ?? '',
                 'client_company' => $found['client_company'] ?? '',
                 'client_address' => $found['client_address'] ?? '',
                 'client_phone' => $found['client_phone'] ?? '',
                 'representative' => $found['representative'] ?? '',
                 'event_type' => $found['event_type'] ?? '',
                 'document_date' => $found['document_date'] ?? $today,
                 'rental_end_date' => $found['rental_end_date'] ?? '',
                 'notes' => $found['notes'] ?? '',
             ];

             $itemsLoadStmt = $pdo->prepare('SELECT item_name, quantity, unit_price, rental_days FROM document_items WHERE document_id = :id ORDER BY id ASC');
             $itemsLoadStmt->execute([':id' => $documentId]);
             $existingItems = $itemsLoadStmt->fetchAll();
         }
     } catch (PDOException $e) {
         // si falla la carga seguimos con formulario vacío
     }
 }

require_once __DIR__ . '/../templates/header.php';
?>

<h1 class="mb-2"><?php echo htmlspecialchars($title); ?></h1>
<p class="text-muted mb-4">Llena los datos del cliente y del evento. Después podrás guardar el documento.</p>

<form action="save.php" method="POST" class="mb-5">
  <input type="hidden" name="doc_type" value="<?php echo htmlspecialchars($docType); ?>">
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?php echo (int) $documentId; ?>">
  <?php endif; ?>

  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5 class="card-title mb-3">Datos del cliente</h5>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="client_name">Nombre del cliente</label>
          <input type="text" id="client_name" name="client_name" class="form-control form-control-lg" value="<?php echo htmlspecialchars($documentData['client_name']); ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="client_company">Empresa (opcional)</label>
          <input type="text" id="client_company" name="client_company" class="form-control form-control-lg" value="<?php echo htmlspecialchars($documentData['client_company']); ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="client_address">Dirección</label>
          <input type="text" id="client_address" name="client_address" class="form-control form-control-lg" value="<?php echo htmlspecialchars($documentData['client_address']); ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="client_phone">Teléfono</label>
          <input type="text" id="client_phone" name="client_phone" class="form-control form-control-lg" value="<?php echo htmlspecialchars($documentData['client_phone']); ?>">
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
          <input type="text" id="representative" name="representative" class="form-control form-control-lg" value="<?php echo htmlspecialchars($documentData['representative']); ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="event_type">Tipo de evento</label>
          <input type="text" id="event_type" name="event_type" class="form-control form-control-lg" value="<?php echo htmlspecialchars($documentData['event_type']); ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="document_date">Fecha del documento</label>
          <input type="date" id="document_date" name="document_date" class="form-control form-control-lg" value="<?php echo htmlspecialchars($documentData['document_date']); ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="rental_end_date">Día de finalización del alquiler</label>
          <input type="date" id="rental_end_date" name="rental_end_date" class="form-control form-control-lg" value="<?php echo htmlspecialchars($documentData['rental_end_date']); ?>">
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
          <option value="<?php echo htmlspecialchars($product['name']); ?>" data-id="<?php echo (int) $product['id']; ?>" data-price="<?php echo htmlspecialchars($product['price']); ?>"></option>
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
        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Escribe detalles adicionales..."><?php echo htmlspecialchars($documentData['notes']); ?></textarea>
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
  const PRODUCT_LIST = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;
  const EXISTING_ITEMS = <?php echo json_encode($existingItems, JSON_UNESCAPED_UNICODE); ?>;
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

  function addItemRow(data = {}) {
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
    descInput.value = data.item_name ? data.item_name : '';
    descCell.appendChild(descInput);

    const qtyCell = document.createElement('td');
    const qtyInput = createInput({
      type: 'number',
      name: `items[${currentIndex}][quantity]`,
      classes: 'form-control form-control-sm text-end item-qty',
      step: '0.01',
      min: '0',
    });
    qtyInput.value = data.quantity !== undefined ? data.quantity : '';
    qtyCell.appendChild(qtyInput);

    const daysCell = document.createElement('td');
    const daysInput = createInput({
      type: 'number',
      name: `items[${currentIndex}][rental_days]`,
      classes: 'form-control form-control-sm text-end rental-days',
      step: '1',
      min: '1',
    });
    daysInput.value = data.rental_days && Number(data.rental_days) > 0 ? data.rental_days : 1;
    daysCell.appendChild(daysInput);

    const priceCell = document.createElement('td');
    const priceInput = createInput({
      type: 'number',
      name: `items[${currentIndex}][unit_price]`,
      classes: 'form-control form-control-sm text-end item-price',
      step: '0.01',
      min: '0',
    });
    priceInput.value = data.unit_price !== undefined ? data.unit_price : '';
    priceCell.appendChild(priceInput);

    const totalCell = document.createElement('td');
    const totalInput = createInput({
      type: 'number',
      name: `items[${currentIndex}][line_total]`,
      classes: 'form-control form-control-sm text-end',
      step: '0.01',
      readOnly: true,
    });
    totalInput.value = data.line_total !== undefined ? Number(data.line_total).toFixed(2) : '';
    totalCell.appendChild(totalInput);

    const deleteCell = document.createElement('td');
    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'btn btn-danger btn-sm';
    deleteBtn.textContent = 'Eliminar';
    deleteBtn.addEventListener('click', () => {
      row.remove();
      recalcDocumentTotals();
    });
    deleteCell.appendChild(deleteBtn);

    [descCell, qtyCell, daysCell, priceCell, totalCell, deleteCell].forEach(cell => row.appendChild(cell));
    tbody.appendChild(row);

    qtyInput.addEventListener('input', recalcDocumentTotals);
    priceInput.addEventListener('input', recalcDocumentTotals);
    daysInput.addEventListener('input', recalcDocumentTotals);
    descInput.addEventListener('change', () => {
      fillProductPrice(descInput, priceInput);
      recalcDocumentTotals();
    });
    descInput.addEventListener('blur', () => {
      fillProductPrice(descInput, priceInput);
      recalcDocumentTotals();
    });
  }

  function fillProductPrice(descInput, priceInput) {
    const value = descInput.value.trim().toLowerCase();
    if (!value) return;
    const match = PRODUCT_LIST.find(prod => (prod.name || '').toLowerCase() === value);
    if (match && priceInput) {
      priceInput.value = parseFloat(match.price).toFixed(2);
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

  function recalcDocumentTotals() {
    recalculateTotals();
  }

  document.addEventListener('input', function (e) {
    if (!e.target.classList.contains('item-name')) return;
    const input = e.target;
    const value = input.value.trim();
    if (!value) return;
    const product = PRODUCT_LIST.find(p => p.name === value);
    if (!product) return;
    const row = input.closest('tr');
    if (!row) return;
    const priceInput = row.querySelector('.item-price') || row.querySelector("input[name*='[unit_price]']");
    if (priceInput) {
      priceInput.value = product.price;
    }
    recalcDocumentTotals();
  });

  document.addEventListener('DOMContentLoaded', () => {
    if (Array.isArray(EXISTING_ITEMS) && EXISTING_ITEMS.length > 0) {
      EXISTING_ITEMS.forEach(item => addItemRow(item));
    } else {
      addItemRow();
    }
    recalcDocumentTotals();
  });
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
