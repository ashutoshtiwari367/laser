<?php
// update_database.php - Add tax columns to existing database
// Run this file ONCE to update your database

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Update - Adding Tax Columns</h2>";
echo "<hr>";

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'billing_system');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>");
}

echo "<p style='color: green;'>✅ Connected to database successfully</p>";

// Check if columns already exist
$checkQuery = "SHOW COLUMNS FROM bills LIKE 'subtotal'";
$result = $conn->query($checkQuery);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Tax columns already exist in 'bills' table.</p>";
} else {
    echo "<p style='color: blue;'>📝 Adding tax columns to 'bills' table...</p>";
    
    // Add columns one by one
    $queries = [
        "ALTER TABLE bills ADD COLUMN customer_gstin VARCHAR(20) AFTER customer_phone",
        "ALTER TABLE bills ADD COLUMN shipping_address TEXT AFTER customer_address",
        "ALTER TABLE bills ADD COLUMN is_shipping_same TINYINT(1) DEFAULT 1 AFTER shipping_address",
        "ALTER TABLE bills ADD COLUMN subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER is_shipping_same",
        "ALTER TABLE bills ADD COLUMN cgst_rate DECIMAL(5,2) DEFAULT 2.50 AFTER subtotal",
        "ALTER TABLE bills ADD COLUMN cgst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER cgst_rate",
        "ALTER TABLE bills ADD COLUMN sgst_rate DECIMAL(5,2) DEFAULT 2.50 AFTER cgst_amount",
        "ALTER TABLE bills ADD COLUMN sgst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER sgst_rate"
    ];
    
    $success = 0;
    foreach ($queries as $query) {
        if ($conn->query($query)) {
            $success++;
        } else {
            // Check if column already exists error
            if (strpos($conn->error, 'Duplicate column') === false) {
                echo "<p style='color: red;'>❌ Error: " . $conn->error . "</p>";
            }
        }
    }
    
    if ($success >= 5) {
        echo "<p style='color: green;'>✅ Successfully added columns to 'bills' table</p>";
        
        // Update existing bills
        $updateQuery = "UPDATE bills SET subtotal = grand_total WHERE subtotal = 0";
        if ($conn->query($updateQuery)) {
            $affected = $conn->affected_rows;
            echo "<p style='color: green;'>✅ Updated $affected existing bills with subtotal values</p>";
        }
    }
}

// Check bill_items table for HSN and unit columns
$checkQuery = "SHOW COLUMNS FROM bill_items LIKE 'hsn_code'";
$result = $conn->query($checkQuery);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ HSN and unit columns already exist in 'bill_items' table. No update needed!</p>";
} else {
    echo "<p style='color: blue;'>📝 Adding HSN and unit columns to 'bill_items' table...</p>";
    
    $queries = [
        "ALTER TABLE bill_items ADD COLUMN hsn_code VARCHAR(20) AFTER product_name",
        "ALTER TABLE bill_items ADD COLUMN unit VARCHAR(20) DEFAULT 'Qty' AFTER quantity"
    ];
    
    $success = 0;
    foreach ($queries as $query) {
        if ($conn->query($query)) {
            $success++;
        } else {
            echo "<p style='color: red;'>❌ Error: " . $conn->error . "</p>";
        }
    }
    
    if ($success == 2) {
        echo "<p style='color: green;'>✅ Successfully added HSN and unit columns to 'bill_items' table</p>";
    }
}

// Check company_settings for tax columns
$checkQuery = "SHOW COLUMNS FROM company_settings LIKE 'cgst_rate'";
$result = $conn->query($checkQuery);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Tax settings already exist in 'company_settings' table. No update needed!</p>";
} else {
    echo "<p style='color: blue;'>📝 Adding tax settings to 'company_settings' table...</p>";
    
    $queries = [
        "ALTER TABLE company_settings ADD COLUMN cgst_rate DECIMAL(5,2) DEFAULT 2.50",
        "ALTER TABLE company_settings ADD COLUMN sgst_rate DECIMAL(5,2) DEFAULT 2.50",
        "ALTER TABLE company_settings ADD COLUMN enable_tax TINYINT(1) DEFAULT 1"
    ];
    
    $success = 0;
    foreach ($queries as $query) {
        if ($conn->query($query)) {
            $success++;
        } else {
            echo "<p style='color: red;'>❌ Error: " . $conn->error . "</p>";
        }
    }
    
    if ($success == 3) {
        echo "<p style='color: green;'>✅ Successfully added tax settings to 'company_settings' table</p>";
    }
}

// Show current table structure
echo "<h3>Current Database Structure:</h3>";

echo "<h4>Bills Table Columns:</h4>";
$result = $conn->query("DESCRIBE bills");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    $highlight = in_array($row['Field'], ['subtotal', 'cgst_rate', 'cgst_amount', 'sgst_rate', 'sgst_amount']) ? 'background: #d1fae5;' : '';
    echo "<tr style='$highlight'><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Default']}</td></tr>";
}
echo "</table>";

echo "<h4>Bill Items Table Columns:</h4>";
$result = $conn->query("DESCRIBE bill_items");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    $highlight = in_array($row['Field'], ['hsn_code', 'unit']) ? 'background: #d1fae5;' : '';
    echo "<tr style='$highlight'><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Default']}</td></tr>";
}
echo "</table>";

$conn->close();

echo "<hr>";
echo "<h3 style='color: green;'>✅ Database Update Complete!</h3>";
echo "<p><strong>You can now create bills with tax calculation.</strong></p>";
echo "<p><a href='create_bill.php' style='padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>Go to Create Bill</a></p>";
echo "<br>";
echo "<p style='color: red;'><strong>IMPORTANT: Delete this update_database.php file after running it!</strong></p>";
?>