// DASHBOARDS
// ═══════════════════════════════════════════════════════════════

async function loadDashboards() {
    try {
        const params = new URLSearchParams();
        if (selectedRankingTabla) {
            params.set('idTabla', selectedRankingTabla);
        }
        dashboardData = await apiGet('api/dashboards/getDashboards.php?' + params.toString());
        renderDashboards();
    } catch (e) {
        console.error('Error loading dashboards:', e);
    }
}

function renderDashboards() {
    if (!dashboardData) return;
    const g = dashboardData.general;

    document.getElementById('dashTotal').textContent = g.PuntosTotales || 0;
    document.getElementById('dashPredicciones').textContent = g.TotalPredicciones || 0;
    document.getElementById('dashExactos').textContent = g.AciertosExactos || 0;
    document.getElementById('dashAciertos').textContent = g.Aciertos || 0;

    // Chart por día
    renderChart(dashboardData.porDia || []);

    // Desglose por partido
    renderDesglose(dashboardData.porPartido || []);
}

function renderChart(data) {
    const container = document.getElementById('chartPorDia');
    if (!data.length) {
        container.innerHTML = '<p class="empty-text">Sin datos de puntos por día</p>';
        return;
    }

    const maxPts = Math.max(...data.map(d => d.Puntos), 1);

    let html = '';
    data.forEach(d => {
        const heightPct = (d.Puntos / maxPts) * 100;
        const dateShort = d.Fecha.substring(5); // MM-DD
        html += `
        <div class="chart-bar-wrapper">
            <span class="chart-bar-value">${d.Puntos}</span>
            <div class="chart-bar" style="height:${Math.max(heightPct, 3)}%"></div>
            <span class="chart-bar-label">${dateShort}</span>
        </div>`;
    });

    container.innerHTML = html;
}

function renderDesglose(data) {
    const container = document.getElementById('desglosePorPartido');
    if (!data.length) {
        container.innerHTML = '<p class="empty-text">Sin partidos evaluados aún</p>';
        return;
    }

    let html = '';
    data.forEach(d => {
        let tipoClass = 'fallo';
        if (d.TipoAcierto === 'exacto') tipoClass = 'exacto';
        else if (d.TipoAcierto === 'resultado') tipoClass = 'resultado';

        html += `
        <div class="desglose-item">
            <div class="desglose-match">
                <span class="partido-fase" style="font-size:10px">${escapeHtml(d.Fase)}</span>
                <strong>${escapeHtml(d.NombreLocal || '?')} vs ${escapeHtml(d.NombreVisitante || '?')}</strong>
            </div>
            <div class="desglose-scores">
                <span class="desglose-pred">Tu: ${d.PredLocal}-${d.PredVisitante}</span>
                <span class="desglose-vs">vs</span>
                <span class="desglose-real">Real: ${d.RealLocal}-${d.RealVisitante}</span>
            </div>
            <span class="puntos-obtenidos ${tipoClass}">
                ${d.PuntosObtenidos} pts
            </span>
        </div>`;
    });

    container.innerHTML = html;
}

// ═══════════════════════════════════════════════════════════════
// MIEMBRO DASHBOARD — Métricas financieras
// ═══════════════════════════════════════════════════════════════

async function loadMiembroDashboard() {
    try {
        miembroDashData = await apiGet('api/dashboards/getMiembroDashboard.php');
        
        if (!miembroDashData.success) return;
        
        const r = miembroDashData.resumen;
        const el = (id) => document.getElementById(id);
        
        if (el('dashGanado')) el('dashGanado').textContent = '$' + r.TotalGanado.toFixed(2);
        if (el('dashPerdido')) el('dashPerdido').textContent = '$' + r.TotalPerdido.toFixed(2);
        if (el('dashNeto')) el('dashNeto').textContent = (r.GananciaNeta >= 0 ? '+$' : '-$') + Math.abs(r.GananciaNeta).toFixed(2);
        if (el('dashPendientes')) el('dashPendientes').textContent = r.Pendientes;
        
        // Render historial
        renderHistorialApuestas(miembroDashData.apuestasPartidos || []);
        renderApuestasCampeon(miembroDashData.apuestasCampeon || []);
        
        // Save teams for champion modal
        championTeams = miembroDashData.equipos || [];
        
        // Cargar gráficos de miembros
        loadMemberCharts();
    } catch (e) {
        console.error('Error loading miembro dashboard:', e);
    }
}

// ═══════════════════════════════════════════════════════════════
// GRÁFICOS DE MIEMBRO (Chart.js)
// ═══════════════════════════════════════════════════════════════

let chartMemberCampeonInstance = null;
let chartMemberPartidosInstance = null;

async function loadMemberCharts() {
    try {
        const data = await apiGet('api/dashboards/getMemberCharts.php');
        if (!data.success) return;

        renderChartMemberCampeon(data.campeon);
        renderChartMemberPartidos(data.partidos);
    } catch (e) {
        console.error('Error cargando graficos de miembro', e);
    }
}

function renderChartMemberCampeon(campeonData) {
    const ctx = document.getElementById('chartMemberCampeon');
    if (!ctx) return;

    if (chartMemberCampeonInstance) {
        chartMemberCampeonInstance.destroy();
    }

    if (!campeonData || campeonData.length === 0) {
        // Show empty state
        const container = ctx.parentElement;
        let emptyMsg = container.querySelector('.empty-chart-msg');
        if (!emptyMsg) {
            emptyMsg = document.createElement('div');
            emptyMsg.className = 'empty-chart-msg empty-text';
            emptyMsg.style.position = 'absolute';
            emptyMsg.style.top = '50%';
            emptyMsg.style.left = '50%';
            emptyMsg.style.transform = 'translate(-50%, -50%)';
            emptyMsg.textContent = 'Sin datos suficientes';
            container.appendChild(emptyMsg);
        }
        return;
    } else {
        const container = ctx.parentElement;
        const emptyMsg = container.querySelector('.empty-chart-msg');
        if (emptyMsg) emptyMsg.remove();
    }

    const labels = campeonData.map(d => d.Equipo);
    const montos = campeonData.map(d => d.MontoTotal);
    const personas = campeonData.map(d => d.CantidadPersonas);

    chartMemberCampeonInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: montos,
                backgroundColor: [
                    '#4ade80', '#3b82f6', '#facc15', '#f87171', '#c084fc',
                    '#2dd4bf', '#fb923c', '#a3e635', '#60a5fa', '#f472b6'
                ],
                borderWidth: 1,
                borderColor: '#1e293b'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: '#e2e8f0' }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const index = context.dataIndex;
                            const monto = context.raw.toFixed(2);
                            const count = personas[index];
                            return ` $${monto} (${count} personas)`;
                        }
                    }
                }
            }
        }
    });
}

function renderChartMemberPartidos(partidosData) {
    const ctx = document.getElementById('chartMemberPartidos');
    if (!ctx) return;

    if (chartMemberPartidosInstance) {
        chartMemberPartidosInstance.destroy();
    }

    if (!partidosData || partidosData.length === 0) {
        // Show empty state
        const container = ctx.parentElement;
        let emptyMsg = container.querySelector('.empty-chart-msg');
        if (!emptyMsg) {
            emptyMsg = document.createElement('div');
            emptyMsg.className = 'empty-chart-msg empty-text';
            emptyMsg.style.position = 'absolute';
            emptyMsg.style.top = '50%';
            emptyMsg.style.left = '50%';
            emptyMsg.style.transform = 'translate(-50%, -50%)';
            emptyMsg.textContent = 'Sin datos suficientes';
            container.appendChild(emptyMsg);
        }
        return;
    } else {
        const container = ctx.parentElement;
        const emptyMsg = container.querySelector('.empty-chart-msg');
        if (emptyMsg) emptyMsg.remove();
    }

    const labels = partidosData.map(d => d.Titulo);
    const montosLocal = partidosData.map(d => d.Local.Monto);
    const montosEmpate = partidosData.map(d => d.Empate.Monto);
    const montosVisitante = partidosData.map(d => d.Visitante.Monto);
    
    const personasLocal = partidosData.map(d => d.Local.Personas);
    const personasEmpate = partidosData.map(d => d.Empate.Personas);
    const personasVisitante = partidosData.map(d => d.Visitante.Personas);

    chartMemberPartidosInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Local',
                    data: montosLocal,
                    backgroundColor: '#3b82f6',
                    personasArray: personasLocal
                },
                {
                    label: 'Empate',
                    data: montosEmpate,
                    backgroundColor: '#94a3b8',
                    personasArray: personasEmpate
                },
                {
                    label: 'Visitante',
                    data: montosVisitante,
                    backgroundColor: '#f87171',
                    personasArray: personasVisitante
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true,
                    ticks: { color: '#94a3b8' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                y: {
                    stacked: true,
                    ticks: { color: '#94a3b8' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                }
            },
            plugins: {
                legend: {
                    labels: { color: '#e2e8f0' }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const dataset = context.dataset;
                            const monto = context.raw.toFixed(2);
                            const personas = dataset.personasArray[context.dataIndex];
                            return `${dataset.label}: $${monto} (${personas} personas)`;
                        }
                    }
                }
            }
        }
    });
}

function renderHistorialApuestas(apuestas) {
    const container = document.getElementById('historialApuestas');
    if (!container) return;
    
    if (apuestas.length === 0) {
        container.innerHTML = '<p class="empty-text">No has realizado apuestas aún</p>';
        return;
    }
    
    let html = '<table class="admin-table"><thead><tr><th>Partido</th><th>Apuesta</th><th>Monto Apostado</th><th>Multiplicador</th><th>Monto Proyectado</th><th>Pago con Pozo</th><th>Estado</th></tr></thead><tbody>';
    
    apuestas.forEach(a => {
        const estadoClass = a.EstadoApuesta === 'Ganada' ? 'exacto' : (a.EstadoApuesta === 'Perdida' || a.EstadoApuesta === 'Denegada') ? 'fallo' : 'resultado';
        const pozoVal = a.PagoConPozo !== undefined ? '$' + parseFloat(a.PagoConPozo).toFixed(2) : '—';
        html += `
        <tr>
            <td data-label="Partido">${escapeHtml(a.NombreLocal || '?')} vs ${escapeHtml(a.NombreVisitante || '?')}</td>
            <td data-label="Apuesta"><span class="cuota-badge cuota-${a.EquipoApostado.toLowerCase()}">${a.EquipoApostado}</span></td>
            <td data-label="Monto Apostado"><strong>$${parseFloat(a.MontoApostado).toFixed(2)}</strong></td>
            <td data-label="Multiplicador">${parseFloat(a.CuotaFijada).toFixed(2)}</td>
            <td data-label="Monto Proyectado" style="color:var(--text-muted);">$${parseFloat(a.MontoProyectado || (a.MontoApostado * a.CuotaFijada)).toFixed(2)}</td>
            <td data-label="Pago con Pozo" style="color:var(--gold-400);font-weight:700;">${pozoVal}</td>
            <td data-label="Estado"><span class="puntos-obtenidos ${estadoClass}" style="font-size:11px">${a.EstadoApuesta}</span></td>
        </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

function renderApuestasCampeon(apuestas) {
    const container = document.getElementById('listaApuestasCampeon');
    const wrapper = document.getElementById('misApuestasCampeonContainer');
    
    if (!container || !wrapper) return;
    
    if (apuestas.length === 0) {
        wrapper.style.display = 'none';
        return;
    }
    
    wrapper.style.display = 'block';
    let html = '';
    
    apuestas.forEach(a => {
        const flagHtml = a.BanderaURL
            ? `<img src="${a.BanderaURL}" alt="${escapeHtml(a.NombreEquipo)}" class="equipo-logo" style="width:24px;height:24px;margin-right:8px;" onerror="this.style.display='none'">`
            : '';
            
        const estadoClass = a.EstadoApuesta === 'Ganada' ? 'exacto' : (a.EstadoApuesta === 'Perdida' || a.EstadoApuesta === 'Denegada') ? 'fallo' : 'resultado';
        const pozoVal = a.PagoConPozo !== undefined ? '$' + parseFloat(a.PagoConPozo).toFixed(2) : '—';
        html += `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; background:var(--bg-glass); border:1px solid var(--border-glass); border-radius:var(--radius-sm); margin-bottom:8px;">
            <div style="display:flex; align-items:center;">
                ${flagHtml}
                <span style="font-weight:600; font-size:14px; color:var(--text-primary);">${escapeHtml(a.NombreEquipo)}</span>
            </div>
            <div style="display:flex; gap:16px; align-items:center;">
                <div style="display:flex; flex-direction:column; align-items:flex-end;">
                    <span style="font-size:11px; color:var(--text-muted);">Monto Apostado</span>
                    <span style="font-weight:700; color:var(--text-primary);">$${parseFloat(a.MontoApostado).toFixed(2)}</span>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end;">
                    <span style="font-size:11px; color:var(--text-muted);">Multiplicador</span>
                    <span style="font-weight:700; color:var(--green-400);">${parseFloat(a.CuotaFijada).toFixed(2)}</span>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end;">
                    <span style="font-size:11px; color:var(--text-muted);">Monto Proyectado</span>
                    <span style="font-weight:700; color:var(--text-muted);">$${parseFloat(a.MontoProyectado || (a.MontoApostado * a.CuotaFijada)).toFixed(2)}</span>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end; width: 80px;">
                    <span style="font-size:11px; color:var(--text-muted);">Pago con Pozo</span>
                    <span style="font-weight:700; color:var(--gold-400);">${pozoVal}</span>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end;">
                    <span style="font-size:11px; color:var(--text-muted);">Estado</span>
                    <span class="puntos-obtenidos ${estadoClass}" style="font-size:11px">${a.EstadoApuesta}</span>
                </div>
            </div>
        </div>`;
    });
    
    container.innerHTML = html;
}

// ═══════════════════════════════════════════════════════════════
// TODAS LAS APUESTAS — Vista global de todos los participantes
// ═══════════════════════════════════════════════════════════════

var allBetsData = null;

async function loadAllBets() {
    const selectEl = document.getElementById('competicionFilterTes');
    const compId = selectEl && selectEl.value ? selectEl.value : '';

    try {
        allBetsData = await apiGet(`api/dashboards/getAllBets.php?competicion=${compId}`);
        
        if (!allBetsData.success) return;
        
        const el = (id) => document.getElementById(id);
        const r = allBetsData.resumen;
        
        // KPIs globales
        if (el('allBetsCampeonMonto')) el('allBetsCampeonMonto').textContent = '$' + parseFloat(r.TotalMontoCampeon || 0).toFixed(2);
        if (el('allBetsPartidosMonto')) el('allBetsPartidosMonto').textContent = '$' + parseFloat(r.TotalMontoPartidos || 0).toFixed(2);
        if (el('allBetsTotalApuestas')) el('allBetsTotalApuestas').textContent = (r.TotalApuestasCampeon || 0) + (r.TotalApuestasPartidos || 0);
        if (el('allBetsPendientes')) el('allBetsPendientes').textContent = r.PendientesPartidos || 0;
        
        // Tablas
        renderAllBetsCampeon();
        renderAllBetsPartidos(allBetsData.apuestasPartidos || []);
    } catch (e) {
        console.error('Error loading all bets:', e);
    }
}

function renderAllBetsCampeon() {
    const container = document.getElementById('allBetsCampeonTable');
    if (!container) return;
    
    let apuestas = allBetsData && allBetsData.apuestasCampeon ? allBetsData.apuestasCampeon : [];
    
    if (apuestas.length === 0) {
        container.innerHTML = '<p class="empty-text">No hay apuestas al campeón registradas</p>';
        return;
    }
    
    // Agrupación por usuario y país
    const grouped = {};
    apuestas.forEach(a => {
        const key = a.NombreUsuario + '_' + a.NombreEquipo;
        if (!grouped[key]) {
            grouped[key] = {
                NombreUsuario: a.NombreUsuario,
                NombreCompleto: a.NombreCompleto,
                NombreEquipo: a.NombreEquipo,
                BanderaURL: a.BanderaURL,
                EstadoApuesta: a.EstadoApuesta,
                Apuestas: [],
                TotalApostado: 0,
                TotalPozo: 0
            };
        }
        grouped[key].Apuestas.push({
            monto: parseFloat(a.MontoApostado),
            cuota: parseFloat(a.CuotaFijada)
        });
        grouped[key].TotalApostado += parseFloat(a.MontoApostado);
        grouped[key].TotalPozo += (a.PagoConPozo !== undefined && a.PagoConPozo !== null) ? parseFloat(a.PagoConPozo) : 0;
    });

    let groupedArray = Object.values(grouped);

    // --- Aplicar Filtros ---
    const userFilterEl = document.getElementById('filterCampeonUser');
    const statusFilterEl = document.getElementById('filterCampeonStatus');
    const sortFilterEl = document.getElementById('filterCampeonSort');
    
    if (userFilterEl && userFilterEl.value) {
        const val = userFilterEl.value.toLowerCase();
        groupedArray = groupedArray.filter(g => 
            g.NombreUsuario.toLowerCase().includes(val) || 
            (g.NombreCompleto && g.NombreCompleto.toLowerCase().includes(val))
        );
    }
    
    if (statusFilterEl && statusFilterEl.value !== 'todos') {
        const statusVal = statusFilterEl.value;
        if (statusVal === 'vigentes') {
            groupedArray = groupedArray.filter(g => g.EstadoApuesta !== 'Perdida' && g.EstadoApuesta !== 'Cancelada' && g.EstadoApuesta !== 'Denegada');
        } else if (statusVal === 'eliminados') {
            groupedArray = groupedArray.filter(g => g.EstadoApuesta === 'Perdida');
        }
    }
    
    // --- Aplicar Ordenamiento ---
    const sortVal = sortFilterEl ? sortFilterEl.value : 'apostado_desc';
    if (sortVal === 'apostado_desc') {
        groupedArray.sort((a, b) => b.TotalApostado - a.TotalApostado);
    } else if (sortVal === 'apostado_asc') {
        groupedArray.sort((a, b) => a.TotalApostado - b.TotalApostado);
    } else if (sortVal === 'pago_desc') {
        groupedArray.sort((a, b) => b.TotalPozo - a.TotalPozo);
    } else if (sortVal === 'pago_asc') {
        groupedArray.sort((a, b) => a.TotalPozo - b.TotalPozo);
    }

    if (groupedArray.length === 0) {
        container.innerHTML = '<p class="empty-text">No hay apuestas que coincidan con los filtros</p>';
        return;
    }

    let html = '<table class="admin-table"><thead><tr><th>Persona</th><th>Equipo</th><th>Desglose de Apuestas</th><th>Total Apostado</th><th>Total Pago con Pozo</th><th>Estado</th></tr></thead><tbody>';
    
    groupedArray.forEach(g => {
        const flagHtml = g.BanderaURL
            ? `<img src="${g.BanderaURL}" alt="${escapeHtml(g.NombreEquipo)}" style="width:18px;height:18px;vertical-align:middle;margin-right:4px;border-radius:2px;" onerror="this.style.display='none'">`
            : '';
        const estadoClass = g.EstadoApuesta === 'Ganada' ? 'exacto' : (g.EstadoApuesta === 'Perdida' || g.EstadoApuesta === 'Denegada') ? 'fallo' : 'resultado';
        
        let desgloseHtml = g.Apuestas.map((ap, idx) => `<div style="font-size:11px;color:var(--text-muted)">Apuesta ${idx + 1}: $${ap.monto.toFixed(2)} (x${ap.cuota.toFixed(2)})</div>`).join('');
        
        html += `
        <tr>
            <td data-label="Persona"><strong>@${escapeHtml(g.NombreUsuario)}</strong><br><span style="font-size:11px;color:var(--text-muted)">${escapeHtml(g.NombreCompleto || '')}</span></td>
            <td data-label="Equipo">${flagHtml} ${escapeHtml(g.NombreEquipo)}</td>
            <td data-label="Desglose de Apuestas">${desgloseHtml}</td>
            <td data-label="Total Apostado"><strong>$${g.TotalApostado.toFixed(2)}</strong></td>
            <td data-label="Total Pago con Pozo" style="color:var(--gold-400);font-weight:700;">$${g.TotalPozo.toFixed(2)}</td>
            <td data-label="Estado"><span class="puntos-obtenidos ${estadoClass}" style="font-size:11px">${g.EstadoApuesta}</span></td>
        </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

function renderAllBetsPartidos(apuestas) {
    const container = document.getElementById('allBetsPartidosTable');
    if (!container) return;
    
    if (apuestas.length === 0) {
        container.innerHTML = '<p class="empty-text">No hay apuestas por partido registradas</p>';
        return;
    }
    
    let html = '<table class="admin-table"><thead><tr><th>Persona</th><th>Partido</th><th>Apuesta</th><th>Monto Apostado</th><th>Multiplicador</th><th>Monto Proyectado</th><th>Pago con Pozo</th><th>Estado</th></tr></thead><tbody>';
    
    apuestas.forEach(a => {
        const estadoClass = a.EstadoApuesta === 'Ganada' ? 'exacto' : (a.EstadoApuesta === 'Perdida' || a.EstadoApuesta === 'Denegada') ? 'fallo' : 'resultado';
        const pozoVal = a.PagoConPozo !== undefined ? '$' + parseFloat(a.PagoConPozo).toFixed(2) : '—';
        
        html += `
        <tr>
            <td data-label="Persona"><strong>@${escapeHtml(a.NombreUsuario)}</strong></td>
            <td data-label="Partido">${escapeHtml(a.NombreLocal || '?')} vs ${escapeHtml(a.NombreVisitante || '?')}<br><span style="font-size:11px;color:var(--text-muted)">${escapeHtml(a.Fase || '')}</span></td>
            <td data-label="Apuesta"><span class="cuota-badge cuota-${a.EquipoApostado.toLowerCase()}">${a.EquipoApostado}</span></td>
            <td data-label="Monto Apostado"><strong>$${parseFloat(a.MontoApostado).toFixed(2)}</strong></td>
            <td data-label="Multiplicador">${parseFloat(a.CuotaFijada).toFixed(2)}</td>
            <td data-label="Monto Proyectado" style="color:var(--text-muted);">$${parseFloat(a.MontoProyectado || (a.MontoApostado * a.CuotaFijada)).toFixed(2)}</td>
            <td data-label="Pago con Pozo" style="color:var(--gold-400);font-weight:700;">${pozoVal}</td>
            <td data-label="Estado"><span class="puntos-obtenidos ${estadoClass}" style="font-size:11px">${a.EstadoApuesta}</span></td>
        </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}


// ═══════════════════════════════════════════════════════════════
// TESORERO DASHBOARD — Chart.js
// ═══════════════════════════════════════════════════════════════

async function loadPendingBets() {
    const selectEl = document.getElementById('competicionFilterTes');
    const compId = selectEl && selectEl.value ? selectEl.value : '';

    try {
        const data = await apiGet(`api/tesoreria/getPendingBets.php?competicion=${compId}`);
        if (data.success) {
            renderPendingBets(data.pendingBets || []);
        }
    } catch (e) {
        console.error('Error loading pending bets:', e);
    }
}

function renderPendingBets(bets) {
    const tbody = document.getElementById('bandejaAprobacionList');
    if (!tbody) return;
    
    if (bets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-text">No hay apuestas pendientes de aprobación </td></tr>';
        return;
    }
    
    let html = '';
    bets.forEach(b => {
        const rowId = `pending_bet_${b.Id}_${b.Tipo}`;
        const pozoVal = b.PagoConPozo !== undefined && b.PagoConPozo !== null ? '$' + parseFloat(b.PagoConPozo).toFixed(2) : '—';
        
        const flagHtml = b.BanderaURL
            ? `<img src="${b.BanderaURL}" alt="${escapeHtml(b.Prediccion || '')}" style="width:18px;height:18px;vertical-align:middle;margin-right:4px;border-radius:2px;" onerror="this.style.display='none'">`
            : '';
        const equipoHtml = b.Tipo === 'Campeon' 
            ? `${flagHtml} ${escapeHtml(b.Prediccion)}`
            : `${escapeHtml(b.Evento)}<br><span style="font-size:11px;color:var(--text-muted)">Apuesta: ${escapeHtml(b.Prediccion)}</span>`;
            
        const desgloseHtml = (b.Desglose || []).map(d => {
            const fontWeight = d.EsEste ? '700' : '400';
            const color = d.EsEste ? 'var(--blue-400)' : 'var(--text-muted)';
            return `<div style="font-size:11px;color:${color};font-weight:${fontWeight};">Apuesta ${d.Numero}: $${d.Monto.toFixed(2)} (x${d.Cuota.toFixed(2)})</div>`;
        }).join('');

        html += `
        <tr id="${rowId}">
            <td data-label="Persona"><strong>@${escapeHtml(b.Usuario)}</strong><br><span style="font-size:11px;color:var(--text-muted)">${escapeHtml(b.NombreCompleto || '')}</span></td>
            <td data-label="Equipo">${equipoHtml}</td>
            <td data-label="Desglose de Apuestas">${desgloseHtml}</td>
            <td data-label="Total Apostado"><strong>$${parseFloat(b.TotalApostado || 0).toFixed(2)}</strong></td>
            <td data-label="Monto por Aprobar"><strong style="color:var(--blue-400)">$${b.Monto.toFixed(2)}</strong></td>
            <td data-label="Total Pago con Pozo" style="color:var(--gold-400);font-weight:700;">${pozoVal}</td>
            <td data-label="Acción" style="vertical-align: middle;">
                <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                    <button class="btn btn-primary" style="display: inline-flex; align-items: center; justify-content: center; gap: 4px; padding: 6px 12px; font-size: 11px; line-height: 1;" onclick="approvePendingBet(${b.Id}, '${b.Tipo}', '${rowId}')">
                        Aprobar
                    </button>
                    <button class="btn" style="display: inline-flex; align-items: center; justify-content: center; gap: 4px; padding: 6px 12px; font-size: 11px; background-color: var(--red-500); color: white; border: none; border-radius: var(--radius-sm); line-height: 1;" onclick="denyPendingBet(${b.Id}, '${b.Tipo}', '${rowId}')">
                        Denegar
                    </button>
                </div>
            </td>
        </tr>`;
    });
    
    tbody.innerHTML = html;
}

async function approvePendingBet(id, tipo, rowId) {
    try {
        const btn = document.querySelector(`#${rowId} button`);
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Cargando...';
        }
        
        const data = await apiPost('api/tesoreria/approveBet.php', { id, tipo });
        
        if (data.success) {
            showToast('Apuesta aprobada y añadida a los totales');
            // Recargar datos del dashboard de tesorero
            await loadTesoreroDashboard();
        } else {
            showToast('' + (data.error || 'Error al aprobar'));
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Aprobar';
            }
        }
    } catch (e) {
        showToast('Error de conexión al aprobar');
    }
}

async function denyPendingBet(id, tipo, rowId) {
    if (!confirm('¿Estás seguro que deseas denegar esta apuesta? El miembro verá que fue denegada.')) return;
    try {
        const row = document.getElementById(rowId);
        const btns = row ? row.querySelectorAll('button') : [];
        btns.forEach(btn => btn.disabled = true);
        
        const data = await apiPost('api/tesoreria/denyBet.php', { id, tipo });
        
        if (data.success) {
            showToast('Apuesta denegada correctamente');
            await loadTesoreroDashboard();
        } else {
            showToast('' + (data.error || 'Error al denegar'));
            btns.forEach(btn => btn.disabled = false);
        }
    } catch (e) {
        showToast('Error de conexión al denegar');
    }
}

async function loadTesoreroDashboard() {
    const section = document.getElementById('sectionTesorero');
    if (!section || section.classList.contains('hidden')) return;

    const selectEl = document.getElementById('competicionFilterTes');
    const compId = selectEl && selectEl.value ? selectEl.value : '';

    try {
        tesoreroDashData = await apiGet(`api/dashboards/getTesoreroDashboard.php?competicion=${compId}`);
        
        if (!tesoreroDashData.success) return;
        
        // Cargar apuestas pendientes de aprobación
        await loadPendingBets();
        
        // Cargar apuestas de todas las personas
        loadAllBets();
        
        renderTesoreroDashboard();
    } catch (e) {
        console.error('Error loading tesorero dashboard:', e);
}
}

let chartMemInstance = null;
let chartCampInstance = null;
let chartDiaInstance = null;

function renderTesoreroDashboard() {
    const d = tesoreroDashData;
    const el = (id) => document.getElementById(id);
    
    // KPIs
    if (el('tesRecaudacion')) el('tesRecaudacion').textContent = '$' + parseFloat(d.membresias.RecaudacionTotal || 0).toFixed(2);
    if (el('tesTotalApuestas')) el('tesTotalApuestas').textContent = d.apuestasResumen?.TotalApuestas || 0;
    if (el('tesMontoApuestas')) el('tesMontoApuestas').textContent = '$' + parseFloat(d.apuestasResumen?.MontoTotal || 0).toFixed(2);
    if (el('tesGananciasPagadas')) el('tesGananciasPagadas').textContent = '$' + parseFloat(d.apuestasResumen?.GananciasPagadas || 0).toFixed(2);
    
    // Chart.js colors
    const chartColors = ['#22c55e', '#3b82f6', '#eab308', '#ef4444', '#8b5cf6', '#ec4899', '#f97316', '#14b8a6', '#6366f1', '#84cc16'];
    
    // Premium Tooltip configuration
    const tooltipOptions = {
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        titleColor: '#f8fafc',
        bodyColor: '#cbd5e1',
        borderColor: 'rgba(255, 255, 255, 0.1)',
        borderWidth: 1,
        padding: 12,
        cornerRadius: 8,
        displayColors: true,
        boxPadding: 4,
        titleFont: { family: 'Inter', size: 14, weight: '600' },
        bodyFont: { family: 'Inter', size: 13 }
    };

    // 1. Pie: Membresías
    const ctxMem = el('chartMembresias');
    if (ctxMem) {
        if (chartMemInstance) chartMemInstance.destroy();
        chartMemInstance = new Chart(ctxMem, {
            type: 'doughnut',
            data: {
                labels: ['Activas', 'Inactivas'],
                datasets: [{
                    data: [d.membresias.Activas || 0, d.membresias.Inactivas || 0],
                    backgroundColor: ['#10b981', 'rgba(255, 255, 255, 0.05)'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                cutout: '75%',
                plugins: {
                    legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 12 }, usePointStyle: true, boxWidth: 8 } },
                    title: { display: true, text: `Total: $${parseFloat(d.membresias.RecaudacionTotal || 0).toFixed(2)}`, color: '#f1f5f9', font: { family: 'Inter', size: 16, weight: '600' }, padding: { bottom: 20 } },
                    tooltip: tooltipOptions
                }
            }
        });
    }
    
    // 2. Pie: Campeón por equipo
    const ctxCamp = el('chartCampeon');
    if (ctxCamp) {
        if (chartCampInstance) {
            chartCampInstance.destroy();
            chartCampInstance = null;
        }
        if (d.campeonPorEquipo && d.campeonPorEquipo.length > 0) {
            const topTeams = d.campeonPorEquipo.slice(0, 10);
            const chartColorsPremium = ['#3b82f6', '#8b5cf6', '#ec4899', '#f43f5e', '#f59e0b', '#10b981', '#06b6d4', '#6366f1', '#a855f7', '#14b8a6'];
            chartCampInstance = new Chart(ctxCamp, {
                type: 'doughnut',
                data: {
                    labels: topTeams.map(t => t.Nombre),
                    datasets: [{
                        data: topTeams.map(t => parseFloat(t.MontoTotal)),
                        backgroundColor: chartColorsPremium.slice(0, topTeams.length),
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '75%',
                    plugins: {
                        legend: { position: 'right', labels: { color: '#94a3b8', font: { family: 'Inter', size: 11 }, usePointStyle: true, boxWidth: 8 } },
                        tooltip: tooltipOptions
                    }
                }
            });
        }
    }
    
    // 3. Line: Recaudación por Día
    const ctxDia = el('chartRecaudacionDia');
    if (ctxDia) {
        if (chartDiaInstance) {
            chartDiaInstance.destroy();
            chartDiaInstance = null;
        }
        if (d.recaudacionPorDia && d.recaudacionPorDia.length > 0) {
            let gradient = ctxDia.getContext('2d').createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

            chartDiaInstance = new Chart(ctxDia, {
                type: 'line',
                data: {
                    labels: d.recaudacionPorDia.map(r => r.Dia.substring(5)),
                    datasets: [{
                        label: 'Recaudación (USD)',
                        data: d.recaudacionPorDia.map(r => parseFloat(r.MontoTotal)),
                        borderColor: '#10b981',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: { 
                            ticks: { color: '#94a3b8', font: { family: 'Inter', size: 11 } }, 
                            grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false } 
                        },
                        y: { 
                            ticks: { color: '#94a3b8', font: { family: 'Inter', size: 11 }, callback: (value) => '$' + value }, 
                            grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false, borderDash: [5, 5] },
                            beginAtZero: true
                        }
                    },
                    plugins: { 
                        legend: { display: false },
                        tooltip: { ...tooltipOptions, callbacks: { label: (ctx) => `$${ctx.parsed.y.toFixed(2)}` } }
                    },
                    interaction: { mode: 'index', intersect: false }
                }
            });
        }
    }
    
    // Membresías recientes
    const tbody = el('tesMembresiasLista');
    if (tbody && d.membresiasRecientes) {
        if (d.membresiasRecientes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-text">Sin membresías registradas</td></tr>';
        } else {
            let html = '';
            d.membresiasRecientes.forEach(m => {
                const estadoBadge = m.Estado === 'Activa' 
                    ? '<span class="admin-role-badge admin">Activa</span>' 
                    : '<span class="admin-role-badge user">Inactiva</span>';
                html += `<tr>
                    <td data-label="Usuario"><strong>@${escapeHtml(m.NombreUsuario)}</strong></td>
                    <td data-label="Nombre">${escapeHtml(m.NombreCompleto)}</td>
                    <td data-label="Monto"><strong>$${parseFloat(m.MontoPagado).toFixed(2)}</strong></td>
                    <td data-label="Fecha">${m.FechaPago ? m.FechaPago.substring(0, 10) : '—'}</td>
                    <td data-label="Estado">${estadoBadge}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }
    }
}

function exportTesorero(format) {
    if (format === 'png') {
        const section = document.getElementById('sectionTesorero');
        html2canvas(section, { backgroundColor: '#0a0e1a', scale: 2 }).then(canvas => {
            const link = document.createElement('a');
            link.download = `Tesoreria_Quiniela2026_${new Date().toISOString().slice(0, 10)}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
            showToast('Dashboard exportado como imagen');
        });
    } else if (format === 'csv') {
        if (!tesoreroDashData) return;
        let csv = 'Tipo,Detalle,Monto,Fecha\n';
        (tesoreroDashData.membresiasRecientes || []).forEach(m => {
            csv += `Membresía,${m.NombreUsuario},${m.MontoPagado},${m.FechaPago}\n`;
        });
        (tesoreroDashData.recaudacionPorDia || []).forEach(r => {
            csv += `Recaudación Día,${r.TotalApuestas} apuestas,${r.MontoTotal},${r.Dia}\n`;
        });
        const blob = new Blob([csv], { type: 'text/csv' });
        const link = document.createElement('a');
        link.download = `Tesoreria_Quiniela2026_${new Date().toISOString().slice(0, 10)}.csv`;
        link.href = URL.createObjectURL(blob);
        link.click();
        showToast('Datos exportados como CSV');
    }
}

// ═══════════════════════════════════════════════════════════════
// BETTING MODALS
// ═══════════════════════════════════════════════════════════════

function openChampionModal() {
    document.getElementById('modalCampeon').classList.add('active');
    if (championTeams.length === 0) {
        loadMiembroDashboard().then(() => renderChampionTeams(championTeams));
    } else {
        renderChampionTeams(championTeams);
    }
}

function closeChampionModal() {
    document.getElementById('modalCampeon').classList.remove('active');
}

function renderChampionTeams(teams) {
    const container = document.getElementById('championTeamsList');
    if (teams.length === 0) {
        container.innerHTML = '<p class="empty-text">No hay equipos disponibles</p>';
        return;
    }
    
    let html = '';
    teams.forEach(t => {
        const flagHtml = t.BanderaURL
            ? `<img src="${t.BanderaURL}" alt="${escapeHtml(t.Nombre)}" class="equipo-logo" style="width:32px;height:32px;" onerror="this.style.display='none'">`
            : '';
        html += `
        <div class="champion-team-card" id="champCard_${t.Id}">
            ${flagHtml}
            <div style="flex-grow:1; display:flex; flex-direction:column;">
                <span class="champion-team-name">${escapeHtml(t.Nombre)}</span>
                <span style="font-size:11px; color:var(--gold-400); font-weight:700;">Cuota: ${t.CuotaActual ? parseFloat(t.CuotaActual).toFixed(2) : '24.00'}</span>
            </div>
            <input type="number" class="champion-bet-input" id="champBet_${t.Id}" placeholder="$" min="0.01" step="0.01">
            <button class="btn btn-primary" style="padding:6px 14px;font-size:12px;" onclick="placeBetChampion(${t.Id})">Apostar</button>
        </div>`;
    });
    container.innerHTML = html;
}

function filterChampionTeams(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.champion-team-card').forEach(card => {
        const name = card.querySelector('.champion-team-name').textContent.toLowerCase();
        card.style.display = name.includes(q) ? 'flex' : 'none';
    });
}

async function placeBetChampion(idEquipo) {
    const input = document.getElementById(`champBet_${idEquipo}`);
    const monto = parseFloat(input?.value);
    
    if (!monto || monto <= 0) {
        showToast('Ingresa un monto válido');
        return;
    }
    
    try {
        const data = await apiPost('api/apuestas/placeBetChampion.php', { idEquipo, monto });
        
        if (data.success) {
            showToast(`${data.message} · Cuota: ${data.cuota} · Ganancia potencial: $${data.ganancia}`);
            input.value = '';
            miembroDashData = null;
        } else {
            showToast(`${data.error}`);
        }
    } catch (e) {
        showToast('Error de conexión');
    }
}

function openBetMatchModal(idPartido) {
    const p = allPartidos.find(x => x.Id === idPartido);
    if (!p) return;
    
    const modal = document.getElementById('modalBetMatch');
    const body = document.getElementById('betMatchBody');
    
    body.innerHTML = `
        <div style="text-align:center; margin-bottom:20px;">
            <strong>${escapeHtml(p.EquipoLocal.Nombre)}</strong> vs <strong>${escapeHtml(p.EquipoVisitante.Nombre)}</strong>
            <div style="color:var(--text-muted);font-size:12px;margin-top:4px;">${escapeHtml(p.Fase)} · ${formatTime(p.FechaHoraCST)}</div>
        </div>
        <div class="bet-options" role="radiogroup" aria-label="Selecciona a quién apostar">
            <button type="button" class="bet-option" role="radio" aria-checked="false" onclick="selectBetOption(this, 'Local')">
                <div class="bet-option-label">${escapeHtml(p.EquipoLocal.Nombre)}</div>
                <div class="bet-option-odds">${p.CuotaLocal?.toFixed(2)}</div>
            </button>
            <button type="button" class="bet-option" role="radio" aria-checked="false" onclick="selectBetOption(this, 'Empate')">
                <div class="bet-option-label">Empate</div>
                <div class="bet-option-odds">${p.CuotaEmpate?.toFixed(2)}</div>
            </button>
            <button type="button" class="bet-option" role="radio" aria-checked="false" onclick="selectBetOption(this, 'Visitante')">
                <div class="bet-option-label">${escapeHtml(p.EquipoVisitante.Nombre)}</div>
                <div class="bet-option-odds">${p.CuotaVisitante?.toFixed(2)}</div>
            </button>
        </div>
        <div class="form-group" style="margin-top:16px;">
            <label>Monto a apostar (USD)</label>
            <input type="number" id="betMatchAmount" class="admin-input" placeholder="Ingresa monto..." min="0.01" step="0.01">
        </div>
        <div id="betMatchGanancia" style="text-align:center;color:var(--green-400);font-weight:700;margin:12px 0;font-size:15px;"></div>
        <button class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;" id="confirmBetMatchBtn" onclick="confirmBetMatch(${idPartido})" disabled>
            Confirmar Apuesta
        </button>`;
    
    modal.classList.add('active');
    
    // Listen for amount changes
    document.getElementById('betMatchAmount').addEventListener('input', updateGananciaPotencial);
}

let selectedBetOption = null;

function selectBetOption(el, option) {
    document.querySelectorAll('.bet-option').forEach(o => {
        o.classList.remove('selected');
        o.setAttribute('aria-checked', 'false');
    });
    el.classList.add('selected');
    el.setAttribute('aria-checked', 'true');
    selectedBetOption = option;
    document.getElementById('confirmBetMatchBtn').disabled = false;
    updateGananciaPotencial();
}

function updateGananciaPotencial() {
    const monto = parseFloat(document.getElementById('betMatchAmount')?.value) || 0;
    const cuotaEl = document.querySelector('.bet-option.selected .bet-option-odds');
    const cuota = cuotaEl ? parseFloat(cuotaEl.textContent) : 0;
    const ganancia = (monto * cuota).toFixed(2);
    const el = document.getElementById('betMatchGanancia');
    if (el) el.textContent = monto > 0 && cuota > 0 ? `Ganancia potencial: $${ganancia}` : '';
}

function closeBetMatchModal() {
    document.getElementById('modalBetMatch').classList.remove('active');
    selectedBetOption = null;
}

async function confirmBetMatch(idPartido) {
    const monto = parseFloat(document.getElementById('betMatchAmount')?.value);
    if (!monto || monto <= 0 || !selectedBetOption) {
        showToast('Selecciona una opción e ingresa un monto');
        return;
    }
    
    const btn = document.getElementById('confirmBetMatchBtn');
    btn.disabled = true;
    btn.textContent = 'Procesando...';
    
    try {
        const data = await apiPost('api/apuestas/placeBetMatch.php', { idPartido, monto, equipoApostado: selectedBetOption });
        
        if (data.success) {
            showToast(`${data.message}`);
            closeBetMatchModal();
            miembroDashData = null;
        } else {
            showToast(`${data.error}`);
            btn.disabled = false;
            btn.textContent = 'Confirmar Apuesta';
        }
    } catch (e) {
        showToast('Error de conexión');
        btn.disabled = false;
        btn.textContent = 'Confirmar Apuesta';
    }
}

// ═══════════════════════════════════════════════════════════════
// SYNC ODDS
// ═══════════════════════════════════════════════════════════════

async function syncOdds() {
    const btn = document.getElementById('syncOddsBtn');
    const result = document.getElementById('syncOddsResult');
    
    btn.disabled = true;
    btn.innerHTML = 'Sincronizando...';
    result.innerHTML = '<div class="loading"><div class="spinner"></div>Consultando The Odds API...</div>';
    
    try {
        const data = await apiPost('api/partidos/syncOdds.php');
        
        if (data.success) {
            const s = data.stats;
            let html = `<div style="color:var(--green-400);font-weight:600;">Sincronización completada</div>
                <div style="margin-top:8px;color:var(--text-secondary);font-size:13px;">
                    Fuente: ${data.source || 'API'}<br>`;
            if (s.eventosAPI !== undefined) html += `Eventos de API: ${s.eventosAPI}<br>`;
            if (s.partidosActualizados !== undefined) html += `Partidos actualizados: ${s.partidosActualizados}<br>`;
            if (s.defaultAplicados !== undefined) html += `Cuotas por defecto aplicadas: ${s.defaultAplicados}<br>`;
            if (s.noEncontrados) html += `No encontrados: ${s.noEncontrados}<br>`;
            html += '</div>';
            
            if (s.errores && s.errores.length > 0) {
                html += '<div style="margin-top:8px;color:var(--red-400);font-size:11px;">';
                s.errores.slice(0, 5).forEach(e => html += `• ${escapeHtml(e)}<br>`);
                html += '</div>';
            }
            
            result.innerHTML = html;
            showToast('Cuotas sincronizadas');
            
            // Reload partidos to show new odds
            await loadPartidos();
        } else {
            result.innerHTML = `<div style="color:var(--red-400);">${escapeHtml(data.error || 'Error')}</div>`;
        }
    } catch (e) {
        result.innerHTML = '<div style="color:var(--red-400);">Error de conexión</div>';
    }
    
    btn.disabled = false;
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg> Sincronizar Cuotas Ahora';
}

// ═══════════════════════════════════════════════════════════════
// ADMIN: REAL-TIME SEARCH
// ═══════════════════════════════════════════════════════════════

function filterAdminUsers(query) {
    const q = query.toLowerCase();
    const rows = document.querySelectorAll('#adminUsersManageList tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}

function filterConvertUsers(query) {
    const q = query.toLowerCase();
    const rows = document.querySelectorAll('#convertUsersList tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}

function filterAdminTableUsers(query) {
    const q = query.toLowerCase();
    const rows = document.querySelectorAll('#adminUsersList tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}

// ═══════════════════════════════════════════════════════════════
// ADMIN: CONVERT TO MEMBER
// ═══════════════════════════════════════════════════════════════

function renderConvertUsers() {
    const tbody = document.getElementById('convertUsersList');
    if (!tbody || adminUsers.length === 0) return;
    
    const rolesMap = {
        1: '<span class="admin-role-badge" style="background: #b91c1c; color: #fff; font-weight: 700; border: none; box-shadow: 0 2px 10px rgba(239, 68, 68, 0.3);">Admin</span>',
        2: '<span class="admin-role-badge" style="background: #1d4ed8; color: #fff; font-weight: 700; border: none; box-shadow: 0 2px 10px rgba(59, 130, 246, 0.3);">Tesorero</span>',
        3: '<span class="admin-role-badge" style="background: #6d28d9; color: #fff; font-weight: 700; border: none; box-shadow: 0 2px 10px rgba(139, 92, 246, 0.3);">Miembro</span>',
        4: '<span class="admin-role-badge" style="background: var(--bg-glass); color: var(--text-muted); font-weight: 600; border: 1px solid var(--border-glass);">Regular</span>'
    };
    
    let html = '';
    adminUsers.forEach(u => {
        const rolActual = u.IdRol || 4;
        const rolBadge = rolesMap[rolActual] || 'Regular';
        
        let rowClass = '';
        if (rolActual === 1) rowClass = 'row-highlight-admin';
        else if (rolActual === 2) rowClass = 'row-highlight-tesorero';
        else if (rolActual === 3) rowClass = 'row-highlight-miembro';
        
        html += `
        <tr data-user-id="${u.Id}" class="${rowClass}">
            <td><strong>@${escapeHtml(u.NombreUsuario)}</strong></td>
            <td>${escapeHtml(u.NombreCompleto || u.NombreUsuario)}</td>
            <td>${rolBadge}</td>
            <td><input type="number" class="admin-input" style="width:100px" id="convMonto_${u.Id}" value="${u.MontoPagado ? parseFloat(u.MontoPagado).toFixed(2) : ''}" placeholder="$0.00" min="0" step="0.01"></td>
            <td style="text-align:center;">
                <select class="filter-select" id="convRol_${u.Id}" style="min-width:120px">
                    <option value="3" ${rolActual === 3 ? 'selected' : ''}>Miembro</option>
                    <option value="2" ${rolActual === 2 ? 'selected' : ''}>Tesorero</option>
                    <option value="4" ${rolActual === 4 ? 'selected' : ''}>Regular</option>
                </select>
                <button class="btn btn-primary" style="padding:6px 12px;font-size:12px;margin-left:4px;" 
                        onclick="convertUser(${u.Id})">Aplicar</button>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

async function convertUser(userId) {
    const montoEl = document.getElementById(`convMonto_${userId}`);
    const rolEl = document.getElementById(`convRol_${userId}`);
    const monto = parseFloat(montoEl?.value) || 0;
    const rol = parseInt(rolEl?.value) || 3;
    
    const selectEl = document.getElementById('competicionFilterMem');
    const compId = selectEl && selectEl.value ? parseInt(selectEl.value) : null;
    
    try {
        const data = await apiPost('api/admin/convertToMember.php', { userId, montoPagado: monto, rol, competicionId: compId });
        
        if (data.success) {
            showToast(`${data.message}`);
            montoEl.value = '';
            loadAdminPermissions(); // Refresh data
        } else {
            showToast(`${data.error}`);
        }
    } catch (e) {
        showToast('Error de conexión');
    }
}

// ═══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    const filters = ['filterCampeonUser', 'filterCampeonStatus', 'filterCampeonSort'];
    filters.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => {
                if (typeof renderAllBetsCampeon === 'function') {
                    renderAllBetsCampeon();
                }
            });
        }
    });
});
