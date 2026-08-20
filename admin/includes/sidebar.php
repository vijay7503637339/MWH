<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark small">MWH</div>
        <div>
            <strong>Maan World</strong>
            <span>Hospitality</span>
        </div>
    </div>

    <nav class="admin-nav" aria-label="Admin navigation">
        <a class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
            <span class="nav-icon"><i class="bi bi-grid-1x2-fill"></i></span><span>Dashboard</span>
        </a>
        <a class="<?= $currentPage === 'hotels.php' ? 'active' : '' ?>" href="hotels.php">
            <span class="nav-icon"><i class="bi bi-buildings-fill"></i></span><span>Hotels</span>
        </a>
        <a class="<?= $currentPage === 'categories.php' ? 'active' : '' ?>" href="categories.php">
            <span class="nav-icon"><i class="bi bi-tags-fill"></i></span><span>Categories & Job Roles</span>
        </a>
        <a class="<?= $currentPage === 'vacancies.php' ? 'active' : '' ?>" href="vacancies.php">
            <span class="nav-icon"><i class="bi bi-briefcase-fill"></i></span><span>Vacancies</span>
        </a>
        <a class="<?= $currentPage === 'candidates.php' ? 'active' : '' ?>" href="candidates.php">
            <span class="nav-icon"><i class="bi bi-people-fill"></i></span><span>Candidates</span>
        </a>
        <a class="<?= $currentPage === 'applications.php' ? 'active' : '' ?>" href="applications.php">
            <span class="nav-icon"><i class="bi bi-file-earmark-check-fill"></i></span><span>Applications</span>
        </a>
        <a class="<?= $currentPage === 'attendance.php' ? 'active' : '' ?>" href="attendance.php">
            <span class="nav-icon"><i class="bi bi-clock-history"></i></span><span>Attendance</span>
        </a>
        <a class="<?= $currentPage === 'payments.php' ? 'active' : '' ?>" href="payments.php">
            <span class="nav-icon"><i class="bi bi-wallet2"></i></span><span>Payments</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="admin-profile">
            <div class="admin-avatar"><?= htmlspecialchars(strtoupper(substr($admin['name'], 0, 1))) ?></div>
            <div class="admin-profile-copy">
                <div class="admin-name"><?= htmlspecialchars($admin['name']) ?></div>
                <div class="admin-role"><?= htmlspecialchars($admin['role']) ?></div>
            </div>
        </div>
        <a href="../api/auth/logout.php" class="logout-link" id="logoutLink">
            <span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span><span>Logout</span>
        </a>
    </div>
</aside>
