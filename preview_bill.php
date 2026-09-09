<?php
// preview_bill.php
require_once 'config.php';
checkLogin();

$company = getCompanySettings();
$bill = null;
$items = [];

// Preview mode from form
if (isset($_GET['preview']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $bill = [
        'bill_no' => $_POST['bill_no'],
        'bill_date' => $_POST['bill_date'],
        'customer_name' => $_POST['customer_name'],
        'customer_phone' => $_POST['customer_phone'] ?? '',
        'customer_gstin' => $_POST['customer_gstin'] ?? '',
        'customer_address' => $_POST['customer_address'] ?? '',
        'shipping_address' => $_POST['shipping_address'] ?? '',
        'is_shipping_same' => isset($_POST['is_shipping_same']) ? 1 : 0,
        'subtotal' => $_POST['subtotal'] ?? 0,
        'cgst_rate' => $_POST['cgst_rate'] ?? 2.5,
        'cgst_amount' => $_POST['cgst_amount'] ?? 0,
        'sgst_rate' => $_POST['sgst_rate'] ?? 2.5,
        'sgst_amount' => $_POST['sgst_amount'] ?? 0,
        'grand_total' => $_POST['grand_total'],
        'payment_status' => $_POST['payment_status'],
        'payment_received' => $_POST['payment_received'] ?? 0,
        'notes' => $_POST['notes'] ?? ''
    ];
    
    $products = $_POST['product_name'];
    $hsn_codes = $_POST['hsn_code'] ?? [];
    $quantities = $_POST['quantity'];
    $units = $_POST['unit'] ?? [];
    $prices = $_POST['price'];
    
    for ($i = 0; $i < count($products); $i++) {
        if (!empty($products[$i])) {
            $items[] = [
                'product_name' => $products[$i],
                'hsn_code' => $hsn_codes[$i] ?? '',
                'quantity' => $quantities[$i],
                'unit' => $units[$i] ?? 'Qty',
                'price' => $prices[$i],
                'total' => $quantities[$i] * $prices[$i]
            ];
        }
    }
} 
// View saved bill
elseif (isset($_GET['id'])) {
    $billId = intval($_GET['id']);
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT * FROM bills WHERE id = ?");
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $bill = $result->fetch_assoc();
        
        // Set default tax values if not exist
        if (!isset($bill['subtotal'])) $bill['subtotal'] = $bill['grand_total'];
        if (!isset($bill['cgst_rate'])) $bill['cgst_rate'] = 2.5;
        if (!isset($bill['sgst_rate'])) $bill['sgst_rate'] = 2.5;
        if (!isset($bill['cgst_amount'])) $bill['cgst_amount'] = 0;
        if (!isset($bill['sgst_amount'])) $bill['sgst_amount'] = 0;
        if (!isset($bill['customer_gstin'])) $bill['customer_gstin'] = '';
        if (!isset($bill['shipping_address'])) $bill['shipping_address'] = '';
        if (!isset($bill['is_shipping_same'])) $bill['is_shipping_same'] = 1;
        
        $stmt = $conn->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
        $stmt->bind_param("i", $billId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($item = $result->fetch_assoc()) {
            if (!isset($item['hsn_code'])) $item['hsn_code'] = '';
            if (!isset($item['unit'])) $item['unit'] = 'Qty';
            $items[] = $item;
        }
    }
    
    $conn->close();
}

if (!$bill) {
    echo '<script>alert("Bill not found!"); window.location.href="dashboard.php";</script>';
    exit();
}

$amountInWords = numberToWords($bill['grand_total']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - <?php echo $bill['bill_no']; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        @media print {
            body * { visibility: hidden; }
            .bill-preview, .bill-preview * { visibility: visible; }
            .bill-preview { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
        
        .professional-invoice {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 0;
            border: 2px solid #000;
        }
        
        .invoice-header-section {
            text-align: center;
            padding: 15px;
            border-bottom: 2px solid #000;
            background: #f8f9fa;
        }
        
        .invoice-header-section h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #000;
        }
        
        .company-details-section {
            padding: 20px;
            border-bottom: 2px solid #000;
        }
        
        .company-details-section h2 {
            margin: 0 0 10px 0;
            font-size: 20px;
            font-weight: bold;
            color: #000;
        }
        
        .company-details-section p {
            margin: 3px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .invoice-party-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 2px solid #000;
        }
        
        .bill-to-box, .invoice-details-box {
            padding: 20px;
            border-right: 2px solid #000;
        }
        
        .invoice-details-box {
            border-right: none;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
            color: #000;
        }
        
        .detail-row {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .detail-label {
            font-weight: 600;
            display: inline-block;
            min-width: 120px;
        }
        
        .professional-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }
        
        .professional-table thead {
            background: #f8f9fa;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        .professional-table th {
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 13px;
            border-right: 1px solid #ddd;
        }
        
        .professional-table th:last-child {
            border-right: none;
        }
        
        .professional-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #ddd;
            border-right: 1px solid #ddd;
            font-size: 13px;
        }
        
        .professional-table td:last-child {
            border-right: none;
        }
        
        .professional-table tbody tr:last-child td {
            border-bottom: 2px solid #000;
        }
        
        .text-right-col {
            text-align: right;
        }
        
        .text-center-col {
            text-align: center;
        }
        
        .tax-summary-section {
            padding: 15px 20px;
            border-bottom: 2px solid #000;
        }
        
        .tax-summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .tax-breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tax-breakdown-table th,
        .tax-breakdown-table td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 13px;
        }
        
        .tax-breakdown-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .amounts-table {
            width: 100%;
        }
        
        .amounts-table tr {
            border-bottom: 1px solid #ddd;
        }
        
        .amounts-table td {
            padding: 8px;
            font-size: 14px;
        }
        
        .amounts-table .total-row td {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #000;
            padding-top: 10px;
        }
        
        .amount-in-words-section {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 2px solid #000;
        }
        
        .amount-in-words-section p {
            margin: 0;
            font-size: 14px;
        }
        
        .amount-in-words-section strong {
            font-weight: bold;
        }
        
        .terms-bank-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 2px solid #000;
        }
        
        .terms-box {
            padding: 20px;
            border-right: 2px solid #000;
        }
        
        .bank-box {
            padding: 20px;
        }
        
        .signature-section {
            padding: 40px 20px 20px;
            text-align: right;
            position: relative;
        }
        
        .signature-image {
            position: absolute;
            right: 20px;
            bottom: 40px;
            max-width: 200px;
            max-height: 80px;
            opacity: 0.8;
        }
        
        .signature-line {
            margin-top: 60px;
            font-weight: bold;
            position: relative;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header no-print">
            <h2>Tax Invoice Preview</h2>
            <div>
                <a href="download_pdf.php?id=<?php echo $_GET['id'] ?? ''; ?>" class="btn btn-primary" target="_blank">📄 Download PDF</a>
                <!-- <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Invoice</button> -->
                <?php if (isset($_GET['id'])): ?>
                    <!-- <a href="export_bill.php?id=<?php echo $_GET['id']; ?>" class="btn btn-success">📥 Export to Excel</a> -->
                    <a href="edit_bill.php?id=<?php echo $_GET['id']; ?>" class="btn btn-secondary">✏️ Edit Bill</a>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>
        
        <div class="bill-preview professional-invoice">
            <!-- Header: Tax Invoice -->
            <div class="invoice-header-section">
                <h1>Tax Invoice</h1>
            </div>
            
                        <!-- Company Details -->
                <div class="company-details-container"
                    style="display: flex; justify-content: space-between; align-items: center; width: 100%; border-bottom: 2px solid #000;">

                    <!-- Left Side: Company Details -->
                    <div class="company-details-section">
                        <h2 style="margin: 0;">LASEREDGE MEDTECH</h2>
                        <p style="margin: 5px 0;">Block -C1, House No-175 Indira Nagar Kanpur - 208026</p>
                        <p style="margin: 5px 0;">
                            <strong>Phone no.:</strong> +917618037434, +918090938659 <br>
                            <strong>Email:</strong> laseredgemedtech@gmail.com
                            <br><strong>GSTN:</strong>  09BXCPK2300M1ZL
                        </p>
                    </div>

                    <!-- Right Side: Company Logo -->
                    <div class="company-logo" style="text-align: right; ">
                        <img src="logolaseredgemedtech-removebg-preview.webp" alt="Company Logo"
                            style="width: 250px; height: auto;">
                    </div>

                </div>
            
            

            <!-- Bill To and Invoice Details -->
            <div class="invoice-party-section">
                <div class="bill-to-box">
                    <div class="section-title">Bill To</div>
                    <p style="margin: 10px 0; font-weight: bold; font-size: 15px;"><?php echo strtoupper($bill['customer_name']); ?></p>
                    <?php if (!empty($bill['customer_address'])): ?>
                        <p style="margin: 5px 0;"><strong>Address: </strong><?php echo $bill['customer_address']; ?></p>
                    <?php endif; ?>
                    <?php if (!empty($bill['customer_phone'])): ?>
                        <p style="margin: 5px 0;"><strong>Phone:</strong> <?php echo $bill['customer_phone']; ?></p>
                    <?php endif; ?>
                    <?php 
                    $id_type = $bill['customer_id_type'] ?? 'none';
                    if ($id_type == 'gstin' && !empty($bill['customer_gstin'])): 
                    ?>
                        <p style="margin: 5px 0;"><strong>GSTIN:</strong> <?php echo $bill['customer_gstin']; ?></p>
                    <?php elseif ($id_type == 'aadhaar' && !empty($bill['customer_aadhaar'])): ?>
                        <p style="margin: 5px 0;"><strong>Aadhaar:</strong> <?php echo $bill['customer_aadhaar']; ?></p>
                    <?php endif; ?>
                  
                    
                  
                </div>
                
                <div class="invoice-details-box">
                    <div class="section-title">Invoice Details</div>
                    <div class="detail-row">
                        <span class="detail-label">Invoice No.:</span>
                        <strong><?php echo $bill['bill_no']; ?></strong>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <strong><?php echo date('d-m-Y', strtotime($bill['bill_date'])); ?></strong>
                    </div>
                    <div class="detail-row">
                         <?php if (!empty($bill['shipping_address']) && $bill['is_shipping_same'] != 1): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 2px dashed #ddd;">
                            <div class="section-title">Ship To</div>
                            <p style="margin: 5px 0;"><?php echo nl2br($bill['shipping_address']); ?></p>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Items Table -->
            <table class="professional-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">S.NO.</th>
                        <th style="width: 32%;">PARTICULARS</th>
                        <th style="width: 10%;">HSN</th>
                        <th style="width: 8%;">QTY</th>
                        <th style="width: 12%;">PRICE</th>
                        <th style="width: 12%;">Taxable Amount</th>
                        <th style="width: 9%;">CGST</th>
                        <th style="width: 9%;">SGST</th>
                        <th style="width: 12%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sno = 1;
                    $totalQty = 0;
                    $totalCgstSum = 0;
                    $totalSgstSum = 0;
                    foreach ($items as $item): 
                        $itemTaxable = floatval($item['quantity']) * floatval($item['price']);
                        $itemCgstRate = floatval($item['cgst_rate'] ?? $bill['cgst_rate'] ?? 2.5);
                        $itemSgstRate = floatval($item['sgst_rate'] ?? $bill['sgst_rate'] ?? 2.5);
                        
                        $itemCGST = ($itemTaxable * $itemCgstRate) / 100;
                        $itemSGST = ($itemTaxable * $itemSgstRate) / 100;
                        $itemAmount = $itemTaxable + $itemCGST + $itemSGST;
                        
                        $totalQty += $item['quantity'];
                        $totalCgstSum += $itemCGST;
                        $totalSgstSum += $itemSGST;
                    ?>
                        <tr>
                            <td class="text-center-col"><?php echo $sno++; ?></td>
                            <td><?php echo strtoupper($item['product_name']); ?></td>
                            <td class="text-center-col"><?php echo $item['hsn_code'] ?: '-'; ?></td>
                            <td class="text-center-col"><?php echo number_format($item['quantity'], 0); ?></td>
                            <td class="text-right-col">₹ <?php echo number_format($item['price'], 2); ?></td>
                            <td class="text-right-col">₹ <?php echo number_format($itemTaxable, 2); ?></td>
                            <td class="text-right-col">₹ <?php echo number_format($itemCGST, 2); ?><br><small>(<?php echo number_format($itemCgstRate, 1); ?>%)</small></td>
                            <td class="text-right-col">₹ <?php echo number_format($itemSGST, 2); ?><br><small>(<?php echo number_format($itemSgstRate, 1); ?>%)</small></td>
                            <td class="text-right-col">₹ <?php echo number_format($itemAmount, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold; background: #f8f9fa;">
                        <td colspan="3" class="text-right-col">Total</td>
                        <td class="text-center-col"><?php echo number_format($totalQty, 0); ?></td>
                        <td></td>
                        <td class="text-right-col">₹ <?php echo number_format($bill['subtotal'], 2); ?></td>
                        <td class="text-right-col">₹ <?php echo number_format($totalCgstSum, 2); ?></td>
                        <td class="text-right-col">₹ <?php echo number_format($totalSgstSum, 2); ?></td>
                        <td class="text-right-col">₹ <?php echo number_format($bill['grand_total'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Tax Summary & Amounts -->
            <div class="tax-summary-section">
                <div class="tax-summary-grid">
                    <div>
                        <div class="section-title">Tax Type Breakdown</div>
                        <table class="tax-breakdown-table">
                            <thead>
                                <tr>
                                    <th>Tax Type</th>
                                    <th>Taxable Amount</th>
                                    <th>Tax Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>CGST Total</td>
                                    <td class="text-right-col">₹ <?php echo number_format($bill['subtotal'], 2); ?></td>
                                    <td class="text-right-col">₹ <?php echo number_format($totalCgstSum, 2); ?></td>
                                </tr>
                                <tr>
                                    <td>SGST Total</td>
                                    <td class="text-right-col">₹ <?php echo number_format($bill['subtotal'], 2); ?></td>
                                    <td class="text-right-col">₹ <?php echo number_format($totalSgstSum, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div>
                        <div class="section-title">Invoice Amounts Summary</div>
                        <table class="amounts-table">
                            <tr>
                                <td>Taxable Subtotal</td>
                                <td class="text-right-col">₹ <?php echo number_format($bill['subtotal'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Total CGST</td>
                                <td class="text-right-col">₹ <?php echo number_format($totalCgstSum, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Total SGST</td>
                                <td class="text-right-col">₹ <?php echo number_format($totalSgstSum, 2); ?></td>
                            </tr>
                            <tr class="total-row">
                                <td>Grand Total</td>
                                <td class="text-right-col">₹ <?php echo number_format($bill['grand_total'], 2); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Amount in Words -->
            <div class="amount-in-words-section">
                <p><strong>Invoice Amount In Words</strong></p>
                <p style="margin-top: 5px; font-size: 15px;"><?php echo $amountInWords; ?></p>
            </div>
            
         
             <!-- Terms and Bank Details -->
                    <div class="terms-bank-section">
                        <div class="terms-box">
                            <div class="section-title">Terms and Conditions</div>

                            <div class="section-title">OUR BANK DETAILS</div>
                            <div class="detail-row"><strong>NAME:</strong> Laseredge Medtech</div>
                            <div class="detail-row"><strong>BANK:</strong> AXIS BANK</div>
                            <div class="detail-row"><strong>BRANCH:</strong> Indira Nagar</div>
                            <div class="detail-row"><strong>AC NO:</strong> 925020011859257</div>
                            <div class="detail-row"><strong>IFSC:</strong> UTIB0005290</div>
                            <div class="detail-row"><strong>CITY:</strong> Kanpur, Uttar Pradesh </div>

                        </div>
                        <!-- Authorized Signatory -->
                        <div class="signature-section">
                            <p style="margin: 0; font-size: 14px;">For : LASEREDGE MEDTECH</p>

                            <!-- Signature Image -->
                            <img src="signature-removebg-preview.png" alt="Signature" style="width: 200px;">

                            <div class="signature-line" style="margin-top: 40px; border-top: 1px solid #000; ">
                                Authorized Signatory
                            </div>
                        </div>
                    </div>
            
            <!-- Authorized Signatory -->
        </div>
    </div>
</body>
</html>