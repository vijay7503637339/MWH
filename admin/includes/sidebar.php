<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark small">MWH</div>
        <div>
            <strong>Maan World</strong>
            <span>Hospitality</span>
        </div>
    </div>

    <nav>
        <a class="active" href="dashboard.php">Dashboard</a>
        <a href="hotels.php">Hotels</a>
        <a href="vacancies.php">Vacancies</a>
        <a href="candidates.php">Candidates</a>
        <a href="applications.php">Applications</a>
        <a href="attendance.php">Attendance</a>
        <a href="payments.php">Payments</a>
    </nav>

    <div class="sidebar-bottom">
        <div class="admin-name"><?= htmlspecialchars($admin['name']) ?></div>
        <div class="admin-role"><?= htmlspecialchars($admin['role']) ?></div>
        <a href="../api/auth/logout.php" class="logout-link" id="logoutLink">Logout</a>
    </div>
</aside>
