<?php
// header.php - Common navigation header
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<style>
    .navbar {
    background: #ffffff;
    border-bottom: 1px solid #ddd;
    padding: 10px 20px;
}

.nav-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-menu {
    display: flex;
    gap: 20px;
    list-style: none;
}

.mobile-menu-btn {
    display: none;
    font-size: 28px;
    cursor: pointer;
    padding: 5px 10px;
}

/* ---------- MOBILE VIEW ---------- */
@media (max-width: 420px) {
  
    .nav-menu {
        display: none;
        flex-direction: column;
        background: white;
        position: absolute;
        top: 60px;
        right: 10px;
        width: 180px;
        padding: 15px;
        border: 1px solid #ddd;
        box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        z-index: 100;
    }

    .nav-menu.show {
        display: flex;
    }

    .mobile-menu-btn {
        display: block;
    }
}

</style>
<nav class="navbar">
    <div class="nav-container">

        <!-- Brand -->
        <div class="nav-brand">
            <h1>📊 laserEdge Medtech</h1>
        </div>

        <!-- Mobile Menu Button (3-bar icon) -->
        <div class="mobile-menu-btn" onclick="toggleMenu()">
            ☰
        </div>

        <!-- Menu -->
        <ul class="nav-menu" id="navMenu">
            <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
            <li><a href="create_bill.php" class="nav-link">Create Bill</a></li>
            <li><a href="view_bills.php" class="nav-link">View Bills</a></li>
            <li><a href="reports.php" class="nav-link">Reports</a></li>
            <li class="nav-user">
                <span>👤 <?php echo $_SESSION['full_name']; ?></span>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </li>
        </ul>

    </div>
</nav>

<script>
function toggleMenu() {
    document.getElementById("navMenu").classList.toggle("show");
}
</script>
