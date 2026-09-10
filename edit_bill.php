<?php
// edit_bill.php
require_once 'config.php';
checkLogin();

if (!isset($_GET['id'])) {
    header('Location: view_bills.php');
    exit();
}

$billId = intval($_GET['id']);
$conn = getDBConnection();
$message = '';
$messageType = '';
$companySettings = getCompanySettings();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_bill'])) {
    try {
        $bill_date = sanitize($_POST['bill_date']);
        $customer_name = sanitize($_POST['customer_name']);
        $customer_phone = sanitize($_POST['customer_phone']);
        $customer_gstin = sanitize($_POST['customer_gstin']);
        $customer_address = sanitize($_POST['customer_address']);
        $is_shipping_same = isset($_POST['is_shipping_same']) ? 1 : 0;
        $shipping_address = $is_shipping_same ? '' : sanitize($_POST['shipping_address']);
        $subtotal = floatval($_POST['subtotal']);
        $cgst_amount = floatval($_POST['cgst_amount']);
        $sgst_amount = floatval($_POST['sgst_amount']);
        $grand_total = floatval($_POST['grand_total']);
        $payment_status = sanitize($_POST['payment_status']);
        $payment_received = floatval($_POST['payment_received']);
        $notes = sanitize($_POST['notes']);
        
        $cgst_rate = ($subtotal > 0) ? ($cgst_amount / $subtotal) * 100 : 0;
        $sgst_rate = ($subtotal > 0) ? ($sgst_amount / $subtotal) * 100 : 0;
        
        // Validate required fields
        if (empty($bill_date) || empty($customer_name) || $grand_total <= 0) {
            throw new Exception('Please fill all required fields');
        }
        
        // Validate items
        if (!isset($_POST['product_name']) || empty($_POST['product_name'][0])) {
            throw new Exception('Please add at least one item');
        }
        
        $conn->begin_transaction();
        
        // Update bill
        $stmt = $conn->prepare("UPDATE bills SET bill_date=?, customer_name=?, customer_phone=?, customer_gstin=?, customer_address=?, shipping_address=?, is_shipping_same=?, subtotal=?, cgst_rate=?, cgst_amount=?, sgst_rate=?, sgst_amount=?, grand_total=?, payment_status=?, payment_received=?, notes=? WHERE id=?");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("ssssssiddddddsdsi", $bill_date, $customer_name, $customer_phone, $customer_gstin, $customer_address, $shipping_address, $is_shipping_same, $subtotal, $cgst_rate, $cgst_amount, $sgst_rate, $sgst_amount, $grand_total, $payment_status, $payment_received, $notes, $billId);
        
        if (!$stmt->execute()) {
            throw new Exception('Update failed: ' . $stmt->error);
        }
        
        // Delete existing items
        $stmt = $conn->prepare("DELETE FROM bill_items WHERE bill_id = ?");
        if (!$stmt) {
            throw new Exception('Delete prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("i", $billId);
        if (!$stmt->execute()) {
            throw new Exception('Delete failed: ' . $stmt->error);
        }
        
        // Insert updated items with per-item GST
        $products = $_POST['product_name'];
        $hsn_codes = $_POST['hsn_code'];
        $quantities = $_POST['quantity'];
        $units = $_POST['unit'];
        $prices = $_POST['price'];
        $cgst_rates = $_POST['cgst_rate_item'] ?? [];
        $sgst_rates = $_POST['sgst_rate_item'] ?? [];
        
        $stmt = $conn->prepare("INSERT INTO bill_items (bill_id, product_name, hsn_code, quantity, unit, price, cgst_rate, cgst_amount, sgst_rate, sgst_amount, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception('Insert prepare failed: ' . $conn->error);
        }
        
        for ($i = 0; $i < count($products); $i++) {
            if (empty($products[$i])) continue;
            
            $product = sanitize($products[$i]);
            $hsn = sanitize($hsn_codes[$i]);
            $quantity = floatval($quantities[$i]);
            $unit = sanitize($units[$i]);
            $price = floatval($prices[$i]);
            $item_cgst_rate = floatval($cgst_rates[$i] ?? 0);
            $item_sgst_rate = floatval($sgst_rates[$i] ?? 0);
            
            $taxable = $quantity * $price;
            $item_cgst_amount = ($taxable * $item_cgst_rate) / 100;
            $item_sgst_amount = ($taxable * $item_sgst_rate) / 100;
            $total = $taxable + $item_cgst_amount + $item_sgst_amount;
            
            $stmt->bind_param("issdsdddddd", $billId, $product, $hsn, $quantity, $unit, $price, $item_cgst_rate, $item_cgst_amount, $item_sgst_rate, $item_sgst_amount, $total);
            
            if (!$stmt->execute()) {
                throw new Exception('Insert item failed: ' . $stmt->error);
            }
        }
        
        $conn->commit();
        $message = 'Bill updated successfully! <a href="preview_bill.php?id=' . $billId . '" style="color: white; text-decoration: underline;">View Bill</a>';
        $messageType = 'success';
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get bill details
$stmt = $conn->prepare("SELECT * FROM bills WHERE id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $billId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<script>alert("Bill not found!"); window.location.href="view_bills.php";</script>';
    exit();
}

$bill = $result->fetch_assoc();

// Get bill items
$stmt = $conn->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $billId);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    if (!isset($row['hsn_code'])) $row['hsn_code'] = '';
    if (!isset($row['unit'])) $row['unit'] = 'Qty';
    if (!isset($row['cgst_rate'])) $row['cgst_rate'] = $bill['cgst_rate'] ?? 2.5;
    if (!isset($row['sgst_rate'])) $row['sgst_rate'] = $bill['sgst_rate'] ?? 2.5;
    $items[] = $row;
}

if (empty($items)) {
    $items[] = [
        'product_name' => '',
        'hsn_code' => '',
        'quantity' => 1,
        'unit' => 'Qty',
        'price' => 0,
        'cgst_rate' => 2.5,
        'sgst_rate' => 2.5,
        'total' => 0
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bill - <?php echo htmlspecialchars($bill['bill_no']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div>
                <h2>Edit Bill - <?php echo htmlspecialchars($bill['bill_no']); ?></h2>
                <p>Modify items, customer info, or payment status</p>
            </div>
            <a href="view_bills.php" class="btn btn-secondary">← Back to Bills List</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="billForm">
            <div class="card">
                <div class="card-header">
                    <h3>Customer & Bill Information</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Bill No.</label>
                            <input type="text" value="<?php echo htmlspecialchars($bill['bill_no']); ?>" readonly style="background-color: #f8fafc; font-weight:600;">
                        </div>
                        <div class="form-group">
                            <label for="bill_date">Bill Date *</label>
                            <input type="date" id="bill_date" name="bill_date" value="<?php echo htmlspecialchars($bill['bill_date']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_name">Customer Name *</label>
                            <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($bill['customer_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="customer_phone">Customer Phone</label>
                            <input type="text" id="customer_phone" name="customer_phone" value="<?php echo htmlspecialchars($bill['customer_phone']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_gstin">Customer GSTIN (Optional)</label>
                            <input type="text" id="customer_gstin" name="customer_gstin" value="<?php echo htmlspecialchars($bill['customer_gstin']); ?>" placeholder="e.g., 09XXXXX1234X1ZX" maxlength="15">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_address">Billing Address *</label>
                        <textarea id="customer_address" name="customer_address" rows="2" required><?php echo htmlspecialchars($bill['customer_address']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight:500;">
                            <input type="checkbox" id="is_shipping_same" name="is_shipping_same" value="1" <?php echo ($bill['is_shipping_same'] == 1) ? 'checked' : ''; ?> onchange="toggleShippingAddress()">
                            <span>Shipping Address is same as Billing Address</span>
                        </label>
                    </div>
                    
                    <div class="form-group" id="shipping_address_group" style="display: <?php echo ($bill['is_shipping_same'] == 1) ? 'none' : 'block'; ?>;">
                        <label for="shipping_address">Shipping Address *</label>
                        <textarea id="shipping_address" name="shipping_address" rows="2" <?php echo ($bill['is_shipping_same'] != 1) ? 'required' : ''; ?>><?php echo htmlspecialchars($bill['shipping_address']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Bill Line Items (Item-Wise GST)</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addItem()">+ Add Item Row</button>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 22%;">Product / Service Name</th>
                                    <th style="width: 9%;">HSN/SAC</th>
                                    <th style="width: 7%;">Qty</th>
                                    <th style="width: 8%;">Unit</th>
                                    <th style="width: 10%;">Price (₹)</th>
                                    <th style="width: 8%;">CGST %</th>
                                    <th style="width: 8%;">SGST %</th>
                                    <th style="width: 11%;">Taxable (₹)</th>
                                    <th style="width: 12%;">Total Incl. GST</th>
                                    <th style="width: 5%; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <?php foreach ($items as $item): 
                                    $itemCgst = floatval($item['cgst_rate'] ?? 2.5);
                                    $itemSgst = floatval($item['sgst_rate'] ?? 2.5);
                                    $taxable = floatval($item['quantity']) * floatval($item['price']);
                                    $itemCgstAmt = ($taxable * $itemCgst) / 100;
                                    $itemSgstAmt = ($taxable * $itemSgst) / 100;
                                    $itemGstAmount = $itemCgstAmt + $itemSgstAmt;
                                    $itemRowTotal = $taxable + $itemGstAmount;
                                ?>
                                    <tr class="item-row">
                                        <td><input type="text" name="product_name[]" class="form-control" value="<?php echo htmlspecialchars($item['product_name']); ?>" required></td>
                                        <td><input type="text" name="hsn_code[]" class="form-control" value="<?php echo htmlspecialchars($item['hsn_code']); ?>" placeholder="HSN"></td>
                                        <td><input type="number" name="quantity[]" class="form-control quantity" step="0.01" min="0.01" value="<?php echo htmlspecialchars($item['quantity']); ?>" required></td>
                                        <td>
                                            <select name="unit[]" class="form-control">
                                                <option value="Qty" <?php echo $item['unit']=='Qty'?'selected':''; ?>>Qty</option>
                                                <option value="Pcs" <?php echo $item['unit']=='Pcs'?'selected':''; ?>>Pcs</option>
                                                <option value="Kg" <?php echo $item['unit']=='Kg'?'selected':''; ?>>Kg</option>
                                                <option value="Ltr" <?php echo $item['unit']=='Ltr'?'selected':''; ?>>Ltr</option>
                                                <option value="Mtr" <?php echo $item['unit']=='Mtr'?'selected':''; ?>>Mtr</option>
                                                <option value="Box" <?php echo $item['unit']=='Box'?'selected':''; ?>>Box</option>
                                                <option value="Nos" <?php echo $item['unit']=='Nos'?'selected':''; ?>>Nos</option>
                                                <option value="Set" <?php echo $item['unit']=='Set'?'selected':''; ?>>Set</option>
                                            </select>
                                        </td>
                                        <td><input type="number" name="price[]" class="form-control price" step="0.01" min="0" value="<?php echo htmlspecialchars($item['price']); ?>" required></td>
                                        <td><input type="number" name="cgst_rate_item[]" class="form-control cgst-rate" step="0.01" min="0" max="100" value="<?php echo $itemCgst; ?>" placeholder="CGST %" style="text-align:center; font-weight:500;"></td>
                                        <td><input type="number" name="sgst_rate_item[]" class="form-control sgst-rate" step="0.01" min="0" max="100" value="<?php echo $itemSgst; ?>" placeholder="SGST %" style="text-align:center; font-weight:500;"></td>
                                        <td>
                                            <input type="text" class="form-control taxable" value="<?php echo number_format($taxable, 2, '.', ''); ?>" readonly style="background:#f8fafc; font-weight:500;">
                                            <small class="tax-info-badge" style="display:block; font-size:11px; color:#0284c7; margin-top:2px;">GST: ₹<?php echo number_format($itemGstAmount, 2); ?></small>
                                        </td>
                                        <td><input type="text" class="form-control item-total" value="<?php echo number_format($itemRowTotal, 2, '.', ''); ?>" readonly style="background:#eff6ff; font-weight:600; color:#1e40af;"></td>
                                        <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">×</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:#f8fafc;">
                                    <td colspan="7" class="text-right" style="padding:14px; font-weight:600;">Total Taxable Subtotal:</td>
                                    <td colspan="3" style="padding:14px;">
                                        <input type="text" id="subtotal_display" class="form-control" readonly style="font-weight: 700; font-size: 15px; color:#0f172a; width: auto; display: inline-block;">
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Summary & Payment Details</h3>
                </div>
                <div class="card-body">
                    <div class="tax-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Subtotal (Taxable Amount)</label>
                                <input type="number" id="subtotal" name="subtotal" step="0.01" value="<?php echo number_format($bill['subtotal'], 2, '.', ''); ?>" readonly style="background: #f8fafc; font-weight: bold;">
                            </div>
                            <div class="form-group">
                                <label>Total CGST (₹)</label>
                                <input type="number" id="cgst_amount" name="cgst_amount" step="0.01" value="<?php echo number_format($bill['cgst_amount'], 2, '.', ''); ?>" readonly style="background: #fef3c7; font-weight: 600; color: #92400e;">
                            </div>
                            <div class="form-group">
                                <label>Total SGST (₹)</label>
                                <input type="number" id="sgst_amount" name="sgst_amount" step="0.01" value="<?php echo number_format($bill['sgst_amount'], 2, '.', ''); ?>" readonly style="background: #fef3c7; font-weight: 600; color: #92400e;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="grand_total">Grand Total (₹) *</label>
                                <input type="number" id="grand_total" name="grand_total" step="0.01" value="<?php echo htmlspecialchars($bill['grand_total']); ?>" readonly required style="background: #eff6ff; font-weight: 700; font-size: 20px; color: #1d4ed8;">
                            </div>
                            <div class="form-group">
                                <label for="payment_status">Payment Status *</label>
                                <select id="payment_status" name="payment_status" required>
                                    <option value="Pending" <?php echo $bill['payment_status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Partial" <?php echo $bill['payment_status'] == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                                    <option value="Paid" <?php echo $bill['payment_status'] == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="payment_received">Payment Received (₹)</label>
                                <input type="number" id="payment_received" name="payment_received" step="0.01" min="0" value="<?php echo htmlspecialchars($bill['payment_received']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="background:#f0fdf4; padding:12px 16px; border-radius:8px; border:1px solid #bbf7d0; margin-top:10px;">
                        <label id="amount_in_words" style="color: #15803d; font-weight: 700; font-size: 14px; margin:0;">Amount in Words: Loading...</label>
                    </div>
                    
                    <div class="form-group" style="margin-top:16px;">
                        <label for="notes">Notes / Internal Remarks</label>
                        <textarea id="notes" name="notes" rows="2"><?php echo htmlspecialchars($bill['notes']); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_bill" class="btn btn-primary">💾 Update Bill</button>
                        <a href="preview_bill.php?id=<?php echo $billId; ?>" class="btn btn-secondary" target="_blank">👁️ Preview Invoice</a>
                        <a href="view_bills.php" class="btn btn-secondary">❌ Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        function toggleShippingAddress() {
            const checkbox = document.getElementById('is_shipping_same');
            const shippingGroup = document.getElementById('shipping_address_group');
            const shippingAddress = document.getElementById('shipping_address');
            
            if (checkbox.checked) {
                shippingGroup.style.display = 'none';
                shippingAddress.required = false;
                shippingAddress.value = '';
            } else {
                shippingGroup.style.display = 'block';
                shippingAddress.required = true;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            attachRowListeners();
            calculateGrandTotal();
        });
        
        function attachRowListeners() {
            document.querySelectorAll('.quantity, .price, .cgst-rate, .sgst-rate').forEach(input => {
                input.removeEventListener('input', onRowInputChange);
                input.addEventListener('input', onRowInputChange);
            });
        }
        
        function onRowInputChange(e) {
            const row = e.target.closest('tr');
            calculateRow(row);
        }
        
        function calculateRow(row) {
            const qty = parseFloat(row.querySelector('.quantity').value) || 0;
            const price = parseFloat(row.querySelector('.price').value) || 0;
            const cgstRate = parseFloat(row.querySelector('.cgst-rate').value) || 0;
            const sgstRate = parseFloat(row.querySelector('.sgst-rate').value) || 0;
            
            const taxable = qty * price;
            const cgstAmount = (taxable * cgstRate) / 100;
            const sgstAmount = (taxable * sgstRate) / 100;
            const totalGst = cgstAmount + sgstAmount;
            const rowTotal = taxable + totalGst;
            
            row.querySelector('.taxable').value = taxable.toFixed(2);
            row.querySelector('.tax-info-badge').textContent = 'GST: ₹' + totalGst.toFixed(2);
            row.querySelector('.item-total').value = rowTotal.toFixed(2);
            
            calculateGrandTotal();
        }
        
        function calculateGrandTotal() {
            let totalSubtotal = 0;
            let totalCgst = 0;
            let totalSgst = 0;
            
            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.quantity').value) || 0;
                const price = parseFloat(row.querySelector('.price').value) || 0;
                const cgstRate = parseFloat(row.querySelector('.cgst-rate').value) || 0;
                const sgstRate = parseFloat(row.querySelector('.sgst-rate').value) || 0;
                
                const taxable = qty * price;
                const cgstAmount = (taxable * cgstRate) / 100;
                const sgstAmount = (taxable * sgstRate) / 100;
                
                totalSubtotal += taxable;
                totalCgst += cgstAmount;
                totalSgst += sgstAmount;
            });
            
            const grandTotal = totalSubtotal + totalCgst + totalSgst;
            
            document.getElementById('subtotal').value = totalSubtotal.toFixed(2);
            document.getElementById('subtotal_display').value = '₹' + totalSubtotal.toFixed(2);
            document.getElementById('cgst_amount').value = totalCgst.toFixed(2);
            document.getElementById('sgst_amount').value = totalSgst.toFixed(2);
            document.getElementById('grand_total').value = grandTotal.toFixed(2);
            
            updateAmountInWords(grandTotal);
        }
        
        function addItem() {
            const tbody = document.getElementById('itemsBody');
            if (tbody.rows.length >= 8) {
                alert('Maximum 8 items allowed for single page invoice layout!');
                return;
            }
            
            const newRow = document.createElement('tr');
            newRow.className = 'item-row';
            newRow.innerHTML = `
                <td><input type="text" name="product_name[]" class="form-control" maxlength="50" placeholder="Product name (max 50 chars)" required></td>
                <td><input type="text" name="hsn_code[]" class="form-control" placeholder="HSN"></td>
                <td><input type="number" name="quantity[]" class="form-control quantity" step="0.01" min="0.01" value="1" required></td>
                <td>
                    <select name="unit[]" class="form-control">
                        <option value="Qty">Qty</option>
                        <option value="Pcs">Pcs</option>
                        <option value="Kg">Kg</option>
                        <option value="Ltr">Ltr</option>
                        <option value="Mtr">Mtr</option>
                        <option value="Box">Box</option>
                        <option value="Nos">Nos</option>
                        <option value="Set">Set</option>
                    </select>
                </td>
                <td><input type="number" name="price[]" class="form-control price" step="0.01" min="0" value="0" required></td>
                <td><input type="number" name="cgst_rate_item[]" class="form-control cgst-rate" step="0.01" min="0" max="100" value="2.5" placeholder="CGST %" style="text-align:center; font-weight:500;"></td>
                <td><input type="number" name="sgst_rate_item[]" class="form-control sgst-rate" step="0.01" min="0" max="100" value="2.5" placeholder="SGST %" style="text-align:center; font-weight:500;"></td>
                <td>
                    <input type="text" class="form-control taxable" value="0.00" readonly style="background:#f8fafc; font-weight:500;">
                    <small class="tax-info-badge" style="display:block; font-size:11px; color:#0284c7; margin-top:2px;">GST: ₹0.00</small>
                </td>
                <td><input type="text" class="form-control item-total" value="0.00" readonly style="background:#eff6ff; font-weight:600; color:#1e40af;"></td>
                <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">×</button></td>
            `;
            tbody.appendChild(newRow);
            attachRowListeners();
            calculateRow(newRow);
        }
        
        function removeItem(btn) {
            const tbody = document.getElementById('itemsBody');
            if (tbody.rows.length > 1) {
                btn.closest('tr').remove();
                calculateGrandTotal();
            } else {
                alert('At least one item is required in the invoice!');
            }
        }
        
        function updateAmountInWords(amount) {
            if (amount <= 0) {
                document.getElementById('amount_in_words').textContent = 'Amount in Words: Zero Rupees Only';
                return;
            }
            
            fetch('number_to_words.php?amount=' + amount)
                .then(response => response.text())
                .then(words => {
                    document.getElementById('amount_in_words').textContent = 'Amount in Words: ' + words;
                })
                .catch(error => {
                    document.getElementById('amount_in_words').textContent = 'Amount in Words: ₹' + amount.toFixed(2);
                });
        }
    </script>
</body>
</html>