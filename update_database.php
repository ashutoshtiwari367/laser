<?php
// update_database.php - Complete Database Schema Migration & Installer
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Setup & Schema Migration</h2>";
echo "<hr>";

$conn = getDBConnection();

echo "<p style='color: green;'>✅ Connected to database successfully</p>";

// 1. Create users table
$tableUsers = "CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($tableUsers)) {
    echo "<p style='color: green;'>✅ Table 'users' verified</p>";
    
    // Insert default admin user if empty
    $checkUser = $conn->query("SELECT id FROM users LIMIT 1");
    if ($checkUser && $checkUser->num_rows == 0) {
        $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name) VALUES ('admin', ?, 'Admin User')");
        $stmt->bind_param("s", $defaultPass);
        $stmt->execute();
        echo "<p style='color: blue;'>👤 Default Admin User created (Username: <strong>admin</strong> | Password: <strong>admin123</strong>)</p>";
    }
}

// 2. Create company_settings table
$tableCompany = "CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(100) DEFAULT 'LaserEdge MedTech',
  `address` TEXT,
  `phone` VARCHAR(50),
  `email` VARCHAR(100),
  `gstin` VARCHAR(20),
  `footer_note` TEXT,
  `cgst_rate` DECIMAL(5,2) DEFAULT 2.50,
  `sgst_rate` DECIMAL(5,2) DEFAULT 2.50,
  `enable_tax` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($tableCompany)) {
    echo "<p style='color: green;'>✅ Table 'company_settings' verified</p>";
}

// 3. Create bills table
$tableBills = "CREATE TABLE IF NOT EXISTS `bills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bill_no` VARCHAR(50) NOT NULL UNIQUE,
  `bill_date` DATE NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(20),
  `customer_id_type` VARCHAR(20) DEFAULT 'none',
  `customer_gstin` VARCHAR(20),
  `customer_aadhaar` VARCHAR(20),
  `customer_address` TEXT NOT NULL,
  `shipping_address` TEXT,
  `is_shipping_same` TINYINT(1) DEFAULT 1,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cgst_rate` DECIMAL(5,2) DEFAULT 2.50,
  `cgst_amount` DECIMAL(10,2) DEFAULT 0.00,
  `sgst_rate` DECIMAL(5,2) DEFAULT 2.50,
  `sgst_amount` DECIMAL(10,2) DEFAULT 0.00,
  `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('Paid', 'Pending', 'Partial') DEFAULT 'Pending',
  `payment_received` DECIMAL(10,2) DEFAULT 0.00,
  `notes` TEXT,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($tableBills)) {
    echo "<p style='color: green;'>✅ Table 'bills' verified</p>";
}

// 4. Create bill_items table
$tableItems = "CREATE TABLE IF NOT EXISTS `bill_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bill_id` INT NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `hsn_code` VARCHAR(20),
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit` VARCHAR(20) DEFAULT 'Qty',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cgst_rate` DECIMAL(5,2) DEFAULT 0.00,
  `cgst_amount` DECIMAL(10,2) DEFAULT 0.00,
  `sgst_rate` DECIMAL(5,2) DEFAULT 0.00,
  `sgst_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($tableItems)) {
    echo "<p style='color: green;'>✅ Table 'bill_items' verified</p>";
}

// Ensure per-item GST columns exist
ensureDatabaseSchema($conn);

$conn->close();

echo "<hr>";
echo "<h3 style='color: green;'>✅ Database Setup Complete!</h3>";
echo "<p><a href='login.php' style='padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
?>