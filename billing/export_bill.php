<?php
// export_bill.php - Export bill data to CSV format
require_once 'config.php';
checkLogin();

$conn = getDBConnection();

if (isset($_GET['id'])) {
    $billId = intval($_GET['id']);
    
    // Fetch bill details
    $stmt = $conn->prepare("SELECT * FROM bills WHERE id = ?");
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    
    if (!$bill) {
        die("Bill not found.");
    }
    
    // Fetch items
    $stmt = $conn->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    
    $filename = "Invoice_" . $bill['bill_no'] . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Bill Header Details
    fputcsv($output, ['INVOICE DETAILS']);
    fputcsv($output, ['Bill No', $bill['bill_no']]);
    fputcsv($output, ['Bill Date', $bill['bill_date']]);
    fputcsv($output, ['Customer Name', $bill['customer_name']]);
    fputcsv($output, ['Customer Phone', $bill['customer_phone']]);
    fputcsv($output, ['ID Type', $bill['customer_id_type'] ?? 'none']);
    fputcsv($output, ['GSTIN', $bill['customer_gstin'] ?? '']);
    fputcsv($output, ['Aadhaar', $bill['customer_aadhaar'] ?? '']);
    fputcsv($output, ['Billing Address', $bill['customer_address']]);
    fputcsv($output, ['Shipping Address', $bill['shipping_address']]);
    fputcsv($output, []);
    
    // Items Header
    fputcsv($output, ['ITEMS']);
    fputcsv($output, ['S.No.', 'Product Name', 'HSN Code', 'Quantity', 'Unit', 'Price', 'Total']);
    
    $sno = 1;
    while ($item = $itemsResult->fetch_assoc()) {
        fputcsv($output, [
            $sno++,
            $item['product_name'],
            $item['hsn_code'] ?? '',
            $item['quantity'],
            $item['unit'] ?? 'Qty',
            $item['price'],
            $item['total']
        ]);
    }
    
    fputcsv($output, []);
    // Summary
    fputcsv($output, ['TAX & TOTAL SUMMARY']);
    fputcsv($output, ['Subtotal', $bill['subtotal']]);
    fputcsv($output, ['CGST Rate (%)', $bill['cgst_rate']]);
    fputcsv($output, ['CGST Amount', $bill['cgst_amount']]);
    fputcsv($output, ['SGST Rate (%)', $bill['sgst_rate']]);
    fputcsv($output, ['SGST Amount', $bill['sgst_amount']]);
    fputcsv($output, ['Grand Total', $bill['grand_total']]);
    fputcsv($output, ['Payment Status', $bill['payment_status']]);
    fputcsv($output, ['Payment Received', $bill['payment_received']]);
    
    fclose($output);
    $conn->close();
    exit();
} else {
    // Export all bills summary
    $query = "SELECT * FROM bills ORDER BY created_at DESC";
    $result = $conn->query($query);
    
    $filename = "Bills_Summary_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Bill No', 'Date', 'Customer Name', 'Phone', 'GSTIN/Aadhaar', 'Subtotal', 'CGST', 'SGST', 'Grand Total', 'Received', 'Balance', 'Status']);
    
    while ($bill = $result->fetch_assoc()) {
        $idNumber = '';
        if (($bill['customer_id_type'] ?? '') == 'gstin') {
            $idNumber = $bill['customer_gstin'] ?? '';
        } elseif (($bill['customer_id_type'] ?? '') == 'aadhaar') {
            $idNumber = $bill['customer_aadhaar'] ?? '';
        }
        
        $balance = $bill['grand_total'] - $bill['payment_received'];
        
        fputcsv($output, [
            $bill['bill_no'],
            $bill['bill_date'],
            $bill['customer_name'],
            $bill['customer_phone'],
            $idNumber,
            $bill['subtotal'],
            $bill['cgst_amount'],
            $bill['sgst_amount'],
            $bill['grand_total'],
            $bill['payment_received'],
            $balance,
            $bill['payment_status']
        ]);
    }
    
    fclose($output);
    $conn->close();
    exit();
}
?>
