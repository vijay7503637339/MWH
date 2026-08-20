(function () {
    'use strict';

    const file = (location.pathname.split('/').pop() || '').toLowerCase();
    const main = document.querySelector('main.content');
    if (!main) return;

    const configs = {
        'hotels.php': {
            kicker: 'WORKFORCE MANAGEMENT', title: 'Hotels', countLabel: 'Hotels',
            description: 'Manage registered hotel partners and their workforce requirements.',
            icon: 'bi-building', search: 'Search hotel, city or contact',
            stats: [['All Hotels', 'total'], ['Active', 'active'], ['Inactive', 'inactive'], ['Vacancies', 'vacancies'], ['Records', 'records']]
        },
        'vacancies.php': {
            kicker: 'WORKFORCE MANAGEMENT', title: 'Vacancies', countLabel: 'Vacancies',
            description: 'Create and manage hotel job openings from one place.',
            icon: 'bi-briefcase', search: 'Search vacancy, hotel, category',
            stats: [['All Vacancies', 'total'], ['Published', 'published'], ['Draft', 'draft'], ['Closed', 'closed'], ['Applications', 'applications']]
        },
        'candidates.php': {
            kicker: 'WORKFORCE MANAGEMENT', title: 'Candidates', countLabel: 'Candidates',
            description: 'Review candidate profiles, documents and verification status.',
            icon: 'bi-people', search: 'Search name, mobile, email or city',
            stats: [['All Candidates', 'total'], ['Verified', 'verified'], ['Pending', 'pending_verification'], ['Rejected', 'rejected'], ['Blocked', 'blocked']]
        },
        'categories.php': {
            kicker: 'WORKFORCE MANAGEMENT', title: 'Categories & Job Roles', countLabel: 'Categories',
            description: 'Manage job categories and the roles available inside each category.',
            icon: 'bi-tags', search: '',
            stats: [['Categories', 'categories'], ['Job Roles', 'roles'], ['Active Categories', 'activeCategories'], ['Active Roles', 'activeRoles'], ['Records', 'records']]
        }
    };

    if (file === 'dashboard.php') {
        main.classList.add('mwh-standard-dashboard');
        return;
    }

    const cfg = configs[file];
    if (!cfg) return;

    main.classList.add('mwh-standard-module');
    const topbar = main.querySelector(':scope > .topbar');
    if (!topbar) return;

    const copy = topbar.querySelector(':scope > div:first-child');
    const title = copy && copy.querySelector('h1');
    const description = copy && copy.querySelector('p.muted');
    if (!title) return;

    title.textContent = cfg.title;
    if (description) description.textContent = cfg.description;

    const kicker = document.createElement('div');
    kicker.className = 'mwh-module-kicker';
    kicker.innerHTML = '<i class="bi ' + cfg.icon + '"></i> ' + cfg.kicker;
    copy.insertBefore(kicker, title);

    const countCard = document.createElement('div');
    countCard.className = 'mwh-module-count';
    countCard.innerHTML = '<strong data-mwh-total>0</strong><span>' + cfg.countLabel + '</span>';
    topbar.appendChild(countCard);

    const stats = document.createElement('div');
    stats.className = 'mwh-module-stats';
    cfg.stats.forEach(function (item, index) {
        const card = document.createElement('div');
        card.className = 'mwh-module-stat-card';
        card.innerHTML = '<div class="mwh-stat-icon"><i class="bi ' + statIcon(item[1], index) + '"></i></div>' +
            '<div><strong data-mwh-stat="' + item[1] + '">0</strong><span>' + item[0] + '</span></div>';
        stats.appendChild(card);
    });
    topbar.insertAdjacentElement('afterend', stats);

    requestAnimationFrame(function () { updateStats(cfg); });

    main.querySelectorAll('.filter-form').forEach(function (form) {
        form.classList.add('mwh-standard-filter');
        const input = form.querySelector('input[type="search"]');
        if (input && cfg.search) input.placeholder = cfg.search + '...';
    });

    if (file === 'categories.php') {
        main.querySelectorAll('.category-card').forEach(function (card) { card.classList.add('mwh-category-card'); });
        main.querySelectorAll('.role-row').forEach(function (row) { row.classList.add('mwh-role-row'); });
    }

    function visibleRows() {
        return Array.from(main.querySelectorAll('table tbody tr')).filter(function (row) {
            return !row.classList.contains('table-empty') && row.querySelector('td');
        });
    }

    function updateStats(config) {
        const values = { total: 0, active: 0, inactive: 0, published: 0, draft: 0, closed: 0, verified: 0, pending_verification: 0, rejected: 0, blocked: 0, applications: 0, vacancies: 0, categories: 0, roles: 0, activeCategories: 0, activeRoles: 0, records: 0 };
        const rows = visibleRows();
        const badges = Array.from(main.querySelectorAll('.status-badge'));

        if (file === 'categories.php') {
            const cats = Array.from(main.querySelectorAll('.category-card'));
            const roles = Array.from(main.querySelectorAll('.role-row'));
            values.categories = cats.length;
            values.roles = roles.length;
            values.activeCategories = cats.filter(c => c.querySelector('.status-badge.active')).length;
            values.activeRoles = roles.filter(r => r.querySelector('.status-badge.active')).length;
            values.records = values.categories + values.roles;
            values.total = values.categories;
        } else {
            values.total = rows.length;
            values.records = rows.length;
            badges.forEach(function (badge) {
                const cls = Array.from(badge.classList).find(c => Object.prototype.hasOwnProperty.call(values, c));
                if (cls) values[cls]++;
            });
            if (file === 'hotels.php') values.vacancies = Array.from(main.querySelectorAll('.vacancy-count')).reduce((n, el) => n + (parseInt(el.textContent, 10) || 0), 0);
        }

        config.stats.forEach(function (item) {
            const el = main.querySelector('[data-mwh-stat="' + item[1] + '"]');
            if (el) el.textContent = format(values[item[1]] || 0);
        });
        const total = main.querySelector('[data-mwh-total]');
        if (total) total.textContent = format(values.total || 0);
    }

    function format(value) { return Number(value || 0).toLocaleString('en-IN'); }
    function statIcon(type, index) {
        const icons = { total: 'bi-grid', active: 'bi-check-circle', inactive: 'bi-x-circle', published: 'bi-check2-circle', draft: 'bi-file-earmark', closed: 'bi-lock', verified: 'bi-patch-check', pending_verification: 'bi-hourglass-split', rejected: 'bi-x-circle', blocked: 'bi-slash-circle', applications: 'bi-file-earmark-text', vacancies: 'bi-briefcase', categories: 'bi-tags', roles: 'bi-person-workspace', activeCategories: 'bi-check2-square', activeRoles: 'bi-person-check', records: 'bi-bar-chart' };
        return icons[type] || ['bi-grid', 'bi-check-circle', 'bi-bar-chart'][index % 3];
    }
})();
