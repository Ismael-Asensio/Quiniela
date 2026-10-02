// ADMIN SUB-TABS
// ═══════════════════════════════════════════════════════════════

function setupAdminSubTabs() {
    const subtabs = document.querySelectorAll('.admin-subtab');
    subtabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.subtab;

            // Update tab active state
            subtabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // Show/hide content
            document.querySelectorAll('.admin-subtab-content').forEach(c => {
                c.classList.toggle('active', c.dataset.subtabContent === target);
            });

            // Load membership conversion data
            if (target === 'membresias') {
                renderConvertUsers();
            }
            // Load penalizaciones data
            if (target === 'penalizaciones') {
                loadPenalizaciones();
            }
            // Load cuotas campeón data
            if (target === 'cuotasCampeon') {
                loadCuotasCampeon();
            }
        });
    });
}

// ═══════════════════════════════════════════════════════════════
// SYNC
// ═══════════════════════════════════════════════════════════════

function setupSync() {
    const btn = document.getElementById('syncBtn');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        btn.classList.add('syncing');
        btn.disabled = true;
        showToast(' Sincronizando partidos...');

        try {
            const data = await apiPost('api/partidos/syncPartidos.php');

            if (data.success) {
                const s = data.stats;
                showToast(`Sincronización completa: ${s.partidosSincronizados} partidos, ${s.equiposInsertados} equipos nuevos${s.puntosRecalculados ? `, ${s.puntosRecalculados} puntos recalculados` : ''}`);

                // Reload everything
                await loadPartidos();
                dashboardData = null;
                rankingData = null;
                if (currentSection === 'dashboards') loadDashboards();
                if (currentSection === 'ranking') loadRanking();
            } else {
                showToast(`${data.error}`);
            }
        } catch (e) {
            showToast('Error al sincronizar');
        }

        btn.classList.remove('syncing');
        btn.disabled = false;
    });
}

// ═══════════════════════════════════════════════════════════════
// ADMIN: TABLAS DE PUNTUACIÓN Y MEMBRESÍA
// ═══════════════════════════════════════════════════════════════

// ─── State for admin ───
let adminTablas = [];
let adminUsers = [];
let adminMembresias = [];
let adminSelectedTabla = 0;

function setupAdminCreateTable() {
    const btn = document.getElementById('createTableBtn');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        const nombre = document.getElementById('newTableName').value.trim();
        const descripcion = document.getElementById('newTableDesc').value.trim();
        const faseSelect = document.getElementById('newTableFaseInicio');
        const faseInicio = faseSelect ? faseSelect.value : 'Grupos';

        if (!nombre) {
            showToast('Ingresa un nombre para la tabla');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = 'Creando...';

        try {
            const data = await apiPost('api/admin/manageTables.php', { action: 'create', nombre, descripcion, faseInicio });

            if (data.success) {
                showToast(`${data.message}`);
                document.getElementById('newTableName').value = '';
                document.getElementById('newTableDesc').value = '';
                if (faseSelect) faseSelect.value = 'Grupos';
                loadAdminPermissions();
                rankingData = null;
            } else {
                showToast(`${data.error}`);
            }
        } catch (e) {
            showToast('Error de conexión');
        }

        btn.disabled = false;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Crear Tabla';
    });
}

async function loadAdminPermissions() {
    const listContainer = document.getElementById('adminUsersList');
    if (!listContainer) return;

    const selectEl = document.getElementById('competicionFilterMem');
    const compId = selectEl && selectEl.value ? selectEl.value : '';

    try {
        const data = await apiGet(`api/admin/getUsersPermissions.php?competicion=${compId}`);

        if (data.error || !data.success) {
            listContainer.innerHTML = `<tr><td colspan="3" class="empty-text">${data.error || 'Error al obtener datos'}</td></tr>`;
            return;
        }

        adminUsers = data.users || [];
        adminTablas = data.tablas || [];
        adminMembresias = data.membresias || [];

        // Populate admin table selector
        const adminSelect = document.getElementById('adminTablaSelect');
        if (adminSelect) {
            const prevVal = adminSelect.value;
            adminSelect.innerHTML = '<option value="">Selecciona una tabla</option>';
            adminTablas.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.Id;
                const faseLabel = (t.FaseInicio && t.FaseInicio !== 'Grupos') ? ` | Desde ${t.FaseInicio}` : '';
                opt.textContent = `${t.Nombre} (${t.TotalMiembros} miembros${faseLabel})`;
                adminSelect.appendChild(opt);
            });

            if (prevVal) {
                adminSelect.value = prevVal;
                adminSelectedTabla = parseInt(prevVal);
            }

            adminSelect.onchange = () => {
                adminSelectedTabla = parseInt(adminSelect.value) || 0;
                renderAdminUsers();
            };
        }

        if (adminTablas.length === 0) {
            listContainer.innerHTML = '<tr><td colspan="3" class="empty-text">Crea una tabla arriba para comenzar a gestionar miembros.</td></tr>';
            return;
        }

        renderAdminUsers();
        if (typeof renderAdminUsersList === 'function') renderAdminUsersList();
        if (typeof renderConvertUsers === 'function') renderConvertUsers();

    } catch (e) {
        listContainer.innerHTML = '<tr><td colspan="3" class="empty-text">Error de conexión</td></tr>';
    }
}

function renderAdminUsers() {
    const listContainer = document.getElementById('adminUsersList');
    if (!listContainer) return;

    if (!adminSelectedTabla) {
        listContainer.innerHTML = '<tr><td colspan="3" class="empty-text">Selecciona una tabla arriba para gestionar miembros</td></tr>';
        return;
    }

    if (adminUsers.length === 0) {
        listContainer.innerHTML = '<tr><td colspan="3" class="empty-text">No hay usuarios registrados.</td></tr>';
        return;
    }

    let html = '';
    adminUsers.forEach(u => {
        const m = adminMembresias.find(mem => mem.IdTabla === adminSelectedTabla && mem.IdUsuario === u.Id);

        const rankChecked = (m && parseInt(m.MostrarEnRanking) === 1) ? 'checked' : '';
        const verChecked = (m && parseInt(m.PuedeVer) === 1) ? 'checked' : '';
        const expChecked = (m && parseInt(m.PuedeExportar) === 1) ? 'checked' : '';

        html += `
        <tr>
            <td data-label="Usuario"><strong>@${escapeHtml(u.NombreUsuario)}</strong></td>
            <td data-label="Nombre">${escapeHtml(u.NombreCompleto || u.NombreUsuario)}</td>
            <td style="text-align: center;" data-label="Ranking">
                <label class="switch" title="Aparece en Ranking">
                    <input type="checkbox" data-user-id="${u.Id}" data-username="${u.NombreUsuario}" data-field="MostrarEnRanking" class="permission-toggle" ${rankChecked}>
                    <span class="slider"></span>
                </label>
            </td>
            <td style="text-align: center;" data-label="Puede Ver">
                <label class="switch" title="Puede Ver">
                    <input type="checkbox" data-user-id="${u.Id}" data-username="${u.NombreUsuario}" data-field="PuedeVer" class="permission-toggle" ${verChecked}>
                    <span class="slider"></span>
                </label>
            </td>
            <td style="text-align: center;" data-label="Puede Exportar">
                <label class="switch" title="Puede Exportar">
                    <input type="checkbox" data-user-id="${u.Id}" data-username="${u.NombreUsuario}" data-field="PuedeExportar" class="permission-toggle" ${expChecked}>
                    <span class="slider"></span>
                </label>
            </td>
        </tr>`;
    });
    listContainer.innerHTML = html;

    // Add event listeners for permission toggles
    document.querySelectorAll('.permission-toggle').forEach(chk => {
        chk.addEventListener('change', async () => {
            const userId = parseInt(chk.dataset.userId);
            const username = chk.dataset.username;
            const field = chk.dataset.field;
            const value = chk.checked ? 1 : 0;

            try {
                const data = await apiPost('api/admin/updateUserPermission.php', { userId, idTabla: adminSelectedTabla, field, value });

                if (data.success) {
                    let mem = adminMembresias.find(m => m.IdTabla === adminSelectedTabla && m.IdUsuario === userId);
                    if (!mem) {
                        mem = { IdTabla: adminSelectedTabla, IdUsuario: userId, PuedeVer: 0, PuedeExportar: 0, MostrarEnRanking: 0 };
                        adminMembresias.push(mem);
                    }
                    mem[field] = value;
                    showToast(`Permiso actualizado para @${username}`);
                    rankingData = null;
                } else {
                    showToast(`Error: ${data.error}`);
                    chk.checked = !chk.checked;
                }
            } catch (err) {
                showToast('Error de conexión');
                chk.checked = !chk.checked;
            }
        });
    });
}

// ═══════════════════════════════════════════════════════════════
// ADMIN: GESTIÓN DE USUARIOS (Crear y Eliminar)
// ═══════════════════════════════════════════════════════════════

function setupAdminUsers() {
    const btn = document.getElementById('createUserBtn');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        const nombre = document.getElementById('newUserName').value.trim();
        const usuario = document.getElementById('newUserUsername').value.trim();
        const contrasena = document.getElementById('newUserPassword').value.trim();

        if (!usuario || !contrasena) {
            showToast('Usuario y contraseña son obligatorios');
            return;
        }

        if (usuario.length < 3) {
            showToast('El usuario debe tener al menos 3 caracteres');
            return;
        }

        if (contrasena.length < 3) {
            showToast('La contraseña debe tener al menos 3 caracteres');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = 'Creando...';

        try {
            const data = await apiPost('api/auth/register.php', {
                usuario: usuario,
                contrasena: contrasena,
                nombreCompleto: nombre || usuario
            });

            if (data.success) {
                showToast(`Usuario @${usuario} creado exitosamente`);
                document.getElementById('newUserName').value = '';
                document.getElementById('newUserUsername').value = '';
                document.getElementById('newUserPassword').value = '';
                loadAdminPermissions();
            } else {
                showToast(`${data.error}`);
            }
        } catch (e) {
            showToast('Error de conexión');
        }

        btn.disabled = false;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Crear Usuario';
    });
}

function renderAdminUsersList() {
    const listContainer = document.getElementById('adminUsersManageList');
    if (!listContainer) return;

    if (adminUsers.length === 0) {
        listContainer.innerHTML = '<tr><td colspan="5" class="empty-text">No hay usuarios registrados.</td></tr>';
        return;
    }

    const rolesMap = { 1: 'Admin', 2: 'Tesorero', 3: 'Miembro', 4: 'Usuario' };

    let html = '';
    adminUsers.forEach(u => {
        const esAdmin = u.EsAdmin ? 1 : 0;
        const rolId = u.IdRol || 4;
        const montoPagado = parseFloat(u.MontoPagado || 0).toFixed(2);

        let selectHtml = `<select id="rol_select_${u.Id}" class="admin-input" style="padding: 4px; width: 120px;" ${esAdmin ? 'disabled' : ''}>`;
        for (const [id, label] of Object.entries(rolesMap)) {
            selectHtml += `<option value="${id}" ${rolId == id ? 'selected' : ''}>${label}</option>`;
        }
        selectHtml += `</select>`;

        const deleteBtn = esAdmin
            ? '<span class="admin-no-action">—</span>'
            : `<button class="btn-delete-user" style="display:inline-flex; align-items:center; gap:4px;" onclick="deleteUser(${u.Id}, '${escapeHtml(u.NombreUsuario)}')" title="Eliminar usuario">
                 <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2-2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> Eliminar
               </button>`;

        const saveBtn = esAdmin
            ? ''
            : `<button class="btn btn-primary" onclick="saveUserMembership(${u.Id})" style="padding: 4px 8px; font-size: 12px; margin-left: 5px; display:inline-flex; align-items:center; gap:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Guardar</button>`;

        html += `
        <tr data-rol-id="${rolId}" class="admin-user-row">
            <td class="username-cell" data-label="Usuario"><strong>@${escapeHtml(u.NombreUsuario)}</strong></td>
            <td class="name-cell" data-label="Nombre">${escapeHtml(u.NombreCompleto || u.NombreUsuario)}</td>
            <td data-label="Rol">${selectHtml}</td>
            <td data-label="Monto">
                <input type="number" id="monto_input_${u.Id}" class="admin-input" style="padding: 4px; width: 80px;" value="${montoPagado}" min="0" step="0.01" ${esAdmin ? 'disabled' : ''}>
            </td>
            <td style="text-align: center;" data-label="Acciones">${saveBtn} ${deleteBtn}</td>
        </tr>`;
    });
    listContainer.innerHTML = html;
}

function filterAdminUsersList() {
    const textQuery = (document.getElementById('adminSearchUsers')?.value || '').toLowerCase();
    const roleQuery = document.getElementById('adminRoleFilter')?.value || '';

    const rows = document.querySelectorAll('#adminUsersManageList .admin-user-row');
    rows.forEach(row => {
        const username = row.querySelector('.username-cell').textContent.toLowerCase();
        const name = row.querySelector('.name-cell').textContent.toLowerCase();
        const rolId = row.getAttribute('data-rol-id');

        const matchesText = username.includes(textQuery) || name.includes(textQuery);
        const matchesRole = roleQuery === '' || rolId === roleQuery;

        if (matchesText && matchesRole) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

async function saveUserMembership(userId) {
    const rolSelect = document.getElementById('rol_select_' + userId);
    const montoInput = document.getElementById('monto_input_' + userId);
    if (!rolSelect || !montoInput) return;

    const newRol = parseInt(rolSelect.value);
    const newMonto = parseFloat(montoInput.value);

    try {
        const data = await apiPost('api/admin/updateUserAndMembership.php', { userId: userId, idRol: newRol, montoPagado: newMonto });

        if (data.success) {
            showToast('Cambios guardados correctamente');
            // Actualizar la variable adminUsers en memoria para que el filtro y exportación funcionen bien
            const u = adminUsers.find(x => x.Id == userId);
            if (u) {
                u.IdRol = newRol;
                u.MontoPagado = newMonto;
            }
            // Update UI row to reflect new rolId for filtering
            const row = rolSelect.closest('tr');
            if (row) row.setAttribute('data-rol-id', newRol);
        } else {
            showToast('Error: ' + data.error);
        }
    } catch (e) {
        showToast('Error al guardar datos');
    }
}

function exportAdminUsersCSV() {
    // Only export visible rows
    const rows = document.querySelectorAll('#adminUsersManageList .admin-user-row');
    const rolesMap = { 1: 'Admin', 2: 'Tesorero', 3: 'Miembro', 4: 'Usuario' };

    let csvContent = "Usuario,Nombre Completo,Rol,Monto Pagado\n";

    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const username = row.querySelector('.username-cell').textContent.replace('@', '').trim();
            const name = row.querySelector('.name-cell').textContent.trim();
            const rolId = row.getAttribute('data-rol-id');
            const rolStr = rolesMap[rolId] || 'Usuario';
            const montoInput = row.querySelector('input[type="number"]');
            const monto = montoInput ? montoInput.value : '0.00';

            // Escape comas en el nombre
            const cleanName = name.includes(',') ? `"${name}"` : name;

            csvContent += `${username},${cleanName},${rolStr},${monto}\n`;
        }
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", "usuarios_quiniela.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

async function deleteUser(userId, username) {
    if (!confirm(`¿Estás seguro de que deseas eliminar al usuario @${username}?\n\nEsta acción eliminará todas sus predicciones, membresías y datos asociados. No se puede deshacer.`)) {
        return;
    }

    try {
        const data = await apiPost('api/admin/deleteUser.php', { userId: userId });

        if (data.success) {
            showToast(`${data.message}`);
            loadAdminPermissions();
            rankingData = null;
        } else {
            showToast(`${data.error}`);
        }
    } catch (e) {
        showToast('Error de conexión');
    }
}

// ═══════════════════════════════════════════════════════════════
// CUOTAS CAMPEÓN — Módulo de cuotas fijas y exclusiones
// ═══════════════════════════════════════════════════════════════

let cuotasCampeonData = null;

async function loadCuotasCampeon() {
    const container = document.getElementById('cuotasCampeonList');
    container.innerHTML = '<div class="loading"><div class="spinner"></div>Cargando equipos...</div>';

    try {
        const data = await apiGet('api/admin/getCuotasCampeon.php');

        if (!data.success) {
            container.innerHTML = `<p class="empty-text">${data.error || 'Error al cargar'}</p>`;
            return;
        }

        cuotasCampeonData = data.equipos || [];
        renderCuotasCampeonList(cuotasCampeonData);
    } catch (e) {
        container.innerHTML = '<p class="empty-text">Error de conexión</p>';
        console.error(e);
    }
}

function renderCuotasCampeonList(equipos) {
    const container = document.getElementById('cuotasCampeonList');

    if (!equipos || equipos.length === 0) {
        container.innerHTML = '<p class="empty-text">No hay equipos registrados.</p>';
        return;
    }

    let html = '';
    equipos.forEach(eq => {
        const flagHtml = eq.BanderaURL
            ? `<img src="${eq.BanderaURL}" alt="${escapeHtml(eq.Nombre)}" class="equipo-logo" style="width:36px;height:36px;border-radius:6px;" onerror="this.style.display='none'">`
            : '<div class="equipo-logo" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-size:14px;background:var(--surface-light);border-radius:6px;"></div>';

        // Estado badge
        let estadoBadge = '';
        if (eq.Excluido) {
            estadoBadge = '<span class="ccf-badge ccf-badge-excluded">Excluido</span>';
        } else if (eq.TieneCuotaFija) {
            estadoBadge = '<span class="ccf-badge ccf-badge-fixed">Fija</span>';
        } else {
            estadoBadge = '<span class="ccf-badge ccf-badge-dynamic">Dinámica</span>';
        }

        // Cuota que se muestra
        const cuotaDisplay = eq.TieneCuotaFija && !eq.Excluido
            ? parseFloat(eq.CuotaFija).toFixed(2)
            : parseFloat(eq.CuotaDinamica).toFixed(2);

        const cuotaFijaVal = eq.TieneCuotaFija ? parseFloat(eq.CuotaFija).toFixed(2) : '';
        const isExcluido = eq.Excluido ? 'checked' : '';
        const cardClass = eq.Excluido ? 'ccf-card ccf-card-excluded' : 'ccf-card';

        html += `
        <div class="${cardClass}" id="ccfCard_${eq.IdEquipo}" data-nombre="${escapeHtml(eq.Nombre).toLowerCase()}">
            <div class="ccf-card-header">
                ${flagHtml}
                <div class="ccf-card-info">
                    <span class="ccf-card-name">${escapeHtml(eq.Nombre)}</span>
                    <div class="ccf-card-meta">
                        ${estadoBadge}
                        <span style="font-size:11px;color:var(--text-muted);">Dinámica: ${parseFloat(eq.CuotaDinamica).toFixed(2)}</span>
                    </div>
                </div>
            </div>
            <div class="ccf-card-controls">
                <div class="ccf-control-group">
                    <label class="ccf-label">Cuota Fija</label>
                    <input type="number" class="ccf-input" id="ccfCuota_${eq.IdEquipo}"
                           value="${cuotaFijaVal}" placeholder="${parseFloat(eq.CuotaDinamica).toFixed(2)}"
                           min="1.01" step="0.01">
                </div>
                <div class="ccf-control-group">
                    <label class="ccf-label">Excluir</label>
                    <label class="ccf-toggle">
                        <input type="checkbox" id="ccfExcl_${eq.IdEquipo}" ${isExcluido} onchange="toggleExcluidoCampeon(${eq.IdEquipo})">
                        <span class="ccf-toggle-slider"></span>
                    </label>
                </div>
                <div class="ccf-card-actions">
                    <button class="btn btn-primary ccf-btn-save" style="padding: 8px; border-radius: 8px;" onclick="saveCuotaCampeon(${eq.IdEquipo})" title="Guardar cuota fija">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </button>
                    ${eq.TieneCuotaFija ? `
                    <button class="btn ccf-btn-remove" style="padding: 8px; border-radius: 8px; background-color: var(--red-500); color: white; border: none;" onclick="removeCuotaCampeon(${eq.IdEquipo})" title="Quitar cuota fija">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>` : ''}
                </div>
            </div>
        </div>`;
    });

    container.innerHTML = html;
}

function filterCuotasCampeonList(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.ccf-card').forEach(card => {
        const nombre = card.dataset.nombre || '';
        card.style.display = nombre.includes(q) ? '' : 'none';
    });
}

async function saveCuotaCampeon(idEquipo) {
    const inputCuota = document.getElementById(`ccfCuota_${idEquipo}`);
    const inputExcl = document.getElementById(`ccfExcl_${idEquipo}`);

    const cuotaFija = parseFloat(inputCuota?.value);
    const excluido = inputExcl?.checked ? 1 : 0;

    if (!excluido && (!cuotaFija || cuotaFija < 1.01)) {
        showToast('Ingresa una cuota válida (mayor a 1.00)');
        return;
    }

    try {
        const data = await apiPost('api/admin/saveCuotaCampeon.php', { idEquipo, cuotaFija: cuotaFija || 1.01, excluido });

        if (data.success) {
            showToast(`${data.message}`);
            loadCuotasCampeon(); // Recargar lista
        } else {
            showToast(`${data.error}`);
        }
    } catch (e) {
        showToast('Error de conexión');
        console.error(e);
    }
}

async function removeCuotaCampeon(idEquipo) {
    if (!confirm('¿Eliminar la cuota fija? El equipo volverá a usar el cálculo dinámico.')) return;

    try {
        const data = await apiPost('api/admin/removeCuotaCampeon.php', { idEquipo });

        if (data.success) {
            showToast(`${data.message}`);
            loadCuotasCampeon(); // Recargar lista
        } else {
            showToast(`${data.error}`);
        }
    } catch (e) {
        showToast('Error de conexión');
        console.error(e);
    }
}

function toggleExcluidoCampeon(idEquipo) {
    const inputExcl = document.getElementById(`ccfExcl_${idEquipo}`);
    const card = document.getElementById(`ccfCard_${idEquipo}`);

    if (inputExcl?.checked) {
        card?.classList.add('ccf-card-excluded');
    } else {
        card?.classList.remove('ccf-card-excluded');
    }
}
