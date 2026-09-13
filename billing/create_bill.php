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
        $cgst_rate = floatval($_POST['cgst_rate']);
        $sgst_rate = floatval($_POST['sgst_rate']);
        $cgst_amount = floatval($_POST['cgst_amount']);
        $sgst_amount = floatval($_POST['sgst_amount']);
        $grand_total = floatval($_POST['grand_total']);
        $payment_status = sanitize($_POST['payment_status']);
        $payment_received = floatval($_POST['payment_received']);
        $notes = sanitize($_POST['notes']);
        
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
        
        // Insert bill items
        $products = $_POST['product_name'];
        $hsn_codes = $_POST['hsn_code'];
        $quantities = $_POST['quantity'];
        $units = $_POST['unit'];
        $prices = $_POST['price'];
        $item_cgst_rates = $_POST['item_cgst_rate'] ?? [];
        $item_sgst_rates = $_POST['item_sgst_rate'] ?? [];
        
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
            $icgst_rate = isset($item_cgst_rates[$i]) ? floatval($item_cgst_rates[$i]) : 2.50;
            $isgst_rate = isset($item_sgst_rates[$i]) ? floatval($item_sgst_rates[$i]) : 2.50;
            
            $taxable = $quantity * $price;
            $icgst_amount = ($taxable * $icgst_rate) / 100;
            $isgst_amount = ($taxable * $isgst_rate) / 100;
            $total = $taxable + $icgst_amount + $isgst_amount;
            
            $stmt->bind_param("issdsdddddd", $bill_id, $product, $hsn, $quantity, $unit, $price, $icgst_rate, $icgst_amount, $isgst_rate, $isgst_amount, $total);
            
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
            <h2>Create New Bill</h2>
            <div style="background: #dbeafe; padding: 10px 15px; border-radius: 6px; border-left: 4px solid #2563eb;">
                <strong>📄 One Page Invoice:</strong> Maximum 8 items | Product name max 50 characters
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
                    <h3>Bill Information</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bill_no">Bill No. *</label>
                            <input type="text" id="bill_no" name="bill_no" value="<?php echo $billNo; ?>" readonly required>
                        </div>
                        <div class="form-group">
                            <label for="bill_date">Bill Date *</label>
                            <input type="date" id="bill_date" name="bill_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_name">Customer Name *</label>
                            <input type="text" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="form-group">
                            <label for="customer_phone">Customer Phone</label>
                            <input type="text" id="customer_phone" name="customer_phone">
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
                            <small style="color: #6b7280;">Format: XXXX-XXXX-XXXX</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_address">Billing Address *</label>
                        <textarea id="customer_address" name="customer_address" rows="2" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="is_shipping_same" name="is_shipping_same" value="1" checked onchange="toggleShippingAddress()">
                            <span>Shipping Address is same as Billing Address</span>
                        </label>
                    </div>
                    
                    <div class="form-group" id="shipping_address_group" style="display: none;">
                        <label for="shipping_address">Shipping Address *</label>
                        <textarea id="shipping_address" name="shipping_address" rows="2"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Bill Items</h3>
                    <button type="button" class="btn btn-secondary" onclick="addItem()">+ Add Item</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th width="24%">Product Name</th>
                                    <th width="10%">HSN/SAC Code</th>
                                    <th width="8%">Qty</th>
                                    <th width="8%">Unit</th>
                                    <th width="10%">Price</th>
                                    <th width="9%">CGST %</th>
                                    <th width="9%">SGST %</th>
                                    <th width="14%">Total (Incl. Tax)</th>
                                    <th width="8%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr class="item-row">
                                    <td><input type="text" name="product_name[]" class="form-control" maxlength="50" placeholder="Max 50 characters" required></td>
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
                                        </select>
                                    </td>
                                    <td><input type="number" name="price[]" class="form-control price" step="0.01" min="0.01" value="0" required></td>
                                    <td><input type="number" name="item_cgst_rate[]" class="form-control item-cgst-rate" step="0.01" min="0" max="100" value="2.50"></td>
                                    <td><input type="number" name="item_sgst_rate[]" class="form-control item-sgst-rate" step="0.01" min="0" max="100" value="2.50"></td>
                                    <td><input type="text" class="form-control total" value="0.00" readonly></td>
                                    <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">×</button></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="7" class="text-right"><strong>Subtotal (Excl. Tax):</strong></td>
                                    <td><input type="text" id="subtotal_display" class="form-control" readonly style="font-weight: bold;"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Tax & Payment Details</h3>
                </div>
                <div class="card-body">
                    <div class="tax-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Subtotal</label>
                                <input type="number" id="subtotal" name="subtotal" step="0.01" value="0.00" readonly style="background: #f3f4f6; font-weight: bold;">
                            </div>
                            <div class="form-group">
                                <label for="cgst_rate">CGST Rate (%) *</label>
                                <input type="number" id="cgst_rate" name="cgst_rate" step="0.01" min="0" max="100" value="2.50" onchange="calculateTax()" style="background: #fef3c7;">
                            </div>
                            <div class="form-group">
                                <label>CGST Amount</label>
                                <input type="number" id="cgst_amount" name="cgst_amount" step="0.01" value="0.00" readonly style="background: #f3f4f6;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>&nbsp;</label>
                            </div>
                            <div class="form-group">
                                <label for="sgst_rate">SGST Rate (%) *</label>
                                <input type="number" id="sgst_rate" name="sgst_rate" step="0.01" min="0" max="100" value="2.50" onchange="calculateTax()" style="background: #fef3c7;">
                            </div>
                            <div class="form-group">
                                <label>SGST Amount</label>
                                <input type="number" id="sgst_amount" name="sgst_amount" step="0.01" value="0.00" readonly style="background: #f3f4f6;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="grand_total">Grand Total *</label>
                                <input type="number" id="grand_total" name="grand_total" step="0.01" value="0.00" readonly required style="background: #dbeafe; font-weight: bold; font-size: 18px;">
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
                                <label for="payment_received">Payment Received</label>
                                <input type="number" id="payment_received" name="payment_received" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label id="amount_in_words" style="color: #059669; font-weight: bold; font-size: 14px;">Amount in Words: Zero Rupees Only</label>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="2"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="previewBill()">Preview Bill</button>
                        <button type="submit" name="save_bill" class="btn btn-primary">Save Bill</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        // Toggle Customer ID Field (GSTIN or Aadhaar)
        function toggleCustomerIDField() {
            const idType = document.getElementById('customer_id_type').value;
            const gstinGroup = document.getElementById('gstin_field_group');
            const aadhaarGroup = document.getElementById('aadhaar_field_group');
            const gstinInput = document.getElementById('customer_gstin');
            const aadhaarInput = document.getElementById('customer_aadhaar');
            
            // Hide both initially
            gstinGroup.style.display = 'none';
            aadhaarGroup.style.display = 'none';
            gstinInput.value = '';
            aadhaarInput.value = '';
            
            // Show selected field
            if (idType === 'gstin') {
                gstinGroup.style.display = 'block';
            } else if (idType === 'aadhaar') {
                aadhaarGroup.style.display = 'block';
            }
        }
        
        // Format Aadhaar number with dashes
        function formatAadhaar(input) {
            let value = input.value.replace(/\D/g, ''); // Remove non-digits
            if (value.length > 12) value = value.slice(0, 12); // Max 12 digits
            
            // Add dashes: XXXX-XXXX-XXXX
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i === 4 || i === 8) {
                    formatted += '-';
                }
                formatted += value[i];
            }
            input.value = formatted;
        }
        
        // Toggle shipping address visibility
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
        
        // Calculate on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Payment status change handler
document.getElementById('payment_status').addEventListener('change', function() {
    const paymentStatus = this.value;
    const grandTotal = parseFloat(document.getElementById('grand_total').value) || 0;
    const paymentReceivedField = document.getElementById('payment_received');
    
    if (paymentStatus === 'Paid') {
        // Automatically fill grand total when Paid is selected
        paymentReceivedField.value = grandTotal.toFixed(2);
    } else if (paymentStatus === 'Pending') {
        // Clear payment received when Pending
        paymentReceivedField.value = '0';
    }
    // For Partial, user will manually enter the amount
});
            attachEventListeners();
            
            // Add event listeners for global tax rate changes
            document.getElementById('cgst_rate').addEventListener('input', updateAllItemTaxRates);
            document.getElementById('sgst_rate').addEventListener('input', updateAllItemTaxRates);
        });
        
        function attachEventListeners() {
            document.querySelectorAll('.quantity, .price, .item-cgst-rate, .item-sgst-rate').forEach(input => {
                input.addEventListener('input', function() {
                    calculateTotal(this);
                });
                input.addEventListener('change', function() {
                    calculateTotal(this);
                });
            });
        }
        
        let isUpdatingFromGlobal = false;

        function updateAllItemTaxRates() {
            isUpdatingFromGlobal = true;
            const globalCgst = document.getElementById('cgst_rate').value || '2.50';
            const globalSgst = document.getElementById('sgst_rate').value || '2.50';
            document.querySelectorAll('.item-cgst-rate').forEach(input => {
                input.value = globalCgst;
            });
            document.querySelectorAll('.item-sgst-rate').forEach(input => {
                input.value = globalSgst;
            });
            document.querySelectorAll('.item-row').forEach(row => {
                const qtyInput = row.querySelector('.quantity');
                if (qtyInput) calculateTotal(qtyInput);
            });
            isUpdatingFromGlobal = false;
        }
        
        function addItem() {
            const tbody = document.getElementById('itemsBody');
            
            // Check maximum items limit (15 items for one page)
            if (tbody.rows.length >= 15) {
                alert('Maximum 15 items allowed for single page invoice!');
                return;
            }
            
            const cgstDefault = document.getElementById('cgst_rate').value || '2.50';
            const sgstDefault = document.getElementById('sgst_rate').value || '2.50';
            
            const newRow = document.createElement('tr');
            newRow.className = 'item-row';
            newRow.innerHTML = `
                <td><input type="text" name="product_name[]" class="form-control" maxlength="50" placeholder="Max 50 characters" required></td>
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
                    </select>
                </td>
                <td><input type="number" name="price[]" class="form-control price" step="0.01" min="0.01" value="0" required></td>
                <td><input type="number" name="item_cgst_rate[]" class="form-control item-cgst-rate" step="0.01" min="0" max="100" value="${cgstDefault}"></td>
                <td><input type="number" name="item_sgst_rate[]" class="form-control item-sgst-rate" step="0.01" min="0" max="100" value="${sgstDefault}"></td>
                <td><input type="text" class="form-control total" value="0.00" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">×</button></td>
            `;
            tbody.appendChild(newRow);
            attachEventListeners();
        }
        
        function removeItem(btn) {
            const tbody = document.getElementById('itemsBody');
            if (tbody.rows.length > 1) {
                btn.closest('tr').remove();
                calculateSubtotal();
            } else {
                alert('At least one item is required!');
            }
        }
        
        function calculateTotal(input) {
            const row = input.closest('tr');
            const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
            const price = parseFloat(row.querySelector('.price').value) || 0;
            const cgstRate = parseFloat(row.querySelector('.item-cgst-rate').value) || 0;
            const sgstRate = parseFloat(row.querySelector('.item-sgst-rate').value) || 0;
            
            const taxable = quantity * price;
            const cgstAmt = (taxable * cgstRate) / 100;
            const sgstAmt = (taxable * sgstRate) / 100;
            const total = taxable + cgstAmt + sgstAmt;
            
            row.querySelector('.total').value = total.toFixed(2);
            calculateSubtotal();
        }
        
        function calculateSubtotal() {
            let subtotal = 0;
            let totalCgst = 0;
            let totalSgst = 0;
            let firstCgstRate = 2.50;
            let firstSgstRate = 2.50;
            let isFirstRow = true;
            
            document.querySelectorAll('.item-row').forEach(row => {
                const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
                const price = parseFloat(row.querySelector('.price').value) || 0;
                const cgstRate = parseFloat(row.querySelector('.item-cgst-rate').value) || 0;
                const sgstRate = parseFloat(row.querySelector('.item-sgst-rate').value) || 0;
                
                if (isFirstRow) {
                    firstCgstRate = cgstRate;
                    firstSgstRate = sgstRate;
                    isFirstRow = false;
                }

                const taxable = quantity * price;
                const cgstAmt = (taxable * cgstRate) / 100;
                const sgstAmt = (taxable * sgstRate) / 100;
                
                subtotal += taxable;
                totalCgst += cgstAmt;
                totalSgst += sgstAmt;
            });
            
            const grandTotal = subtotal + totalCgst + totalSgst;
            
            document.getElementById('subtotal').value = subtotal.toFixed(2);
            document.getElementById('subtotal_display').value = '₹' + subtotal.toFixed(2);
            document.getElementById('cgst_amount').value = totalCgst.toFixed(2);
            document.getElementById('sgst_amount').value = totalSgst.toFixed(2);
            document.getElementById('grand_total').value = grandTotal.toFixed(2);

            // Keep global CGST & SGST percentage input boxes in sync with item rows
            if (!isUpdatingFromGlobal) {
                const effectiveCgstRate = subtotal > 0 ? ((totalCgst / subtotal) * 100) : firstCgstRate;
                const effectiveSgstRate = subtotal > 0 ? ((totalSgst / subtotal) * 100) : firstSgstRate;
                document.getElementById('cgst_rate').value = effectiveCgstRate.toFixed(2);
                document.getElementById('sgst_rate').value = effectiveSgstRate.toFixed(2);
            }
            
            updateAmountInWords(grandTotal);
        }
        
        function calculateTax() {
            calculateSubtotal();
        }
        
        function updateAmountInWords(amount) {
            if (amount <= 0) {
                document.getElementById('amount_in_words').textContent = 'Amount in Words: Zero Rupees Only';
                return;
            }
            
            fetch('number_to_words.php?amount=' + amount)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('File not found');
                    }
                    return response.text();
                })
                .then(words => {
                    // Check if response is HTML error page
                    if (words.includes('DOCTYPE') || words.includes('404')) {
                        throw new Error('File not found');
                    }
                    document.getElementById('amount_in_words').textContent = 'Amount in Words: ' + words;
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Fallback: Show just the number
                    document.getElementById('amount_in_words').textContent = 'Amount in Words: ₹' + amount.toFixed(2);
                    document.getElementById('amount_in_words').style.color = '#dc2626';
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
                alert('Please add items with valid quantities and prices!');
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