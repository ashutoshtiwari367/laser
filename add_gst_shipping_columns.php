<?php
// add_gst_shipping_columns.php - Add customer GSTIN and shipping address columns
// RUN THIS FILE ONCE to fix the error!

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Adding Customer GSTIN & Shipping Address Columns</h2>";
echo "<hr>";

// Database connection
$conn = new mysqli('localhost', 'root', '', 'billing_system');

if ($conn->connect_error) {
    die("<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>");
}

echo "<p style='color: green;'>✅ Connected to database</p>";

// Check if customer_gstin column exists
$check = $conn->query("SHOW COLUMNS FROM bills LIKE 'customer_gstin'");

if ($check->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Columns already exist!</p>";
} else {
    echo "<p style='color: blue;'>📝 Adding new columns...</p>";
    
    // Add customer_gstin column
    $sql1 = "ALTER TABLE bills ADD COLUMN customer_gstin VARCHAR(20) AFTER customer_phone";
    if ($conn->query($sql1)) {
        echo "<p style='color: green;'>✅ Added 'customer_gstin' column</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding customer_gstin: " . $conn->error . "</p>";
    }
    
    // Add shipping_address column
    $sql2 = "ALTER TABLE bills ADD COLUMN shipping_address TEXT AFTER customer_address";
    if ($conn->query($sql2)) {
        echo "<p style='color: green;'>✅ Added 'shipping_address' column</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding shipping_address: " . $conn->error . "</p>";
    }
    
    // Add is_shipping_same column
    $sql3 = "ALTER TABLE bills ADD COLUMN is_shipping_same TINYINT(1) DEFAULT 1 AFTER shipping_address";
    if ($conn->query($sql3)) {
        echo "<p style='color: green;'>✅ Added 'is_shipping_same' column</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding is_shipping_same: " . $conn->error . "</p>";
    }
}

// Show current table structure
echo "<h3>Current 'bills' Table Structure:</h3>";
$result = $conn->query("DESCRIBE bills");
echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    $highlight = in_array($row['Field'], ['customer_gstin', 'shipping_address', 'is_shipping_same']) ? 'background: #d1fae5;' : '';
    echo "<tr style='$highlight'><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Default']}</td></tr>";
}
echo "</table>";

$conn->close();

echo "<hr>";
echo "<h3 style='color: green;'>✅ Done!</h3>";
echo "<p><strong>Ab aap bill create kar sakte hain with Customer GSTIN and Shipping Address!</strong></p>";
echo "<p><a href='create_bill.php' style='padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>Go to Create Bill</a></p>";
echo "<br>";
echo "<p style='color: red;'><strong>DELETE THIS FILE after running!</strong></p>";
?>