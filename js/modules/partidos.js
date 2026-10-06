// ═══════════════════════════════════════════════════════════════
// PARTIDOS (PREDICCIONES) — Solo modo detallado
// ═══════════════════════════════════════════════════════════════

async function loadPartidos() {
    const feed = document.getElementById('partidosFeed');
    feed.innerHTML = '<div class="loading"><div class="spinner"></div>Cargando partidos...</div>';

    try {
        const params = new URLSearchParams();
        const fase = document.getElementById('filterFase')?.value;
        const fecha = document.getElementById('filterFecha')?.value;
        if (fase) params.set('fase', fase);
        if (fecha) params.set('fecha', fecha);

        const data = await apiGet('api/partidos/getPartidos.php?' + params.toString());

        if (data.error) {
            feed.innerHTML = `<div class="loading">${data.error}</div>`;
            return;
        }

        allPartidos = data.partidos || [];
        horaServidor = data.horaServidor;

        // Populate filter selects
        populateFilters(data.fases || [], data.fechas || []);

        renderPartidos();
        updateQuickStats();
    } catch (e) {
        feed.innerHTML = '<div class="loading">Error al cargar partidos</div>';
        console.error(e);
    }
}

function populateFilters(fases, fechas) {
    const faseSelect = document.getElementById('filterFase');
    const fechaSelect = document.getElementById('filterFecha');

    // Only populate if empty (first load)
    if (faseSelect.options.length <= 1) {
        fases.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f;
            opt.textContent = f;
            faseSelect.appendChild(opt);
        });

        faseSelect.addEventListener('change', () => loadPartidos());
    }

    if (fechaSelect.options.length <= 1) {
        let serverDate = '';
        if (typeof horaServidor !== 'undefined' && horaServidor) {
            serverDate = horaServidor.split(' ')[0];
        } else {
            const today = new Date();
            serverDate = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
        }

        let hasToday = false;

        fechas.sort((a, b) => a.localeCompare(b));

        fechas.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f;
            opt.textContent = formatDateLabel(f);
            if (f === serverDate) {
                opt.selected = true;
                hasToday = true;
            }
            fechaSelect.appendChild(opt);
        });

        fechaSelect.addEventListener('change', () => loadPartidos());

        if (!window.initialFilterApplied) {
            window.initialFilterApplied = true;

            if (!hasToday) {
                const opt = document.createElement('option');
                opt.value = serverDate;
                opt.textContent = formatDateLabel(serverDate);
                fechaSelect.appendChild(opt);
            }

            fechaSelect.value = serverDate;
            loadPartidos();
        }
    }
}

function renderPartidos() {
    const feed = document.getElementById('partidosFeed');

    if (allPartidos.length === 0) {
        feed.innerHTML = '<div class="loading">No hay partidos el día de hoy.</div>';
        return;
    }

    // Group by date
    const grouped = {};
    allPartidos.forEach(p => {
        const dateKey = p.FechaHoraCST.split(' ')[0];
        if (!grouped[dateKey]) grouped[dateKey] = [];
        grouped[dateKey].push(p);
    });

    let html = '';
    for (const [date, partidos] of Object.entries(grouped)) {
        html += `<div class="partido-date-header"> ${formatDateLabel(date)}</div>`;

        partidos.forEach(p => {
            html += renderPartidoCard(p);
        });
    }

    feed.innerHTML = html;
}

function renderPartidoCard(p) {
    const eLocal = p.EquipoLocal;
    const eVisitante = p.EquipoVisitante;
    const pred = p.Prediccion;
    const bloqueado = p.Bloqueado;
    const finalizado = p.Estado === 'FT' || p.Estado === 'AET' || p.Estado === 'PEN';
    const enVivo = ['1H', '2H', 'HT', 'ET', 'P', 'BT', 'LIVE'].includes(p.Estado);

    let cardClass = 'partido-card match-row-layout';
    if (bloqueado) cardClass += ' bloqueado';
    if (finalizado) cardClass += ' finalizado';
    if (enVivo) cardClass += ' en-vivo';

    // Prediction inputs values
    const predLocal = pred ? pred.GolesLocal : '';
    const predVisitante = pred ? pred.GolesVisitante : '';
    const hasValueLocal = predLocal !== '' ? 'has-value' : '';
    const hasValueVisitante = predVisitante !== '' ? 'has-value' : '';

    // Equipo logos
    const logoLocal = eLocal.BanderaURL
        ? `<img class="equipo-logo" src="${eLocal.BanderaURL}" alt="${eLocal.Nombre}">`
        : `<div class="equipo-logo placeholder-logo"><span>${escapeHtml(eLocal.Nombre?.substring(0,1) || '?')}</span></div>`;

    const logoVisitante = eVisitante.BanderaURL
        ? `<img class="equipo-logo" src="${eVisitante.BanderaURL}" alt="${eVisitante.Nombre}">`
        : `<div class="equipo-logo placeholder-logo"><span>${escapeHtml(eVisitante.Nombre?.substring(0,1) || '?')}</span></div>`;

    let centerContent = '';
    if (bloqueado || finalizado) {
        let pLocal = pred ? pred.GolesLocal : '-';
        let pVis = pred ? pred.GolesVisitante : '-';
        centerContent = `
            <div class="score-display">
                <span class="score-box">${pLocal}</span>
                <span class="vs-text">vs</span>
                <span class="score-box">${pVis}</span>
            </div>
        `;
    } else {
        centerContent = `
            <div class="score-inputs">
                <input type="number" min="0" max="20" class="score-input ${hasValueLocal}" id="predLocal_${p.Id}" value="${predLocal}" placeholder="0" aria-label="Goles de ${escapeHtml(eLocal.Nombre || 'equipo local')}" oninput="this.classList.toggle('has-value', this.value !== '')">
                <span class="vs-text">vs</span>
                <input type="number" min="0" max="20" class="score-input ${hasValueVisitante}" id="predVisitante_${p.Id}" value="${predVisitante}" placeholder="0" aria-label="Goles de ${escapeHtml(eVisitante.Nombre || 'equipo visitante')}" oninput="this.classList.toggle('has-value', this.value !== '')">
            </div>
        `;
    }

    // Left info: Fase and Status (Time/Live)
    const timeString = p.FechaHoraCST ? p.FechaHoraCST.substring(11, 16) : '';
    const faseBadge = `<div class="fase-badge">${escapeHtml(p.Fase || 'GRUPOS')}</div>`;
    let statusHtml = '';
    if (enVivo) {
        statusHtml = `<div class="match-status live"><span class="live-dot"></span> EN VIVO</div>`;
    } else {
        statusHtml = `<div class="match-status time"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> ${timeString}</div>`;
    }

    const leftInfo = `
        <div class="match-left-info">
            ${faseBadge}
            ${statusHtml}
        </div>
    `;

    // Right side actions/stats
    let rightSide = '';
    if (finalizado && pred) {
        const pts = pred.PuntosObtenidos;
        let color = pts > 0 ? 'var(--green-500)' : 'var(--text-muted)';
        rightSide = `
            <div class="match-results">
                <div class="real-score-badge">Final: ${p.GolesLocal} - ${p.GolesVisitante}</div>
                <div class="pts-earned" style="color: ${color}">+${pts} pts</div>
            </div>
        `;
    } else if (finalizado && !pred) {
        rightSide = `
            <div class="match-results">
                <div class="real-score-badge">Final: ${p.GolesLocal} - ${p.GolesVisitante}</div>
                <div class="pts-earned" style="color: var(--text-muted)">0 pts</div>
            </div>
        `;
    } else {
        // Active match right side
        const saveBtnText = pred ? 'ACTUALIZAR' : 'GUARDAR';
        
        let cerradaHtml = '';
        let btnHtml = '';
        let iconColor = 'var(--gold-500)';
        let valColor = 'var(--gold-500)';

        if (bloqueado) {
            cerradaHtml = `
                <div class="prediccion-cerrada-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Predicción cerrada
                </div>
            `;
            iconColor = 'var(--text-muted)';
            valColor = 'var(--text-muted)';
            // The save button is completely removed when blocked
            btnHtml = ''; 
        } else {
            btnHtml = `
                <button class="icon-action-btn ${pred ? 'saved' : ''}" onclick="savePrediccion(${p.Id}, this)" id="saveBtn_${p.Id}" title="${saveBtnText}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    <span>${saveBtnText}</span>
                </button>
            `;
        }

        rightSide = `
            ${cerradaHtml}
            <div class="match-potentials">
                <div class="potential-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="${iconColor}" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg>
                    <div class="pot-text"><span class="pot-val" style="color: ${valColor};">+3 pts</span><br><span class="pot-lbl">EXACTO</span></div>
                </div>
                <div class="potential-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="${iconColor}" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    <div class="pot-text"><span class="pot-val" style="color: ${valColor};">+1 pt</span><br><span class="pot-lbl">RESULTADO</span></div>
                </div>
                ${btnHtml}
            </div>
        `;
    }

    return `
    <div class="${cardClass}" data-id="${p.Id}">
        <div class="match-row-inner">
            ${leftInfo}
            <div class="team-side local">
                <span class="team-name" style="text-align: right; width: 100%;">${escapeHtml(eLocal.Nombre || 'Por definir')}</span>
                ${logoLocal}
            </div>
            
            <div class="match-center">
                ${centerContent}
            </div>

            <div class="team-side visitante">
                ${logoVisitante}
                <span class="team-name" style="text-align: left; width: 100%;">${escapeHtml(eVisitante.Nombre || 'Por definir')}</span>
            </div>
        </div>

        <div class="match-right-actions">
            ${rightSide}
        </div>
    </div>`;
}

// ═══════════════════════════════════════════════════════════════
// SAVE PREDICTION — Solo detallada (marcador exacto)
// ═══════════════════════════════════════════════════════════════

// Actualiza sólo el texto del botón de guardar (su <span>), preservando el
// ícono SVG que lo acompaña — btnEl.textContent lo borraba por completo.
function setSaveBtnLabel(btnEl, text) {
    if (!btnEl) return;
    const span = btnEl.querySelector('span');
    if (span) {
        span.textContent = text;
    } else {
        btnEl.textContent = text;
    }
}

async function savePrediccion(idPartido, btnEl) {
    const inputLocal = document.getElementById(`predLocal_${idPartido}`);
    const inputVisitante = document.getElementById(`predVisitante_${idPartido}`);

    if (!inputLocal || !inputVisitante) return;

    const golesLocal = inputLocal.value.trim();
    const golesVisitante = inputVisitante.value.trim();

    if (golesLocal === '' || golesVisitante === '') {
        showToast('Ingresa ambos marcadores');
        return;
    }

    const gl = parseInt(golesLocal);
    const gv = parseInt(golesVisitante);

    if (isNaN(gl) || isNaN(gv) || gl < 0 || gv < 0) {
        showToast('Los goles deben ser números válidos ≥ 0');
        return;
    }

    if (btnEl) {
        btnEl.disabled = true;
        setSaveBtnLabel(btnEl, 'Guardando...');
    }

    try {
        const data = await apiPost('api/partidos/savePrediccion.php', {
            idPartido: idPartido,
            golesLocal: gl,
            golesVisitante: gv
        });

        if (data.success) {
            showToast(`${data.message} — ${data.prediccion.Fase}`);

            // Update local state
            const partido = allPartidos.find(p => p.Id === idPartido);
            if (partido) {
                partido.Prediccion = {
                    GolesLocal: gl,
                    GolesVisitante: gv,
                    PuntosObtenidos: 0,
                    FechaPrediccion: data.prediccion.FechaPrediccion
                };
            }

            if (btnEl) {
                setSaveBtnLabel(btnEl, 'Guardado');
                btnEl.classList.add('saved');
                setTimeout(() => {
                    setSaveBtnLabel(btnEl, 'Actualizar');
                    btnEl.disabled = false;
                }, 1500);
            }

            // Reset dashboard cache
            dashboardData = null;
        } else {
            showToast(`${data.error}`);
            if (btnEl) {
                setSaveBtnLabel(btnEl, 'Guardar');
                btnEl.disabled = false;
            }
        }
    } catch (e) {
        showToast('Error de conexión');
        if (btnEl) {
            setSaveBtnLabel(btnEl, 'Guardar');
            btnEl.disabled = false;
        }
    }
}
