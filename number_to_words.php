<?php
// number_to_words.php - AJAX endpoint for converting numeric amounts to words
require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

if (isset($_GET['amount'])) {
    $amount = floatval($_GET['amount']);
    echo numberToWords($amount);
} else {
    echo 'Zero Rupees Only';
}
