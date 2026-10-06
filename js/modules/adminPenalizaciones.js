// ═══════════════════════════════════════════════════════════════
// ADMIN: PENALIZACIONES — Módulo de gestión
// ═══════════════════════════════════════════════════════════════

let penalizacionesData = null;
let penalizacionesUsers = [];
let editingPenalizacionId = null;

// ─── Cargar penalizaciones ───
async function loadPenalizaciones() {
    const container = document.getElementById('penalizacionesHistorial');
    if (!container) return;

    container.innerHTML = '<tr><td colspan="6" class="empty-text"><div class="spinner" style="margin:0 auto 8px"></div>Cargando...</td></tr>';

    try {
        const estado = document.getElementById('filterPenEstado')?.value || '';
        const busqueda = document.getElementById('searchPenalizaciones')?.value || '';

        const params = new URLSearchParams();
        if (estado) params.set('estado', estado);
        if (busqueda) params.set('busqueda', busqueda);

        const data = await apiGet('api/admin/managePenalizaciones.php?' + params.toString());

        if (!data.success) {
            container.innerHTML = `<tr><td colspan="6" class="empty-text">${data.error || 'Error al cargar'}</td></tr>`;
            return;
        }

        penalizacionesData = data.penalizaciones || [];
        penalizacionesUsers = data.usuarios || [];

        renderPenalizacionesResumen(data.resumen);
        renderPenalizacionUsersSelector();
        
        // Poblar selector de tablas
        const penTablaSelect = document.getElementById('penTablaSelect');
        if (penTablaSelect && typeof adminTablas !== 'undefined') {
            const currentVal = penTablaSelect.value;
            penTablaSelect.innerHTML = '';
            adminTablas.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.Id;
                opt.textContent = `${t.Nombre} (${t.TotalMiembros} miembros)`;
                penTablaSelect.appendChild(opt);
            });
            if (currentVal) penTablaSelect.value = currentVal;
            else if (adminTablas.length > 0) penTablaSelect.value = 2; // Default a tabla 2 por historial
        }
        
        renderPenalizaciones();
        setupMotivoCounter();

    } catch (e) {
        container.innerHTML = '<tr><td colspan="6" class="empty-text">Error de conexión</td></tr>';
        console.error('Error loading penalizaciones:', e);
    }
}

// ─── Renderizar KPIs ───
function renderPenalizacionesResumen(resumen) {
    if (!resumen) return;
    const el = (id) => document.getElementById(id);
    if (el('penTotal')) el('penTotal').textContent = resumen.Total || 0;
    if (el('penActivas')) el('penActivas').textContent = resumen.Activas || 0;
    if (el('penAnuladas')) el('penAnuladas').textContent = resumen.Anuladas || 0;
    if (el('penPuntosRestados')) el('penPuntosRestados').textContent = resumen.TotalPuntosRestados || 0;
}

// ─── Renderizar historial ───
function renderPenalizaciones() {
    const container = document.getElementById('penalizacionesHistorial');
    if (!container) return;

    if (!penalizacionesData || penalizacionesData.length === 0) {
        container.innerHTML = '<tr><td colspan="6" class="empty-text"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; vertical-align:middle;"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 3v5h5M16 13H8M16 17H8M10 9H8"/></svg> No hay sanciones registradas aún.</td></tr>';
        return;
    }

    let html = '';
    penalizacionesData.forEach(p => {
        const isActiva = p.Estado === 'Activa';
        const estadoClass = isActiva ? 'pen-status-activa' : 'pen-status-anulada';

        const fechaCreacion = p.FechaCreacion ? formatPenDate(p.FechaCreacion) : '—';
        const fechaMod = p.FechaModificacion ? formatPenDate(p.FechaModificacion) : '';
        
        let fechaHtml = `<span style="font-size:12px;">${fechaCreacion}</span>
            <br><span style="font-size:10px;color:var(--text-muted)">por @${escapeHtml(p.CreadaPorUsuario || '?')}</span>`;
        if (fechaMod) {
            fechaHtml += `<br><span style="font-size:10px;color:var(--blue-400)">️ ${fechaMod}</span>`;
        }

        const accionesHtml = isActiva ? `
            <div class="pen-actions-group">
                <button class="btn-pen-edit" onclick="openEditPenalizacionModal(${p.Id})" title="Modificar">
                    Editar
                </button>
                <button class="btn-pen-annul" onclick="annulPenalizacion(${p.Id})" title="Anular sanción">
                     Anular
                </button>
            </div>
        ` : `<span class="pen-anulada-label">— Anulada —</span>`;

        html += `
        <tr class="${isActiva ? '' : 'pen-row-anulada'}">
            <td data-label="Usuario">
                <strong style="color:var(--text-primary);">@${escapeHtml(p.NombreUsuario)}</strong>
                <br><span style="font-size:11px;color:var(--text-muted)">${escapeHtml(p.NombreCompleto || '')}</span>
            </td>
            <td style="text-align:center;" data-label="Puntos">
                <span class="pen-puntos-badge">−${p.PuntosRestados}</span>
            </td>
            <td data-label="Tabla"><span class="fase-badge" style="font-size: 11px;">${escapeHtml(p.NombreTabla || 'Global')}</span></td>
            <td class="pen-motivo-cell" data-label="Motivo">${escapeHtml(p.Motivo)}</td>
            <td style="text-align:center;" data-label="Estado">
                <span class="pen-status-badge ${estadoClass}">${isActiva ? 'Vigente' : 'Anulada'}</span>
            </td>
            <td data-label="Fecha">${fechaHtml}</td>
            <td style="text-align:center;" data-label="Acciones">${accionesHtml}</td>
        </tr>`;
    });

    container.innerHTML = html;
}

// ─── Selector de usuarios (checkboxes) ───
function renderPenalizacionUsersSelector() {
    const container = document.getElementById('penUsersGrid');
    if (!container) return;

    if (penalizacionesUsers.length === 0) {
        container.innerHTML = '<p class="empty-text">No hay usuarios disponibles.</p>';
        return;
    }

    let html = '';
    penalizacionesUsers.forEach(u => {
        html += `
        <label class="pen-user-checkbox" data-username="${u.NombreUsuario.toLowerCase()}" data-name="${(u.NombreCompleto || '').toLowerCase()}">
            <input type="checkbox" value="${u.Id}" class="pen-user-check">
            <span class="pen-user-info">
                <strong>@${escapeHtml(u.NombreUsuario)}</strong>
                <span>${escapeHtml(u.NombreCompleto || u.NombreUsuario)}</span>
            </span>
        </label>`;
    });

    container.innerHTML = html;
}

// ─── Filtrar usuarios ───
function filterPenUsers(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.pen-user-checkbox').forEach(label => {
        const username = label.dataset.username || '';
        const name = label.dataset.name || '';
        label.style.display = (username.includes(q) || name.includes(q)) ? '' : 'none';
    });
}

// ─── Seleccionar/Deseleccionar todos ───
function toggleAllPenUsers(selectAll) {
    document.querySelectorAll('.pen-user-check').forEach(cb => {
        const label = cb.closest('.pen-user-checkbox');
        if (label && label.style.display !== 'none') {
            cb.checked = selectAll;
        }
    });
    updatePenSelectedCount();
}

// ─── Contador de seleccionados ───
function updatePenSelectedCount() {
    const count = document.querySelectorAll('.pen-user-check:checked').length;
    const el = document.getElementById('penSelectedCount');
    if (el) {
        el.textContent = count > 0 ? `${count} seleccionado${count > 1 ? 's' : ''}` : '';
        el.style.display = count > 0 ? 'inline-flex' : 'none';
    }
}

// ─── Setup contador de caracteres del motivo ───
function setupMotivoCounter() {
    const textarea = document.getElementById('penMotivoInput');
    const counter = document.getElementById('penMotivoCount');
    if (textarea && counter) {
        textarea.removeEventListener('input', _handleMotivoInput);
        textarea.addEventListener('input', _handleMotivoInput);
    }
}

function _handleMotivoInput() {
    const counter = document.getElementById('penMotivoCount');
    if (counter) counter.textContent = this.value.length;
}

// ─── Crear penalización ───
async function createPenalizacion() {
    const checkedUsers = document.querySelectorAll('.pen-user-check:checked');
    const puntos = parseInt(document.getElementById('penPuntosInput')?.value || '0');
    const motivo = (document.getElementById('penMotivoInput')?.value || '').trim();

    if (checkedUsers.length === 0) {
        showToast('Selecciona al menos un usuario.');
        return;
    }
    if (puntos <= 0) {
        showToast('Los puntos a restar deben ser mayores a 0.');
        return;
    }
    if (!motivo) {
        showToast('El motivo es obligatorio.');
        return;
    }

    const userIds = Array.from(checkedUsers).map(cb => parseInt(cb.value));
    const usernames = Array.from(checkedUsers).map(cb => {
        const label = cb.closest('.pen-user-checkbox');
        return label ? '@' + (label.dataset.username || '?') : '?';
    });

    const idTabla = parseInt(document.getElementById('penTablaSelect')?.value) || 2;
    const tablaName = document.getElementById('penTablaSelect')?.options[document.getElementById('penTablaSelect').selectedIndex]?.text.split(' (')[0] || '';

    if (!confirm(`¿Aplicar sanción de −${puntos} puntos a ${userIds.length} usuario(s) en la tabla ${tablaName}?\n\n${usernames.join(', ')}\n\nMotivo: ${motivo}`)) {
        return;
    }

    const btn = document.getElementById('btnAplicarPenalizacion');
    if (btn) { btn.disabled = true; btn.innerHTML = 'Aplicando...'; }

    try {
        const data = await apiPost('api/admin/managePenalizaciones.php', { action: 'create', userIds, puntos, motivo, idTabla });

        if (data.success) {
            showToast(`${data.message}`);
            document.querySelectorAll('.pen-user-check:checked').forEach(cb => cb.checked = false);
            document.getElementById('penPuntosInput').value = '';
            document.getElementById('penMotivoInput').value = '';
            const counter = document.getElementById('penMotivoCount');
            if (counter) counter.textContent = '0';
            updatePenSelectedCount();
            loadPenalizaciones();
            rankingData = null;
            dashboardData = null;
        } else {
            showToast(`${data.error}`);
        }
    } catch (e) {
        showToast('Error de conexión');
    }

    if (btn) { btn.disabled = false; btn.innerHTML = 'Aplicar Sanción'; }
}

// ─── Anular penalización ───
async function annulPenalizacion(id) {
    const pen = penalizacionesData?.find(p => p.Id === id);
    if (!pen) return;

    if (!confirm(`¿Anular sanción #${id}?\n\nUsuario: @${pen.NombreUsuario}\nPuntos: −${pen.PuntosRestados}\n\nSe devolverán ${pen.PuntosRestados} puntos.`)) {
        return;
    }

    try {
        const data = await apiPost('api/admin/managePenalizaciones.php', { action: 'annul', id });

        if (data.success) {
            showToast(`${data.message}`);
            loadPenalizaciones();
            rankingData = null;
            dashboardData = null;
        } else {
            showToast(`${data.error}`);
        }
    } catch (e) {
        showToast('Error de conexión');
    }
}

// ─── Modal de edición ───
function openEditPenalizacionModal(id) {
    const pen = penalizacionesData?.find(p => p.Id === id);
    if (!pen) return;
    editingPenalizacionId = id;
    document.getElementById('editPenId').textContent = '#' + id;
    document.getElementById('editPenUser').textContent = '@' + pen.NombreUsuario + ' — ' + (pen.NombreCompleto || '');
    document.getElementById('editPenPuntos').value = pen.PuntosRestados;
    document.getElementById('editPenMotivo').value = pen.Motivo;
    document.getElementById('modalEditPenalizacion').classList.add('active');
}

function closeEditPenalizacionModal() {
    document.getElementById('modalEditPenalizacion').classList.remove('active');
    editingPenalizacionId = null;
}

async function saveEditPenalizacion(e) {
    if (e) e.preventDefault();
    if (!editingPenalizacionId) return;

    const puntos = parseInt(document.getElementById('editPenPuntos').value || '0');
    const motivo = (document.getElementById('editPenMotivo').value || '').trim();

    if (puntos <= 0) { showToast('Los puntos deben ser mayores a 0.'); return; }
    if (!motivo) { showToast('El motivo es obligatorio.'); return; }

    try {
        const data = await apiPost('api/admin/managePenalizaciones.php', { action: 'update', id: editingPenalizacionId, puntos, motivo });

        if (data.success) {
            showToast(`${data.message}`);
            closeEditPenalizacionModal();
            loadPenalizaciones();
            rankingData = null;
            dashboardData = null;
        } else {
            showToast(`${data.error}`);
        }
    } catch (e) {
        showToast('Error de conexión');
    }
}

// ─── Filtrar historial ───
function filterPenalizacionesHistorial() {
    loadPenalizaciones();
}

// ─── Formato de fecha ───
function formatPenDate(dateStr) {
    if (!dateStr) return '';
    try {
        const parts = dateStr.split(' ');
        const [y, m, d] = parts[0].split('-');
        const timePart = parts[1] ? parts[1].substring(0, 5) : '';
        const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return `${parseInt(d)} ${meses[parseInt(m) - 1]} ${timePart}`;
    } catch (e) {
        return dateStr;
    }
}
