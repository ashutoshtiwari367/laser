<?php
// create_bill.php
require_once 'config.php';
checkLogin();

$message = '';
$messageType = '';
$billNo = generateBillNumber();
$companySettings = getCompanySettings();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_bill'])) {
    $conn = getDBConnection();
    
    try {
        // Get and validate form data
        $bill_no = sanitize($_POST['bill_no']);
        $bill_date = sanitize($_POST['bill_date']);
        $customer_name = sanitize($_POST['customer_name']);
        $customer_phone = sanitize($_POST['customer_phone']);
        $customer_id_type = sanitize($_POST['customer_id_type']);
        $customer_gstin = ($customer_id_type == 'gstin') ? sanitize($_POST['customer_gstin']) : '';
        $customer_aadhaar = ($customer_id_type == 'aadhaar') ? sanitize($_POST['customer_aadhaar']) : '';
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
        
        // Calculate effective average CGST/SGST rates for main bills record
        $cgst_rate = ($subtotal > 0) ? ($cgst_amount / $subtotal) * 100 : 0;
        $sgst_rate = ($subtotal > 0) ? ($sgst_amount / $subtotal) * 100 : 0;
        
        // Validate required fields
        if (empty($bill_no) || empty($bill_date) || empty($customer_name) || $grand_total <= 0) {
            throw new Exception('Please fill all required fields');
        }
        
        // Validate items
        if (!isset($_POST['product_name']) || empty($_POST['product_name'][0])) {
            throw new Exception('Please add at least one item');
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Insert bill
        $stmt = $conn->prepare("INSERT INTO bills (bill_no, bill_date, customer_name, customer_phone, customer_id_type, customer_gstin, customer_aadhaar, customer_address, shipping_address, is_shipping_same, subtotal, cgst_rate, cgst_amount, sgst_rate, sgst_amount, grand_total, payment_status, payment_received, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("sssssssssiddddddsdsi", $bill_no, $bill_date, $customer_name, $customer_phone, $customer_id_type, $customer_gstin, $customer_aadhaar, $customer_address, $shipping_address, $is_shipping_same, $subtotal, $cgst_rate, $cgst_amount, $sgst_rate, $sgst_amount, $grand_total, $payment_status, $payment_received, $notes, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $bill_id = $conn->insert_id;
        
        if ($bill_id <= 0) {
            throw new Exception('Failed to get bill ID');
        }
        
        // Insert bill items with per-item tax
        $products = $_POST['product_name'];
        $hsn_codes = $_POST['hsn_code'];
        $quantities = $_POST['quantity'];
        $units = $_POST['unit'];
        $prices = $_POST['price'];
        $cgst_rates = $_POST['cgst_rate_item'] ?? [];
        $sgst_rates = $_POST['sgst_rate_item'] ?? [];
        
        $stmt = $conn->prepare("INSERT INTO bill_items (bill_id, product_name, hsn_code, quantity, unit, price, cgst_rate, cgst_amount, sgst_rate, sgst_amount, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception('Prepare items failed: ' . $conn->error);
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
            
            $stmt->bind_param("issdsdddddd", $bill_id, $product, $hsn, $quantity, $unit, $price, $item_cgst_rate, $item_cgst_amount, $item_sgst_rate, $item_sgst_amount, $total);
            
            if (!$stmt->execute()) {
                throw new Exception('Execute item failed: ' . $stmt->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $message = 'Bill saved successfully! <a href="preview_bill.php?id=' . $bill_id . '" style="color: white; text-decoration: underline;">View Bill</a>';
        $messageType = 'success';
        
        // Generate new bill number for next bill
        $billNo = generateBillNumber();
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
    
    if (isset($conn)) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Bill - Billing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div>
                <h2>Create New Bill</h2>
                <p>Generate GST compliant tax invoice with itemized tax calculation</p>
            </div>
            <div style="background: #eff6ff; padding: 8px 14px; border-radius: 8px; border: 1px solid #bfdbfe; color: #1e40af; font-size: 13px;">
                ⚡ <strong>Single Page Invoice:</strong> Max 8 items allowed per invoice
            </div>
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
                            <label for="bill_no">Bill No. *</label>
                            <input type="text" id="bill_no" name="bill_no" value="<?php echo $billNo; ?>" readonly style="background-color: #f8fafc; font-weight: 600;" required>
                        </div>
                        <div class="form-group">
                            <label for="bill_date">Bill Date *</label>
                            <input type="date" id="bill_date" name="bill_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_name">Customer Name *</label>
                            <input type="text" id="customer_name" name="customer_name" placeholder="Enter Customer / Company Name" required>
                        </div>
                        <div class="form-group">
                            <label for="customer_phone">Customer Phone</label>
                            <input type="text" id="customer_phone" name="customer_phone" placeholder="e.g. +91 9876543210">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_id_type">Customer ID Type</label>
                            <select id="customer_id_type" name="customer_id_type" onchange="toggleCustomerIDField()">
                                <option value="none">None</option>
                                <option value="gstin">GSTIN Number</option>
                                <option value="aadhaar">Aadhaar Card Number</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row" id="gstin_field_group" style="display: none;">
                        <div class="form-group">
                            <label for="customer_gstin">Customer GSTIN</label>
                            <input type="text" id="customer_gstin" name="customer_gstin" placeholder="e.g., 09XXXXX1234X1ZX" maxlength="15">
                        </div>
                    </div>
                    
                    <div class="form-row" id="aadhaar_field_group" style="display: none;">
                        <div class="form-group">
                            <label for="customer_aadhaar">Customer Aadhaar Number</label>
                            <input type="text" id="customer_aadhaar" name="customer_aadhaar" placeholder="XXXX-XXXX-XXXX" maxlength="14" onkeyup="formatAadhaar(this)">
                            <small style="color: #64748b;">Format: XXXX-XXXX-XXXX</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_address">Billing Address *</label>
                        <textarea id="customer_address" name="customer_address" rows="2" placeholder="Full billing address" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 500;">
                            <input type="checkbox" id="is_shipping_same" name="is_shipping_same" value="1" checked onchange="toggleShippingAddress()">
                            <span>Shipping Address is same as Billing Address</span>
                        </label>
                    </div>
                    
                    <div class="form-group" id="shipping_address_group" style="display: none;">
                        <label for="shipping_address">Shipping Address *</label>
                        <textarea id="shipping_address" name="shipping_address" rows="2" placeholder="Full shipping address"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Add Line Items (Item-Wise GST)</h3>
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
                                <tr class="item-row">
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
                                </tr>
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
                                <input type="number" id="subtotal" name="subtotal" step="0.01" value="0.00" readonly style="background: #f8fafc; font-weight: bold;">
                            </div>
                            <div class="form-group">
                                <label>Total CGST (₹)</label>
                                <input type="number" id="cgst_amount" name="cgst_amount" step="0.01" value="0.00" readonly style="background: #fef3c7; font-weight: 600; color: #92400e;">
                            </div>
                            <div class="form-group">
                                <label>Total SGST (₹)</label>
                                <input type="number" id="sgst_amount" name="sgst_amount" step="0.01" value="0.00" readonly style="background: #fef3c7; font-weight: 600; color: #92400e;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="grand_total">Grand Total (₹) *</label>
                                <input type="number" id="grand_total" name="grand_total" step="0.01" value="0.00" readonly required style="background: #eff6ff; font-weight: 700; font-size: 20px; color: #1d4ed8;">
                            </div>
                            <div class="form-group">
                                <label for="payment_status">Payment Status *</label>
                                <select id="payment_status" name="payment_status" required>
                                    <option value="Pending">Pending</option>
                                    <option value="Partial">Partial</option>
                                    <option value="Paid">Paid</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="payment_received">Payment Received (₹)</label>
                                <input type="number" id="payment_received" name="payment_received" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="background:#f0fdf4; padding:12px 16px; border-radius:8px; border:1px solid #bbf7d0; margin-top:10px;">
                        <label id="amount_in_words" style="color: #15803d; font-weight: 700; font-size: 14px; margin:0;">Amount in Words: Zero Rupees Only</label>
                    </div>
                    
                    <div class="form-group" style="margin-top:16px;">
                        <label for="notes">Notes / Internal Remarks</label>
                        <textarea id="notes" name="notes" rows="2" placeholder="Optional notes for this bill..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="previewBill()">👁️ Preview Invoice</button>
                        <button type="submit" name="save_bill" class="btn btn-primary">💾 Save Bill</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        function toggleCustomerIDField() {
            const idType = document.getElementById('customer_id_type').value;
            const gstinGroup = document.getElementById('gstin_field_group');
            const aadhaarGroup = document.getElementById('aadhaar_field_group');
            const gstinInput = document.getElementById('customer_gstin');
            const aadhaarInput = document.getElementById('customer_aadhaar');
            
            gstinGroup.style.display = 'none';
            aadhaarGroup.style.display = 'none';
            gstinInput.value = '';
            aadhaarInput.value = '';
            
            if (idType === 'gstin') {
                gstinGroup.style.display = 'block';
            } else if (idType === 'aadhaar') {
                aadhaarGroup.style.display = 'block';
            }
        }
        
        function formatAadhaar(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 12) value = value.slice(0, 12);
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i === 4 || i === 8) {
                    formatted += '-';
                }
                formatted += value[i];
            }
            input.value = formatted;
        }
        
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
        
        function previewBill() {
            const form = document.getElementById('billForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const grandTotal = parseFloat(document.getElementById('grand_total').value);
            if (grandTotal <= 0) {
                alert('Please add valid items with quantities and prices!');
                return;
            }
            
            form.target = '_blank';
            form.action = 'preview_bill.php?preview=1';
            form.submit();
            
            form.target = '';
            form.action = '';
        }
    </script>
</body>
</html>