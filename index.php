<?php
// Root index.php - Forward to billing application
if (file_exists(__DIR__ . '/billing/index.php')) {
    header('Location: billing/index.php');
} else {
    header('Location: login.php');
}
exit();
?>
