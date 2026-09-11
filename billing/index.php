<?php
// index.php - Default entry point for billing system
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit();
?>
