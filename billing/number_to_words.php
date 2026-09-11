<?php
// number_to_words.php - Convert amount to words for AJAX request
require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

if (isset($_GET['amount'])) {
    $amount = floatval($_GET['amount']);
    echo numberToWords($amount);
} else {
    echo 'Zero Rupees Only';
}
?>
