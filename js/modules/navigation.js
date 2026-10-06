// NAVIGATION
// ═══════════════════════════════════════════════════════════════

function setupNavigation() {
    // Desktop nav tabs
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            switchSection(tab.dataset.section);
        });
    });

    // Mobile bottom nav
    document.querySelectorAll('.mobile-nav-item').forEach(item => {
        item.addEventListener('click', () => {
            switchSection(item.dataset.section);
        });
    });
}

function switchSection(section) {
    currentSection = section;

    // Update desktop tabs
    document.querySelectorAll('.nav-tab').forEach(t => {
        const selected = t.dataset.section === section;
        t.classList.toggle('active', selected);
        t.setAttribute('aria-selected', selected ? 'true' : 'false');
    });

    // Update mobile nav
    document.querySelectorAll('.mobile-nav-item').forEach(i => {
        const selected = i.dataset.section === section;
        i.classList.toggle('active', selected);
        i.setAttribute('aria-selected', selected ? 'true' : 'false');
    });

    // Toggle sections
    document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
    const target = document.getElementById('section' + section.charAt(0).toUpperCase() + section.slice(1));
    if (target) target.classList.remove('hidden');

    // Lazy load data
    if (section === 'dashboards' && !dashboardData) {
        loadDashboards();
    }
    if (section === 'dashboards' && CURRENT_USER.isMiembro && !miembroDashData) {
        loadMiembroDashboard();
    }
    if (section === 'ranking' && !rankingData) {
        loadRanking();
    }
    if (section === 'admin') {
        loadAdminPermissions();
    }
    if (section === 'tesorero' && !tesoreroDashData) {
        loadTesoreroDashboard();
    }
}

// ═══════════════════════════════════════════════════════════════
