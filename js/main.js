// ─── Init ───
window.COMPETICIONES = [];

async function loadCompeticiones() {
    try {
        const data = await apiGet('api/admin/getCompeticiones.php');
        if (data.success) {
            window.COMPETICIONES = data.competiciones || [];
            
            // Llenar selectores si existen
            const selects = [document.getElementById('competicionFilterTes'), document.getElementById('competicionFilterMem'), document.getElementById('competicionFilterPred')];
            selects.forEach(select => {
                if (select) {
                    select.innerHTML = '';
                    window.COMPETICIONES.forEach(comp => {
                        const opt = document.createElement('option');
                        opt.value = comp.Id;
                        opt.textContent = comp.Nombre + (comp.Activa ? ' (Activa)' : '');
                        if (comp.Activa) {
                            opt.selected = true;
                        }
                        select.appendChild(opt);
                    });
                }
            });
        }
    } catch (e) {
        console.error('Error cargando competiciones:', e);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    await loadCompeticiones();
    setupNavigation();
    setupUserMenu();
    setupModalAccessibility();
    setupSync();
    setupExport();
    loadPartidos();
    if (CURRENT_USER.isAdmin) {
        setupAdminCreateTable();
        setupAdminUsers();
        setupAdminSubTabs();
    }
});

// ═══════════════════════════════════════════════════════════════
