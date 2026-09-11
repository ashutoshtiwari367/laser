<?php
// view_bills.php
require_once 'config.php';
checkLogin();

$conn = getDBConnection();

// Filter parameters
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT * FROM bills WHERE 1=1";

if ($filterStatus != 'all') {
    $query .= " AND payment_status = '" . $conn->real_escape_string($filterStatus) . "'";
}

if ($searchTerm) {
    $query .= " AND (bill_no LIKE '%" . $conn->real_escape_string($searchTerm) . "%' 
                OR customer_name LIKE '%" . $conn->real_escape_string($searchTerm) . "%')";
}

$query .= " ORDER BY created_at DESC";

$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bills - Billing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>All Bills</h2>
            <a href="create_bill.php" class="btn btn-primary">+ Create New Bill</a>
        </div>
        
        <!-- Filter Form -->
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Filter by Status</label>
                            <select id="status" name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $filterStatus == 'all' ? 'selected' : ''; ?>>All Bills</option>
                                <option value="Paid" <?php echo $filterStatus == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="Pending" <?php echo $filterStatus == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Partial" <?php echo $filterStatus == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="search">Search Bills</label>
                            <input type="text" id="search" name="search" placeholder="Bill No. or Customer Name" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="view_bills.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Bills Table -->
        <div class="card">
            <div class="card-header">
                <h3>Bills List</h3>
                <span>Total: <?php echo $result->num_rows; ?> bills</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bill No.</th>
                            <th>Date</th>
                            <th>Customer Name</th>
                            <th>Customer Phone</th>
                            <th>Grand Total</th>
                            <th>Payment Received</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($bill = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo $bill['bill_no']; ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($bill['bill_date'])); ?></td>
                                    <td><?php echo $bill['customer_name']; ?></td>
                                    <td><?php echo $bill['customer_phone'] ?? '-'; ?></td>
                                    <td><?php echo formatCurrency($bill['grand_total']); ?></td>
                                    <td><?php echo formatCurrency($bill['payment_received']); ?></td>
                                    <td><?php echo formatCurrency($bill['grand_total'] - $bill['payment_received']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($bill['payment_status']); ?>">
                                            <?php echo $bill['payment_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="preview_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-secondary" title="Preview">👁️</a>
                                        <a href="download_pdf.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-primary" title="Download PDF" target="_blank">📄</a>
                                        <!--<a href="export_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-success" title="Export">📥</a>-->
                                        <a href="edit_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-primary" title="Edit">✏️</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center" style="padding: 40px;">
                                    <p style="font-size: 18px; color: #6b7280;">No bills found.</p>
                                    <a href="create_bill.php" class="btn btn-primary" style="margin-top: 10px;">Create Your First Bill</a>
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