<?php
// update_database.php - Add missing columns to existing database
// Run this file ONCE via browser (e.g. https://laseredgemedtech.com/billing/update_database.php) to update your database

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Database Update - Adding Missing Columns & Tax Settings</h2>";
echo "<hr>";

$conn = getDBConnection();

echo "<p style='color: green;'>✅ Connected to database successfully</p>";

// Columns to check and add for 'bills' table
$billsColumns = [
    'customer_id_type' => "ALTER TABLE bills ADD COLUMN customer_id_type VARCHAR(20) DEFAULT 'none' AFTER customer_phone",
    'customer_gstin'   => "ALTER TABLE bills ADD COLUMN customer_gstin VARCHAR(20) AFTER customer_id_type",
    'customer_aadhaar' => "ALTER TABLE bills ADD COLUMN customer_aadhaar VARCHAR(20) AFTER customer_gstin",
    'shipping_address' => "ALTER TABLE bills ADD COLUMN shipping_address TEXT AFTER customer_address",
    'is_shipping_same' => "ALTER TABLE bills ADD COLUMN is_shipping_same TINYINT(1) DEFAULT 1 AFTER shipping_address",
    'subtotal'         => "ALTER TABLE bills ADD COLUMN subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER is_shipping_same",
    'cgst_rate'        => "ALTER TABLE bills ADD COLUMN cgst_rate DECIMAL(5,2) DEFAULT 2.50 AFTER subtotal",
    'cgst_amount'      => "ALTER TABLE bills ADD COLUMN cgst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER cgst_rate",
    'sgst_rate'        => "ALTER TABLE bills ADD COLUMN sgst_rate DECIMAL(5,2) DEFAULT 2.50 AFTER cgst_amount",
    'sgst_amount'      => "ALTER TABLE bills ADD COLUMN sgst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER sgst_rate"
];

echo "<h3>1. Updating 'bills' table:</h3>";
foreach ($billsColumns as $columnName => $alterQuery) {
    $checkQuery = "SHOW COLUMNS FROM bills LIKE '$columnName'";
    $result = $conn->query($checkQuery);
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: orange;'>⚠️ Column '$columnName' already exists in 'bills' table.</p>";
    } else {
        if ($conn->query($alterQuery)) {
            echo "<p style='color: green;'>✅ Successfully added '$columnName' to 'bills' table.</p>";
        } else {
            echo "<p style='color: red;'>❌ Error adding '$columnName': " . $conn->error . "</p>";
        }
    }
}

// Update subtotal for existing bills if subtotal is 0
$updateQuery = "UPDATE bills SET subtotal = grand_total WHERE subtotal = 0";
if ($conn->query($updateQuery)) {
    $affected = $conn->affected_rows;
    if ($affected > 0) {
        echo "<p style='color: green;'>✅ Updated $affected existing bills with subtotal values.</p>";
    }
}

// Columns to check and add for 'bill_items' table
$itemsColumns = [
    'hsn_code'    => "ALTER TABLE bill_items ADD COLUMN hsn_code VARCHAR(20) AFTER product_name",
    'unit'        => "ALTER TABLE bill_items ADD COLUMN unit VARCHAR(20) DEFAULT 'Qty' AFTER quantity",
    'cgst_rate'   => "ALTER TABLE bill_items ADD COLUMN cgst_rate DECIMAL(5,2) DEFAULT 2.50 AFTER price",
    'cgst_amount' => "ALTER TABLE bill_items ADD COLUMN cgst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER cgst_rate",
    'sgst_rate'   => "ALTER TABLE bill_items ADD COLUMN sgst_rate DECIMAL(5,2) DEFAULT 2.50 AFTER cgst_amount",
    'sgst_amount' => "ALTER TABLE bill_items ADD COLUMN sgst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER sgst_rate"
];

echo "<h3>2. Updating 'bill_items' table:</h3>";
foreach ($itemsColumns as $columnName => $alterQuery) {
    $checkQuery = "SHOW COLUMNS FROM bill_items LIKE '$columnName'";
    $result = $conn->query($checkQuery);
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: orange;'>⚠️ Column '$columnName' already exists in 'bill_items' table.</p>";
    } else {
        if ($conn->query($alterQuery)) {
            echo "<p style='color: green;'>✅ Successfully added '$columnName' to 'bill_items' table.</p>";
        } else {
            echo "<p style='color: red;'>❌ Error adding '$columnName': " . $conn->error . "</p>";
        }
    }
}

// Columns to check and add for 'company_settings' table
$companyColumns = [
    'cgst_rate'  => "ALTER TABLE company_settings ADD COLUMN cgst_rate DECIMAL(5,2) DEFAULT 2.50",
    'sgst_rate'  => "ALTER TABLE company_settings ADD COLUMN sgst_rate DECIMAL(5,2) DEFAULT 2.50",
    'enable_tax' => "ALTER TABLE company_settings ADD COLUMN enable_tax TINYINT(1) DEFAULT 1"
];

echo "<h3>3. Updating 'company_settings' table:</h3>";
foreach ($companyColumns as $columnName => $alterQuery) {
    $checkQuery = "SHOW COLUMNS FROM company_settings LIKE '$columnName'";
    $result = $conn->query($checkQuery);
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: orange;'>⚠️ Column '$columnName' already exists in 'company_settings' table.</p>";
    } else {
        if ($conn->query($alterQuery)) {
            echo "<p style='color: green;'>✅ Successfully added '$columnName' to 'company_settings' table.</p>";
        } else {
            echo "<p style='color: red;'>❌ Error adding '$columnName': " . $conn->error . "</p>";
        }
    }
}

// Show current table structure
echo "<h3>Current Database Structure:</h3>";

echo "<h4>Bills Table Columns:</h4>";
$result = $conn->query("DESCRIBE bills");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 13px;'>";
    echo "<tr style='background: #f3f4f6;'><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $highlight = in_array($row['Field'], ['customer_id_type', 'customer_gstin', 'customer_aadhaar', 'shipping_address', 'is_shipping_same', 'subtotal', 'cgst_rate', 'cgst_amount', 'sgst_rate', 'sgst_amount']) ? 'background: #d1fae5;' : '';
        echo "<tr style='$highlight'><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Default']}</td></tr>";
    }
    echo "</table>";
}

echo "<h4>Bill Items Table Columns:</h4>";
$result = $conn->query("DESCRIBE bill_items");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 13px;'>";
    echo "<tr style='background: #f3f4f6;'><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $highlight = in_array($row['Field'], ['hsn_code', 'unit']) ? 'background: #d1fae5;' : '';
        echo "<tr style='$highlight'><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Default']}</td></tr>";
    }
    echo "</table>";
}

$conn->close();

echo "<hr>";
echo "<h3 style='color: green;'>✅ Database Update Complete!</h3>";
echo "<p><strong>You can now create bills with customer ID (GSTIN/Aadhaar) and tax calculations smoothly.</strong></p>";
echo "<p><a href='create_bill.php' style='padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>Go to Create Bill</a></p>";
?>