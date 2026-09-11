<?php
// config.php - Database configuration and common functions

session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u447123054_billing_system');
define('DB_PASS', 'Rakesh#123@456');
define('DB_NAME', 'u447123054_billing_system');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
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

// Generate bill number - Financial Year wise reset (INV-001 format)
// Har saal 1 April ko INV-001 se start hoga automatically
function generateBillNumber() {
    $conn = getDBConnection();

    // --------------------------------------------------
    // Current financial year calculate karo
    // April - March = Indian Financial Year
    // --------------------------------------------------
    $month = (int)date('n'); // 1-12
    $year  = (int)date('Y');

    if ($month >= 4) {
        // April se December: FY current year se start
        $fy_start_year = $year;
        $fy_end_year   = $year + 1;
    } else {
        // January se March: FY pichle year se start
        $fy_start_year = $year - 1;
        $fy_end_year   = $year;
    }

    $fy_start_date = $fy_start_year . '-04-01'; // e.g. 2025-04-01
    $fy_end_date   = $fy_end_year   . '-03-31'; // e.g. 2026-03-31

    // --------------------------------------------------
    // Is financial year ke andar kitne bills hain count karo
    // Sirf wahi bills jo INV-001 format mein hain (new format)
    // Purane INV-2025120001 format wale bills count mein nahi aayenge
    // --------------------------------------------------
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count FROM bills 
         WHERE bill_date >= ? 
         AND bill_date <= ? 
         AND bill_no LIKE 'INV-%'
         AND LENGTH(bill_no) <= 7"
    );
    // INV-001 = 7 characters, INV-999 = 7 characters
    // Purana format INV-2025120001 = 15 characters, toh woh exclude ho jayega

    $stmt->bind_param("ss", $fy_start_date, $fy_end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $next_number = (int)$result['count'] + 1;

    $conn->close();

    // Format: INV-001, INV-002, ... INV-999
    return 'INV-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
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