<?php
// download_pdf.php - Direct PDF Download without URL in footer
require_once 'config.php';
checkLogin();

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$billId = intval($_GET['id']);
$conn = getDBConnection();

// Get bill details
$stmt = $conn->prepare("SELECT * FROM bills WHERE id = ?");
$stmt->bind_param("i", $billId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<script>alert("Bill not found!"); window.location.href="dashboard.php";</script>';
    exit();
}

$bill = $result->fetch_assoc();

// Set default tax values if not exist
if (!isset($bill['cgst_rate'])) $bill['cgst_rate'] = 2.5;
if (!isset($bill['sgst_rate'])) $bill['sgst_rate'] = 2.5;
if (!isset($bill['cgst_amount'])) $bill['cgst_amount'] = 0;
if (!isset($bill['sgst_amount'])) $bill['sgst_amount'] = 0;
if (!isset($bill['customer_gstin'])) $bill['customer_gstin'] = '';
if (!isset($bill['shipping_address'])) $bill['shipping_address'] = '';
if (!isset($bill['is_shipping_same'])) $bill['is_shipping_same'] = 1;

// Get bill items
$stmt = $conn->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
$stmt->bind_param("i", $billId);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($item = $result->fetch_assoc()) {
    if (!isset($item['hsn_code'])) $item['hsn_code'] = '';
    if (!isset($item['unit'])) $item['unit'] = 'Qty';
    $items[] = $item;
}

$conn->close();

// Dynamically recalculate tax values for items and bill if tax/subtotal was missing or zero
$calcSubtotal = 0;
$calcCgst = 0;
$calcSgst = 0;

foreach ($items as &$item) {
    $taxable = floatval($item['quantity']) * floatval($item['price']);
    $itemCgstRate = (isset($item['cgst_rate']) && floatval($item['cgst_rate']) > 0) ? floatval($item['cgst_rate']) : floatval($bill['cgst_rate'] ?? 2.5);
    $itemSgstRate = (isset($item['sgst_rate']) && floatval($item['sgst_rate']) > 0) ? floatval($item['sgst_rate']) : floatval($bill['sgst_rate'] ?? 2.5);
    
    $itemCgstAmt = (isset($item['cgst_amount']) && floatval($item['cgst_amount']) > 0) ? floatval($item['cgst_amount']) : (($taxable * $itemCgstRate) / 100);
    $itemSgstAmt = (isset($item['sgst_amount']) && floatval($item['sgst_amount']) > 0) ? floatval($item['sgst_amount']) : (($taxable * $itemSgstRate) / 100);
    
    $item['cgst_rate'] = $itemCgstRate;
    $item['sgst_rate'] = $itemSgstRate;
    $item['cgst_amount'] = $itemCgstAmt;
    $item['sgst_amount'] = $itemSgstAmt;
    $item['total'] = $taxable + $itemCgstAmt + $itemSgstAmt;
    
    $calcSubtotal += $taxable;
    $calcCgst += $itemCgstAmt;
    $calcSgst += $itemSgstAmt;
}
unset($item);

// If bill subtotal or cgst_amount was 0 or subtotal == grand_total (old bill issue), override bill values
if (empty($bill['subtotal']) || floatval($bill['cgst_amount']) == 0 || floatval($bill['subtotal']) == floatval($bill['grand_total'])) {
    $bill['subtotal'] = $calcSubtotal;
    $bill['cgst_amount'] = $calcCgst;
    $bill['sgst_amount'] = $calcSgst;
    $bill['grand_total'] = $calcSubtotal + $calcCgst + $calcSgst;
}

$company = getCompanySettings();
$amountInWords = numberToWords($bill['grand_total']);

// Set headers for PDF download
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - <?php echo $bill['bill_no']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            background: white;
        }
        
        .professional-invoice {
            width: 206mm;
            max-height: 293mm;
            margin: 10px auto;
            background: white;
            padding: 0;
            border: 2px solid #000;
            box-sizing: border-box;
            overflow: hidden;
            page-break-after: avoid;
            page-break-inside: avoid;
        }
        
        .invoice-header-section {
            text-align: center;
            padding: 10px;
            border-bottom: 2px solid #000;
            background: #f8f9fa;
        }
        
        .invoice-header-section h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
            color: #000;
        }
        
        .company-details-section {
            padding: 12px 15px;
            border-bottom: 2px solid #000;
        }
        
        .company-details-section h2 {
            margin: 0 0 6px 0;
            font-size: 16px;
            font-weight: bold;
            color: #000;
        }
        
        .company-details-section p {
            margin: 2px 0;
            font-size: 10px;
            line-height: 1.4;
        }
        
        .invoice-party-section {
            display: table;
            width: 100%;
            border-bottom: 2px solid #000;
        }
        
        .bill-to-box, .invoice-details-box {
            display: table-cell;
            width: 50%;
            padding: 12px 15px;
            border-right: 2px solid #000;
            vertical-align: top;
        }
        
        .invoice-details-box {
            border-right: none;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 6px;
            color: #000;
        }
        
        .detail-row {
            margin: 3px 0;
            font-size: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            display: inline-block;
            min-width: 100px;
        }
        
        /* FIXED HEIGHT PRODUCT TABLE */
        .product-table-container {
            height: 440px;
            overflow: hidden;
            border-bottom: 2px solid #000;
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
            padding: 8px 5px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
            border-right: 1px solid #ddd;
        }
        
        .professional-table th:last-child {
            border-right: none;
        }
        
        .professional-table td {
            padding: 6px 5px;
            border-bottom: 1px solid #ddd;
            border-right: 1px solid #ddd;
            font-size: 10px;
            max-width: 150px;
            word-wrap: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .professional-table td:last-child {
            border-right: none;
        }
        
        .professional-table tbody tr:last-child td {
            border-bottom: 2px solid #000;
        }
        
        /* Empty rows styling */
        .empty-row td {
            padding: 8px 5px;
            border-bottom: 1px solid #ddd;
        }
        
        .text-right-col {
            text-align: right;
        }
        
        .text-center-col {
            text-align: center;
        }
        
        .tax-summary-section {
            padding: 10px 15px;
            border-bottom: 2px solid #000;
        }
        
        .tax-summary-grid {
            display: table;
            width: 100%;
        }
        
        .tax-summary-grid > div {
            display: table-cell;
            width: 50%;
            padding: 0 10px;
        }
        
        .tax-breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tax-breakdown-table th,
        .tax-breakdown-table td {
            padding: 5px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 9px;
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
            padding: 5px;
            font-size: 10px;
        }
        
        .amounts-table .total-row td {
            font-weight: bold;
            font-size: 11px;
            border-top: 2px solid #000;
            padding-top: 8px;
        }
        
        .amount-in-words-section {
            padding: 10px 15px;
            background: #f8f9fa;
            border-bottom: 2px solid #000;
        }
        
        .amount-in-words-section p {
            margin: 0;
            font-size: 10px;
        }
        
        .amount-in-words-section strong {
            font-weight: bold;
        }
        
        .terms-bank-section {
            display: table;
            width: 100%;
            border-bottom: 2px solid #000;
        }
        
        .terms-box {
            display: table-cell;
            width: 50%;
            padding: 12px 15px;
            border-right: 2px solid #000;
            vertical-align: top;
        }
        
        .bank-box {
            display: table-cell;
            width: 50%;
            padding: 12px 15px;
            vertical-align: top;
        }
        
        .signature-section {
            padding: 20px 15px 15px;
            text-align: right;
            position: relative;
        }
        
        .signature-image {
            position: absolute;
            right: 15px;
            bottom: 35px;
            max-width: 150px;
            max-height: 60px;
            opacity: 0.8;
        }
        
        .signature-line {
            margin-top: 40px;
            font-weight: bold;
            font-size: 11px;
        }

        .action-bar {
            max-width: 206mm;
            margin: 15px auto 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1e293b;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .action-bar .btn {
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-back { background: #475569; color: #fff; }
        .btn-back:hover { background: #334155; }
        .btn-save { background: #2563eb; color: #fff; }
        .btn-save:hover { background: #1d4ed8; }

        @media print {
            @page {
                margin: 0;
                size: A4 portrait;
            }
            
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            
            .professional-invoice {
                width: 210mm;
                height: 297mm;
                margin: 0;
                border: 2px solid #000;
                page-break-after: avoid;
                page-break-inside: avoid;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
    <!-- Include html2pdf.js for client-side direct PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.querySelector('.professional-invoice');
            const btn = document.getElementById('btn-download-pdf');
            if (btn) {
                btn.innerHTML = '⏳ Downloading PDF...';
                btn.disabled = true;
            }

            const opt = {
                margin:       [0, 0, 0, 0],
                filename:     'Invoice-<?php echo $bill['bill_no']; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false, scrollY: 0 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak:    { mode: ['avoid-all'] }
            };

            html2pdf().set(opt).from(element).save().then(function() {
                if (btn) {
                    btn.innerHTML = '✅ PDF Downloaded!';
                    setTimeout(function() {
                        btn.innerHTML = '📥 Download PDF Again';
                        btn.disabled = false;
                    }, 2000);
                }
            }).catch(function(err) {
                console.error(err);
                if (btn) {
                    btn.innerHTML = '📥 Download PDF Again';
                    btn.disabled = false;
                }
            });
        }

        window.onload = function() {
            // Auto trigger direct PDF file download without opening print dialog
            setTimeout(function() {
                downloadPDF();
            }, 400);
        }
    </script>
</head>
<body>
    <div class="action-bar no-print">
        <div>
            <a href="preview_bill.php?id=<?php echo $billId; ?>" class="btn btn-back">⬅ Back to Invoice</a>
        </div>
        <div>
            <button id="btn-download-pdf" onclick="downloadPDF()" class="btn btn-save">📥 Download PDF Again</button>
        </div>
    </div>

    <div class="professional-invoice">
        <!-- Header: Tax Invoice -->
        <div class="invoice-header-section">
            <h1>Tax Invoice</h1>
        </div>
        
        <!-- Company Details -->
        <div class="company-details-container" style="display: table; width: 100%; border-bottom: 2px solid #000; padding: 10px 15px;">
            <div style="display: table-cell; vertical-align: middle; width: 60%;">
                <h2 style="margin: 0; font-size: 16px; font-weight: bold; color: #000;">LASEREDGE MEDTECH</h2>
                <p style="margin: 3px 0; font-size: 10px;">Block -C1, House No-175 Indira Nagar Kanpur - 208026</p>
                <p style="margin: 3px 0; font-size: 10px;">
                    <strong>Phone no.:</strong> +917618037434, +918090938659 <br>
                    <strong>Email:</strong> laseredgemedtech@gmail.com <br>
                    <strong>GSTN:</strong> 09BXCPK2300M1ZL
                </p>
            </div>
            <div style="display: table-cell; vertical-align: middle; width: 40%; text-align: right;">
                <img src="logolaseredgemedtech-removebg-preview.webp" alt="Company Logo" style="max-width: 220px; height: auto;">
            </div>
        </div>
        
        <!-- Bill To and Invoice Details -->
        <div class="invoice-party-section">
            <div class="bill-to-box">
                <div class="section-title">Bill To</div>
                <p style="margin: 10px 0; font-weight: bold; font-size: 15px;"><?php echo strtoupper($bill['customer_name']); ?></p>
                <?php if (!empty($bill['customer_address'])): ?>
                    <p style="margin: 5px 0;"><strong>Address:</strong><?php echo $bill['customer_address']; ?></p>
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
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ddd;">
                        <div class="section-title" style="font-size: 11px;">Ship To</div>
                        <p style="margin: 5px 0; font-size: 10px;"><?php echo $bill['shipping_address']; ?></p>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Items Table with Fixed Height -->
        <div class="product-table-container">
            <table class="professional-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">S.NO.</th>
                        <th style="width: 28%;">PARTICULARS</th>
                        <th style="width: 10%;">HSN/SAC</th>
                        <th style="width: 7%;">QTY</th>
                        <th style="width: 12%;">PRICE</th>
                        <th style="width: 12%;">Taxable</th>
                        <th style="width: 9%;">CGST</th>
                        <th style="width: 9%;">SGST</th>
                        <th style="width: 12%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sno = 1;
                    $totalQty = 0;
                    $maxRows = 15;
                    
                    foreach ($items as $item): 
                        if ($sno > $maxRows) break;
                        
                        $itemTotal = floatval($item['quantity']) * floatval($item['price']);
                        $itemCgstRate = isset($item['cgst_rate']) ? floatval($item['cgst_rate']) : floatval($bill['cgst_rate']);
                        $itemSgstRate = isset($item['sgst_rate']) ? floatval($item['sgst_rate']) : floatval($bill['sgst_rate']);
                        $itemCGST = isset($item['cgst_amount']) && $item['cgst_amount'] > 0 ? floatval($item['cgst_amount']) : (($itemTotal * $itemCgstRate) / 100);
                        $itemSGST = isset($item['sgst_amount']) && $item['sgst_amount'] > 0 ? floatval($item['sgst_amount']) : (($itemTotal * $itemSgstRate) / 100);
                        $itemAmount = $itemTotal + $itemCGST + $itemSGST;
                        $totalQty += $item['quantity'];
                        
                        // Truncate product name to 40 characters
                        $productName = $item['product_name'];
                        if (strlen($productName) > 40) {
                            $productName = substr($productName, 0, 40) . '...';
                        }
                    ?>
                        <tr>
                            <td class="text-center-col"><?php echo $sno++; ?></td>
                            <td><?php echo strtoupper($productName); ?></td>
                            <td class="text-center-col"><?php echo $item['hsn_code'] ?: '-'; ?></td>
                            <td class="text-center-col"><?php echo number_format($item['quantity'], 0); ?></td>
                            <td class="text-right-col">₹<?php echo number_format($item['price'], 2); ?></td>
                            <td class="text-right-col">₹<?php echo number_format($itemTotal, 2); ?></td>
                            <td class="text-right-col">₹<?php echo number_format($itemCGST, 2); ?></td>
                            <td class="text-right-col">₹<?php echo number_format($itemSGST, 2); ?></td>
                            <td class="text-right-col">₹<?php echo number_format($itemAmount, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php 
                    // Fill empty rows to maintain fixed table height
                    $actualRows = min(count($items), $maxRows);
                    $emptyRows = $maxRows - $actualRows;
                    for ($i = 0; $i < $emptyRows; $i++): 
                    ?>
                        <tr class="empty-row">
                            <td class="text-center-col">&nbsp;</td>
                            <td>&nbsp;</td>
                            <td class="text-center-col">-</td>
                            <td class="text-center-col">&nbsp;</td>
                            <td class="text-right-col">&nbsp;</td>
                            <td class="text-right-col">&nbsp;</td>
                            <td class="text-right-col">&nbsp;</td>
                            <td class="text-right-col">&nbsp;</td>
                            <td class="text-right-col">&nbsp;</td>
                        </tr>
                    <?php endfor; ?>
                    
                    <tr style="font-weight: bold; background: #f8f9fa;">
                        <td colspan="3" class="text-right-col">Total</td>
                        <td class="text-center-col"><?php echo number_format($totalQty, 0); ?></td>
                        <td></td>
                        <td class="text-right-col">₹<?php echo number_format($bill['subtotal'], 2); ?></td>
                        <td class="text-right-col">₹<?php echo number_format($bill['cgst_amount'], 2); ?></td>
                        <td class="text-right-col">₹<?php echo number_format($bill['sgst_amount'], 2); ?></td>
                        <td class="text-right-col">₹<?php echo number_format($bill['grand_total'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Tax Summary & Amounts -->
        <div class="tax-summary-section">
            <div class="tax-summary-grid">
                <div>
                    <div class="section-title">Tax type</div>
                    <table class="tax-breakdown-table">
                        <thead>
                            <tr>
                                <th>Tax Type</th>
                                <th>Taxable Amount</th>
                                <th>Rate</th>
                                <th>Tax Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $taxGroups = [];
                            foreach ($items as $item) {
                                $itemTaxable = round(floatval($item['quantity']) * floatval($item['price']), 2);
                                $itemCgstRate = isset($item['cgst_rate']) ? floatval($item['cgst_rate']) : floatval($bill['cgst_rate'] ?? 2.5);
                                $itemSgstRate = isset($item['sgst_rate']) ? floatval($item['sgst_rate']) : floatval($bill['sgst_rate'] ?? 2.5);
                                
                                $itemCgstAmt = (isset($item['cgst_amount']) && floatval($item['cgst_amount']) > 0) ? floatval($item['cgst_amount']) : round(($itemTaxable * $itemCgstRate) / 100, 2);
                                $itemSgstAmt = (isset($item['sgst_amount']) && floatval($item['sgst_amount']) > 0) ? floatval($item['sgst_amount']) : round(($itemTaxable * $itemSgstRate) / 100, 2);
                                
                                $sgstKey = number_format($itemSgstRate, 2, '.', '');
                                $cgstKey = number_format($itemCgstRate, 2, '.', '');
                                
                                if (!isset($taxGroups[$sgstKey])) {
                                    $taxGroups[$sgstKey] = ['rate' => $itemSgstRate, 'sgst_taxable' => 0, 'sgst_amt' => 0, 'cgst_taxable' => 0, 'cgst_amt' => 0];
                                }
                                if (!isset($taxGroups[$cgstKey])) {
                                    $taxGroups[$cgstKey] = ['rate' => $itemCgstRate, 'sgst_taxable' => 0, 'sgst_amt' => 0, 'cgst_taxable' => 0, 'cgst_amt' => 0];
                                }
                                
                                $taxGroups[$sgstKey]['sgst_taxable'] += $itemTaxable;
                                $taxGroups[$sgstKey]['sgst_amt'] += $itemSgstAmt;
                                $taxGroups[$cgstKey]['cgst_taxable'] += $itemTaxable;
                                $taxGroups[$cgstKey]['cgst_amt'] += $itemCgstAmt;
                            }
                            ksort($taxGroups, SORT_NUMERIC);

                            foreach ($taxGroups as $group):
                            ?>
                                <?php if ($group['sgst_taxable'] > 0 || $group['sgst_amt'] > 0): ?>
                                <tr>
                                    <td>SGST (<?php echo floatval($group['rate']); ?>%)</td>
                                    <td class="text-right-col">₹ <?php echo number_format($group['sgst_taxable'], 2); ?></td>
                                    <td class="text-center-col"><?php echo number_format($group['rate'], 1); ?>%</td>
                                    <td class="text-right-col">₹ <?php echo number_format($group['sgst_amt'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($group['cgst_taxable'] > 0 || $group['cgst_amt'] > 0): ?>
                                <tr>
                                    <td>CGST (<?php echo floatval($group['rate']); ?>%)</td>
                                    <td class="text-right-col">₹ <?php echo number_format($group['cgst_taxable'], 2); ?></td>
                                    <td class="text-center-col"><?php echo number_format($group['rate'], 1); ?>%</td>
                                    <td class="text-right-col">₹ <?php echo number_format($group['cgst_amt'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div>
                    <div class="section-title">Amounts</div>
                    <table class="amounts-table">
                        <tr>
                            <td>Sub Total</td>
                            <td class="text-right-col">₹ <?php echo number_format($bill['subtotal'], 2); ?></td>
                        </tr>
                        <tr class="total-row">
                            <td>Total</td>
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
                <img src="signature-removebg-preview.png" alt="Signature" style="width: 100px;">

                <div class="signature-line" style="margin-top: 40px; border-top: 1px solid #000; ">
                    Authorized Signatory
                </div>
            </div>
        </div>
    </div>
</body>
</html>