<?php
// reports.php
require_once 'config.php';
checkLogin();

$conn = getDBConnection();

// Date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Summary statistics with date filter
$query = "SELECT 
    COUNT(*) as total_bills,
    COALESCE(SUM(grand_total), 0) as total_sales,
    COALESCE(SUM(payment_received), 0) as payment_received,
    COALESCE(SUM(grand_total - payment_received), 0) as balance_due
    FROM bills 
    WHERE bill_date BETWEEN ? AND ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$summary = ['total_bills' => 0, 'total_sales' => 0, 'payment_received' => 0, 'balance_due' => 0];
if ($result && $result->num_rows > 0) {
    $summary = $result->fetch_assoc();
}

// Status-wise breakdown
$statusQuery = "SELECT 
    payment_status,
    COUNT(*) as count,
    COALESCE(SUM(grand_total), 0) as total_amount
    FROM bills 
    WHERE bill_date BETWEEN ? AND ?
    GROUP BY payment_status";

$stmt = $conn->prepare($statusQuery);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$statusBreakdown = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $statusBreakdown[] = $row;
    }
}

// Top customers
$customersQuery = "SELECT 
    customer_name,
    COUNT(*) as bill_count,
    COALESCE(SUM(grand_total), 0) as total_spent
    FROM bills 
    WHERE bill_date BETWEEN ? AND ?
    GROUP BY customer_name
    ORDER BY total_spent DESC
    LIMIT 10";

$stmt = $conn->prepare($customersQuery);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$topCustomers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topCustomers[] = $row;
    }
}

// Monthly trend (last 6 months)
$trendQuery = "SELECT 
    DATE_FORMAT(bill_date, '%Y-%m') as month,
    COUNT(*) as bill_count,
    COALESCE(SUM(grand_total), 0) as total_sales
    FROM bills 
    WHERE bill_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(bill_date, '%Y-%m')
    ORDER BY month ASC";

$result = $conn->query($trendQuery);
$monthlyTrend = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $monthlyTrend[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Billing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>Reports & Analytics</h2>
        </div>
        
        <!-- Date Filter -->
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-info">
                <div class="stat-icon">📋</div>
                <div class="stat-content">
                    <h3>Total Bills</h3>
                    <p class="stat-value"><?php echo $summary['total_bills']; ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-primary">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <h3>Total Sales</h3>
                    <p class="stat-value"><?php echo formatCurrency($summary['total_sales']); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3>Collected</h3>
                    <p class="stat-value"><?php echo formatCurrency($summary['payment_received']); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-warning">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <h3>Balance Due</h3>
                    <p class="stat-value"><?php echo formatCurrency($summary['balance_due']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Status Breakdown -->
        <?php if (!empty($statusBreakdown)): ?>
        <div class="card">
            <div class="card-header">
                <h3>Payment Status Breakdown</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Number of Bills</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statusBreakdown as $status): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($status['payment_status']); ?>">
                                        <?php echo $status['payment_status']; ?>
                                    </span>
                                </td>
                                <td><?php echo $status['count']; ?></td>
                                <td><?php echo formatCurrency($status['total_amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Top Customers -->
        <div class="card">
            <div class="card-header">
                <h3>Top 10 Customers</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Customer Name</th>
                            <th>Number of Bills</th>
                            <th>Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($topCustomers)): ?>
                            <?php 
                            $rank = 1;
                            foreach ($topCustomers as $customer): 
                            ?>
                                <tr>
                                    <td><strong><?php echo $rank++; ?></strong></td>
                                    <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                    <td><?php echo $customer['bill_count']; ?></td>
                                    <td><strong><?php echo formatCurrency($customer['total_spent']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center" style="padding: 40px;">No data available for selected period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Monthly Trend -->
        <div class="card">
            <div class="card-header">
                <h3>Monthly Sales Trend (Last 6 Months)</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Number of Bills</th>
                            <th>Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($monthlyTrend)): ?>
                            <?php foreach ($monthlyTrend as $month): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                    <td><?php echo $month['bill_count']; ?></td>
                                    <td><?php echo formatCurrency($month['total_sales']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center" style="padding: 40px;">No data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>