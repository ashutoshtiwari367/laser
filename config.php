<?php
// config.php - Database configuration and common functions

session_start();

// Database configuration
$isLive = isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') === false && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false;

if ($isLive) {
    // Hostinger Live Database Configuration (Update with your Hostinger DB details)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u123456789_user'); // Hostinger DB Username
    define('DB_PASS', 'Your_Password');   // Hostinger DB Password
    define('DB_NAME', 'u123456789_billing'); // Hostinger DB Name
} else {
    // Local XAMPP Configuration
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'billing_system');
}

// Create database connection
function getDBConnection() {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn || $conn->connect_error) {
        $conn = @new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    }
    if (!$conn || $conn->connect_error) {
        die("Connection failed: " . ($conn ? $conn->connect_error : 'Unable to connect to database'));
    }
    
    $conn->set_charset("utf8mb4");
    ensureDatabaseSchema($conn);
    return $conn;
}

// Auto migration helper for missing per-item GST columns
function ensureDatabaseSchema($conn) {
    if (!$conn) return;
    $res = $conn->query("SHOW COLUMNS FROM bill_items LIKE 'cgst_rate'");
    if ($res && $res->num_rows == 0) {
        @$conn->query("ALTER TABLE bill_items ADD COLUMN cgst_rate DECIMAL(5,2) DEFAULT 0.00 AFTER price");
        @$conn->query("ALTER TABLE bill_items ADD COLUMN cgst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER cgst_rate");
        @$conn->query("ALTER TABLE bill_items ADD COLUMN sgst_rate DECIMAL(5,2) DEFAULT 0.00 AFTER cgst_amount");
        @$conn->query("ALTER TABLE bill_items ADD COLUMN sgst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER sgst_rate");
    }
}

// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Get company settings
function getCompanySettings() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM company_settings LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        $settings = $result->fetch_assoc();
    } else {
        // Return default values if no settings found
        $settings = [
            'company_name' => 'Laser Edge MedTech',
            'address' => '123 Business Street, City - 400001',
            'phone' => '+917618037434, +918090938659',
            'email' => 'laseredgemedtech@gmail.com',
            'gstin' => '09BXCPK2300M1ZL',
            'footer_note' => 'Thank you for your business!',
            'cgst_rate' => 2.50,
            'sgst_rate' => 2.50,
            'enable_tax' => 1
        ];
    }
    
    $conn->close();
    return $settings;
}

// Generate unique bill number
function generateBillNumber() {
    $conn = getDBConnection();
    $year = date('Y');
    $month = date('m');
    
    // Get the last bill number for current month
    $query = "SELECT bill_no FROM bills WHERE bill_no LIKE 'INV-$year$month%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Extract the last 4 digits and increment
        $lastNumber = intval(substr($row['bill_no'], -4));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    $billNo = 'INV-' . $year . $month . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    $conn->close();
    
    return $billNo;
}

// Format currency
function formatCurrency($amount) {
    return '₹' . number_format((float)$amount, 2);
}

// Convert number to words (Indian format)
function numberToWords($number) {
    $number = (float)$number;
    $decimal = round($number - floor($number), 2) * 100;
    $number = floor($number);
    
    $words = array(
        '0' => '', '1' => 'One', '2' => 'Two', '3' => 'Three', '4' => 'Four',
        '5' => 'Five', '6' => 'Six', '7' => 'Seven', '8' => 'Eight', '9' => 'Nine',
        '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve', '13' => 'Thirteen',
        '14' => 'Fourteen', '15' => 'Fifteen', '16' => 'Sixteen', '17' => 'Seventeen',
        '18' => 'Eighteen', '19' => 'Nineteen', '20' => 'Twenty', '30' => 'Thirty',
        '40' => 'Forty', '50' => 'Fifty', '60' => 'Sixty', '70' => 'Seventy',
        '80' => 'Eighty', '90' => 'Ninety'
    );
    
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    
    $result = '';
    
    if ($number == 0) {
        return 'Zero Rupees Only';
    }
    
    // Crores
    $crore = floor($number / 10000000);
    if ($crore > 0) {
        $result .= convertTwoDigits($crore, $words) . ' Crore ';
        $number %= 10000000;
    }
    
    // Lakhs
    $lakh = floor($number / 100000);
    if ($lakh > 0) {
        $result .= convertTwoDigits($lakh, $words) . ' Lakh ';
        $number %= 100000;
    }
    
    // Thousands
    $thousand = floor($number / 1000);
    if ($thousand > 0) {
        $result .= convertTwoDigits($thousand, $words) . ' Thousand ';
        $number %= 1000;
    }
    
    // Hundreds
    $hundred = floor($number / 100);
    if ($hundred > 0) {
        $result .= $words[$hundred] . ' Hundred ';
        $number %= 100;
    }
    
    // Remaining
    if ($number > 0) {
        $result .= convertTwoDigits($number, $words);
    }
    
    $result = trim($result) . ' Rupees';
    
    if ($decimal > 0) {
        $result .= ' and ' . convertTwoDigits($decimal, $words) . ' Paise';
    }
    
    return $result . ' Only';
}

function convertTwoDigits($number, $words) {
    if ($number < 20) {
        return $words[$number];
    }
    
    $tens = floor($number / 10) * 10;
    $units = $number % 10;
    
    return $words[$tens] . ($units > 0 ? ' ' . $words[$units] : '');
}

// Sanitize input
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

// Debug function
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}
?>