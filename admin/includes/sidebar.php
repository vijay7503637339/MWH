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
        <div class="nav-section-label">MWH Hospitality</div>
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
        <a class="<?= in_array($currentPage, ['jobs.php','assign_job.php'], true) ? 'active' : '' ?>" href="jobs.php">
            <span class="nav-icon"><i class="bi bi-calendar2-check-fill"></i></span><span>Jobs & Assignments</span>
        </a>
        <a class="<?= $currentPage === 'attendance.php' ? 'active' : '' ?>" href="attendance.php">
            <span class="nav-icon"><i class="bi bi-clock-history"></i></span><span>Attendance</span>
        </a>
        <a class="<?= $currentPage === 'payments.php' ? 'active' : '' ?>" href="payments.php">
            <span class="nav-icon"><i class="bi bi-wallet2"></i></span><span>Payments</span>
        </a>
        <div class="nav-section-label">MWH Local</div>
        <a class="<?= $currentPage === 'local_contractors.php' ? 'active' : '' ?>" href="local_contractors.php">
            <span class="nav-icon"><i class="bi bi-briefcase"></i></span><span>Contractors</span>
        </a>
        <a class="<?= $currentPage === 'local_staff.php' ? 'active' : '' ?>" href="local_staff.php">
            <span class="nav-icon"><i class="bi bi-person-badge"></i></span><span>Local Staff / Chefs</span>
        </a>
        <a class="<?= $currentPage === 'local_requirements.php' ? 'active' : '' ?>" href="local_requirements.php">
            <span class="nav-icon"><i class="bi bi-clipboard-plus"></i></span><span>Requirements</span>
        </a>
        <a class="<?= $currentPage === 'notifications.php' ? 'active' : '' ?>" href="notifications.php">
            <span class="nav-icon"><i class="bi bi-bell-fill"></i></span><span>Notifications</span>
        </a>
        <a class="<?= $currentPage === 'account_deletion_requests.php' ? 'active' : '' ?>" href="account_deletion_requests.php">
            <span class="nav-icon"><i class="bi bi-person-x-fill"></i></span><span>Account Deletion</span>
        </a>
        <div class="nav-section-label">Account</div>
        <a class="<?= $currentPage === 'change_password.php' ? 'active' : '' ?>" href="change_password.php">
            <span class="nav-icon"><i class="bi bi-shield-lock-fill"></i></span><span>Change Password</span>
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

<style>
.nav-section-label{margin:18px 14px 7px;color:#95a0b2;font-size:9px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
</style>

<?php if ($currentPage === 'candidates.php'): ?>
<style>
.candidate-availability-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:10px;font-weight:800}
.candidate-availability-badge.available{background:#effcf6;color:#087b50}
.candidate-availability-badge.unavailable{background:#fff1f2;color:#c9363d}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tables = Array.from(document.querySelectorAll('.data-table'));
    const table = tables.find(function (candidateTable) {
        return Array.from(candidateTable.querySelectorAll('thead th')).some(function (header) {
            return header.textContent.trim().toLowerCase() === 'documents';
        });
    });
    if (!table) return;
    const headers = table.querySelectorAll('thead th');
    let availabilityIndex = -1;
    headers.forEach(function (header, index) {
        if (header.textContent.trim().toLowerCase() === 'documents') {
            availabilityIndex = index;
            header.textContent = 'Availability';
        }
    });
    if (availabilityIndex === -1) return;
    fetch('candidate_availability_data.php', {credentials:'same-origin',cache:'no-store'})
        .then(function(response){return response.ok?response.json():null;})
        .then(function(payload){
            if(!payload||!payload.success||!payload.availability)return;
            table.querySelectorAll('tbody tr').forEach(function(row){
                const viewLink=row.querySelector('a[href*="candidates.php?view="]');
                if(!viewLink)return;
                const match=viewLink.getAttribute('href').match(/[?&]view=(\d+)/);
                if(!match)return;
                const status=payload.availability[match[1]]||'available';
                const cell=row.cells[availabilityIndex];
                if(!cell)return;
                cell.innerHTML='<span class="candidate-availability-badge '+status+'"><i class="bi '+(status==='available'?'bi-check-circle-fill':'bi-x-circle-fill')+'"></i> '+(status==='available'?'Available':'Unavailable')+'</span>';
            });
        }).catch(function(){});
});
</script>
<?php endif; ?>
