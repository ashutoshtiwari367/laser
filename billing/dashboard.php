<?php
// dashboard.php
require_once 'config.php';
checkLogin();

$conn = getDBConnection();

// Get dashboard statistics
$totalSalesQuery = "SELECT COALESCE(SUM(grand_total), 0) as total_sales FROM bills";
$totalSalesResult = $conn->query($totalSalesQuery);
$totalSales = 0;
if ($totalSalesResult && $totalSalesResult->num_rows > 0) {
    $totalSales = $totalSalesResult->fetch_assoc()['total_sales'];
}

$paymentReceivedQuery = "SELECT COALESCE(SUM(payment_received), 0) as payment_received FROM bills";
$paymentReceivedResult = $conn->query($paymentReceivedQuery);
$paymentReceived = 0;
if ($paymentReceivedResult && $paymentReceivedResult->num_rows > 0) {
    $paymentReceived = $paymentReceivedResult->fetch_assoc()['payment_received'];
}

$paymentPending = $totalSales - $paymentReceived;

// Get recent bills
$recentBillsQuery = "SELECT * FROM bills ORDER BY created_at DESC LIMIT 10";
$recentBills = $conn->query($recentBillsQuery);

// Get bills count by status
$paidCountResult = $conn->query("SELECT COUNT(*) as count FROM bills WHERE payment_status = 'Paid'");
$paidCount = 0;
if ($paidCountResult && $paidCountResult->num_rows > 0) {
    $paidCount = $paidCountResult->fetch_assoc()['count'];
}

$pendingCountResult = $conn->query("SELECT COUNT(*) as count FROM bills WHERE payment_status = 'Pending'");
$pendingCount = 0;
if ($pendingCountResult && $pendingCountResult->num_rows > 0) {
    $pendingCount = $pendingCountResult->fetch_assoc()['count'];
}

$partialCountResult = $conn->query("SELECT COUNT(*) as count FROM bills WHERE payment_status = 'Partial'");
$partialCount = 0;
if ($partialCountResult && $partialCountResult->num_rows > 0) {
    $partialCount = $partialCountResult->fetch_assoc()['count'];
}

$totalBills = $paidCount + $pendingCount + $partialCount;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Billing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div>
                <h2>Dashboard </h2>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
            </div>
            <a href="create_bill.php" class="btn btn-primary">+ Create New Bill</a>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-info">
                <div class="stat-icon">📋</div>
                <div class="stat-content">
                    <h3>Total Bills</h3>
                    <p class="stat-value"><?php echo $totalBills; ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-primary">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <h3>Total Sales</h3>
                    <p class="stat-value"><?php echo formatCurrency($totalSales); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3>Payment Received</h3>
                    <p class="stat-value"><?php echo formatCurrency($paymentReceived); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-warning">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <h3>Payment Pending</h3>
                    <p class="stat-value"><?php echo formatCurrency($paymentPending); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Bills Status Summary -->
        <div class="status-summary">
            <div class="status-item">
                <span class="status-badge status-paid"><?php echo $paidCount; ?></span>
                <span>Paid Bills</span>
            </div>
            <div class="status-item">
                <span class="status-badge status-pending"><?php echo $pendingCount; ?></span>
                <span>Pending Bills</span>
            </div>
            <div class="status-item">
                <span class="status-badge status-partial"><?php echo $partialCount; ?></span>
                <span>Partial Bills</span>
            </div>
        </div>
        
        <!-- Recent Bills Table -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Bills</h3>
                <a href="view_bills.php" class="btn btn-secondary">View All Bills</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bill No.</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Grand Total</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentBills && $recentBills->num_rows > 0): ?>
                            <?php while ($bill = $recentBills->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo $bill['bill_no']; ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($bill['bill_date'])); ?></td>
                                    <td><?php echo $bill['customer_name']; ?></td>
                                    <td><?php echo formatCurrency($bill['grand_total']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($bill['payment_status']); ?>">
                                            <?php echo $bill['payment_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="preview_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-secondary" title="Preview">👁️</a>
                                        <a href="download_pdf.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-primary" title="Download PDF" target="_blank">📄</a>
                                        <!-- <a href="export_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-success" title="Export">📥</a> -->
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 40px;">
                                    <p style="font-size: 18px; color: #6b7280;">No bills found. Create your first bill!</p>
                                    <a href="create_bill.php" class="btn btn-primary" style="margin-top: 10px;">Create Bill</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>