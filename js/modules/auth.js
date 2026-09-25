// ═══════════════════════════════════════════════════════════════
// USER MENU
// ═══════════════════════════════════════════════════════════════

function setupUserMenu() {
    const btn = document.getElementById('userMenuBtn');
    const dropdown = document.getElementById('userDropdown');
    if (!btn || !dropdown) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('active');
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.user-menu')) {
            dropdown.classList.remove('active');
            btn.setAttribute('aria-expanded', 'false');
        }
    });

    // Close modals on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeChampionModal();
            closeBetMatchModal();
            if (typeof closeAdminPrediccionModal === 'function') closeAdminPrediccionModal();
            if (typeof closeEditPenalizacionModal === 'function') closeEditPenalizacionModal();
        }
    });

    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
                selectedBetOption = null;
            }
        });
    });
}

async function logout() {
    try { await apiGet('api/auth/logout.php'); } catch (e) { /* silent */ }
    window.location.href = 'login.php';
}

// ═══════════════════════════════════════════════════════════════
// MODAL ACCESSIBILITY — foco atrapado dentro del diálogo abierto y
// devuelto al disparador al cerrarlo. Reacciona a la clase "active"
// en vez de engancharse a cada open*Modal(), que viven repartidas
// en dashboards.js / adminPredicciones.js / adminPenalizaciones.js.
// ═══════════════════════════════════════════════════════════════

function getFocusableElements(container) {
    const selector = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
    return Array.from(container.querySelectorAll(selector)).filter(el => el.offsetParent !== null);
}

function setupModalAccessibility() {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        let triggerEl = null;
        const content = modal.querySelector('.modal-content');
        if (content && !content.hasAttribute('tabindex')) content.setAttribute('tabindex', '-1');

        const observer = new MutationObserver(() => {
            const isOpen = modal.classList.contains('active');
            if (isOpen) {
                triggerEl = document.activeElement;
                const focusable = getFocusableElements(modal);
                (focusable[0] || modal.querySelector('.modal-content'))?.focus();
            } else if (triggerEl) {
                triggerEl.focus();
                triggerEl = null;
            }
            // Bloquea el scroll del body mientras cualquier modal esté abierto
            // (si no, en móvil el fondo se desplaza detrás del bottom-sheet).
            const anyOpen = document.querySelector('.modal-overlay.active');
            document.body.classList.toggle('modal-open', !!anyOpen);
        });
        observer.observe(modal, { attributes: true, attributeFilter: ['class'] });

        modal.addEventListener('keydown', (e) => {
            if (e.key !== 'Tab') return;
            const focusable = getFocusableElements(modal);
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    });
}
