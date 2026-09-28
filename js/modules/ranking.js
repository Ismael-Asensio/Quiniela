// RANKING — Con selector de tabla
// ═══════════════════════════════════════════════════════════════

async function loadRanking(forceTabla) {
    const container = document.getElementById('rankingContainer');
    container.innerHTML = '<div class="loading"><div class="spinner"></div>Cargando ranking...</div>';

    try {
        const params = new URLSearchParams();
        if (forceTabla) {
            params.set('idTabla', forceTabla);
        } else if (selectedRankingTabla) {
            params.set('idTabla', selectedRankingTabla);
        }

        const data = await apiGet('api/dashboards/getRanking.php?' + params.toString());

        // Populate table selector
        populateRankingTables(data.tablasDisponibles || [], data.tablaActual);

        if (data.sinTablas || data.accesoRestringido || (data.error && !data.ranking)) {
            container.innerHTML = `
            <div class="lock-screen">
                <div class="lock-screen-icon">${data.sinTablas ? '' : ''}</div>
                <div class="lock-screen-title">${data.sinTablas ? 'Sin Tablas' : 'Acceso Restringido'}</div>
                <div class="lock-screen-desc">${escapeHtml(data.error || 'No tienes permiso para ver esta sección.')}</div>
            </div>`;
            const exportBtn = document.getElementById('exportRankingBtn');
            if (exportBtn) exportBtn.style.display = 'none';
            return;
        }

        rankingData = data;
        const exportBtn = document.getElementById('exportRankingBtn');
        if (exportBtn) {
            exportBtn.style.display = data.puedeExportar ? 'inline-flex' : 'none';
        }
        renderRanking();
        updateQuickStats();
    } catch (e) {
        container.innerHTML = '<div class="loading">Error al cargar ranking</div>';
        const exportBtn = document.getElementById('exportRankingBtn');
        if (exportBtn) exportBtn.style.display = 'none';
    }
}

function populateRankingTables(tablas, tablaActual) {
    const select = document.getElementById('rankingTablaSelect');
    if (!select) return;

    select.innerHTML = '';

    if (tablas.length === 0) {
        select.innerHTML = '<option value="">Sin tablas disponibles</option>';
        return;
    }

    tablas.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.Id;
        const faseLabel = (t.FaseInicio && t.FaseInicio !== 'Grupos') ? ` (desde ${t.FaseInicio})` : '';
        opt.textContent = t.Nombre + faseLabel;
        if (tablaActual && t.Id == tablaActual.Id) {
            opt.selected = true;
            selectedRankingTabla = t.Id;
        }
        select.appendChild(opt);
    });

    select.onchange = () => {
        selectedRankingTabla = parseInt(select.value);
        rankingData = null;
        dashboardData = null;
        loadRanking(selectedRankingTabla);
        updateQuickStats();
    };
}

function renderRanking() {
    if (!rankingData) return;
    const container = document.getElementById('rankingContainer');
    const ranking = rankingData.ranking || [];
    const tablaActual = rankingData.tablaActual;

    if (ranking.length === 0) {
        container.innerHTML = '<p class="empty-text">Sin participantes en esta tabla</p>';
        return;
    }

    let html = '';
    const faseInicio = rankingData.faseInicio || 'Grupos';

    if (tablaActual) {
        html += `<div class="ranking-table-name">${escapeHtml(tablaActual.Nombre)}`;
        if (faseInicio !== 'Grupos') {
            html += ` <span class="fase-badge"> Desde ${escapeHtml(faseInicio)}</span>`;
        }
        html += `</div>`;
    }

    html += `
    <table class="ranking-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Jugador</th>
                <th>Puntos</th>
                <th>Pred.</th>
                <th>Aciertos</th>
                <th>Exactos</th>
                <th>%</th>
            </tr>
        </thead>
        <tbody>`;

    ranking.forEach(r => {
        const posClass = r.Posicion <= 3 ? `top-${r.Posicion}` : '';
        const rowClass = r.EsUsuarioActual ? 'current-user' : '';
        const medal = r.Posicion === 1 ? '' : r.Posicion === 2 ? '' : r.Posicion === 3 ? '' : '';

        html += `
        <tr class="${rowClass}">
            <td class="ranking-pos ${posClass}">${medal || r.Posicion}</td>
            <td class="ranking-name">
                ${escapeHtml(r.NombreCompleto || r.NombreUsuario)}
                <span class="ranking-name-user">@${escapeHtml(r.NombreUsuario)}</span>
            </td>
            <td class="ranking-points">${r.PuntosTotales}</td>
            <td class="ranking-stat">${r.PrediccionesRealizadas}</td>
            <td class="ranking-stat">${r.Aciertos || 0}</td>
            <td class="ranking-stat">${r.AciertosExactos || 0}</td>
            <td class="ranking-stat">${r.PorcentajeAcierto}%</td>
        </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

// ═══════════════════════════════════════════════════════════════
// EXPORT RANKING
// ═══════════════════════════════════════════════════════════════

function setupExport() {
    const btn = document.getElementById('exportRankingBtn');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const rankingCard = document.querySelector('#sectionRanking .panel');
        if (!rankingCard) return;

        btn.disabled = true;
        btn.innerHTML = 'Exportando...';

        html2canvas(rankingCard, {
            backgroundColor: '#111827',
            scale: 2,
            logging: false,
            useCORS: true,
            onclone: (clonedDoc) => {
                const clonedBtn = clonedDoc.getElementById('exportRankingBtn');
                if (clonedBtn) clonedBtn.style.display = 'none';

                const clonedSelect = clonedDoc.querySelector('.ranking-actions .table-selector');
                if (clonedSelect) clonedSelect.style.display = 'none';

                const clonedPanel = clonedDoc.querySelector('.panel');
                if (clonedPanel) {
                    clonedPanel.style.border = '1px solid rgba(255,255,255,0.15)';
                    clonedPanel.style.borderRadius = '16px';
                    clonedPanel.style.padding = '30px';
                }
            }
        }).then(canvas => {
            try {
                const imgData = canvas.toDataURL('image/png');
                const link = document.createElement('a');
                const tablaName = rankingData?.tablaActual?.Nombre || 'General';
                link.download = `Puntuaciones_${tablaName}_Quiniela2026_${new Date().toISOString().slice(0, 10)}.png`;
                link.href = imgData;
                link.click();
                showToast('Tabla exportada como imagen con éxito!');
            } catch (err) {
                console.error(err);
                showToast('Error al generar la imagen.');
            }
            btn.disabled = false;
            btn.innerHTML = 'Exportar Imagen';
        }).catch(err => {
            console.error(err);
            showToast('Error de exportación');
            btn.disabled = false;
            btn.innerHTML = 'Exportar Imagen';
        });
    });
}

// ═══════════════════════════════════════════════════════════════
// QUICK STATS UPDATE
// ═══════════════════════════════════════════════════════════════

async function updateQuickStats() {
    try {
        const params = new URLSearchParams();
        if (selectedRankingTabla) {
            params.set('idTabla', selectedRankingTabla);
        }
        const data = await apiGet('api/dashboards/getDashboards.php?' + params.toString());
        document.getElementById('statPuntos').textContent = data.general?.PuntosTotales || 0;
        document.getElementById('statPosicion').textContent = data.posicionRanking || '-';
    } catch (e) { /* silent */ }
}

// ═══════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════

function formatDateLabel(dateStr) {
    if (!dateStr) return '';
    try {
        const [y, m, d] = dateStr.split('-');
        const dt = new Date(parseInt(y), parseInt(m) - 1, parseInt(d));
        const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return `${dias[dt.getDay()]} ${parseInt(d)} ${meses[parseInt(m) - 1]} ${y}`;
    } catch (e) {
        return dateStr;
    }
}

function formatTime(dateTimeStr) {
    if (!dateTimeStr) return '';
    const parts = dateTimeStr.split(' ');
    if (parts.length >= 2) {
        return parts[1].substring(0, 5); // HH:MM
    }
    return '';
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showToast(msg) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = msg;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
