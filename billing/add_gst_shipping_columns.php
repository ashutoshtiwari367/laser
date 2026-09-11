<?php
// add_gst_shipping_columns.php - Add customer ID, GSTIN and shipping address columns
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Adding Customer ID (GSTIN / Aadhaar) & Shipping Address Columns</h2>";
echo "<hr>";

$conn = getDBConnection();

echo "<p style='color: green;'>✅ Connected to database</p>";

$columnsToAdd = [
    'customer_id_type' => "ALTER TABLE bills ADD COLUMN customer_id_type VARCHAR(20) DEFAULT 'none' AFTER customer_phone",
    'customer_gstin'   => "ALTER TABLE bills ADD COLUMN customer_gstin VARCHAR(20) AFTER customer_id_type",
    'customer_aadhaar' => "ALTER TABLE bills ADD COLUMN customer_aadhaar VARCHAR(20) AFTER customer_gstin",
    'shipping_address' => "ALTER TABLE bills ADD COLUMN shipping_address TEXT AFTER customer_address",
    'is_shipping_same' => "ALTER TABLE bills ADD COLUMN is_shipping_same TINYINT(1) DEFAULT 1 AFTER shipping_address"
];

foreach ($columnsToAdd as $col => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM bills LIKE '$col'");
    if ($check && $check->num_rows > 0) {
        echo "<p style='color: orange;'>⚠️ Column '$col' already exists!</p>";
    } else {
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Added '$col' column</p>";
        } else {
            echo "<p style='color: red;'>❌ Error adding '$col': " . $conn->error . "</p>";
        }
    }
}

// Show current table structure
echo "<h3>Current 'bills' Table Structure:</h3>";
$result = $conn->query("DESCRIBE bills");
echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    $highlight = in_array($row['Field'], ['customer_id_type', 'customer_gstin', 'customer_aadhaar', 'shipping_address', 'is_shipping_same']) ? 'background: #d1fae5;' : '';
    echo "<tr style='$highlight'><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Default']}</td></tr>";
}
echo "</table>";

$conn->close();

echo "<hr>";
echo "<h3 style='color: green;'>✅ Done!</h3>";
echo "<p><a href='create_bill.php' style='padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>Go to Create Bill</a></p>";
?>