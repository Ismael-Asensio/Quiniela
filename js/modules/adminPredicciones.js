// ═══════════════════════════════════════════════════════════════
// GESTIÓN DE PREDICCIONES (ADMIN)
// ═══════════════════════════════════════════════════════════════

let adminPrediccionesData = [];
let adminPrediccionesUsers = [];
let adminPrediccionesMatches = [];

async function loadAdminPrediccionesOptions() {
    try {
        const data = await apiGet('api/admin/getOptionsAdminPredicciones.php');
        
        if (data.success) {
            adminPrediccionesUsers = data.usuarios;
            adminPrediccionesMatches = data.partidos;
            
            // Llenar selects del Modal
            const modalUser = document.getElementById('adminPredUser');
            const modalMatch = document.getElementById('adminPredMatch');
            
            modalUser.innerHTML = '<option value="">Selecciona un usuario...</option>';
            adminPrediccionesUsers.forEach(u => {
                modalUser.innerHTML += `<option value="${u.Id}">${escapeHtml(u.NombreCompleto)} (@${escapeHtml(u.NombreUsuario)})</option>`;
            });
            
            modalMatch.innerHTML = '<option value="">Selecciona un partido...</option>';
            adminPrediccionesMatches.forEach(p => {
                modalMatch.innerHTML += `<option value="${p.Id}">${p.FechaHoraCST} - ${escapeHtml(p.NombreLocal)} vs ${escapeHtml(p.NombreVisitante)} [${p.Estado}]</option>`;
            });
        }
    } catch (e) {
        console.error("Error loading predicciones options:", e);
    }
}

let adminPredPage = 1;
let adminPredTotalPages = 1;
let adminPredSearchTimeout = null;

async function loadAdminPredicciones(page = 1) {
    adminPredPage = page;
    const list = document.getElementById('adminPrediccionesList');
    list.innerHTML = '<tr><td colspan="7" class="empty-text">Cargando...</td></tr>';
    
    const userTerm = document.getElementById('adminSearchPrediccionUser').value.trim();
    const matchTerm = document.getElementById('adminSearchPrediccionMatch').value.trim();
    const compFilter = document.getElementById('competicionFilterPred')?.value || '';
    
    const url = `api/admin/getAdminPredicciones.php?page=${page}&limit=50&searchUser=${encodeURIComponent(userTerm)}&searchMatch=${encodeURIComponent(matchTerm)}&idCompeticion=${encodeURIComponent(compFilter)}`;
    
    try {
        const data = await apiGet(url);
        
        if (data.success) {
            adminPrediccionesData = data.predicciones;
            if (data.pagination) {
                adminPredTotalPages = data.pagination.totalPages;
            } else {
                adminPredTotalPages = 1;
            }
            renderAdminPredicciones(adminPrediccionesData);
        } else {
            list.innerHTML = `<tr><td colspan="7" class="empty-text">Error: ${data.error}</td></tr>`;
        }
    } catch (e) {
        console.error(e);
        list.innerHTML = '<tr><td colspan="7" class="empty-text">Error de conexión</td></tr>';
    }
}

function filterAdminPredicciones() {
    if (adminPredSearchTimeout) {
        clearTimeout(adminPredSearchTimeout);
    }
    // Esperar 400ms después de que el usuario deje de escribir para hacer la consulta
    adminPredSearchTimeout = setTimeout(() => {
        loadAdminPredicciones(1);
    }, 400);
}

function renderAdminPredicciones(dataToRender) {
    const list = document.getElementById('adminPrediccionesList');
    if (!dataToRender || dataToRender.length === 0) {
        list.innerHTML = '<tr><td colspan="7" class="empty-text">No hay predicciones que coincidan con los filtros.</td></tr>';
        return;
    }
    
    let html = '';
    dataToRender.forEach(p => {
        const realScore = p.RealLocal !== null ? `${p.RealLocal} - ${p.RealVisitante}` : '—';
        const predScore = `${p.GolesLocal} - ${p.GolesVisitante}`;
        const pts = p.PuntosObtenidos > 0 ? `<strong style="color:var(--green-400)">${p.PuntosObtenidos}</strong>` : p.PuntosObtenidos;
        
        const flagL = p.BanderaLocal ? `<img src="${p.BanderaLocal}" alt="${escapeHtml(p.NombreLocal || '')}" style="width:16px; height:16px; border-radius:2px; vertical-align:middle; margin-right:4px">` : '';
        const flagV = p.BanderaVisitante ? `<img src="${p.BanderaVisitante}" alt="${escapeHtml(p.NombreVisitante || '')}" style="width:16px; height:16px; border-radius:2px; vertical-align:middle; margin-left:4px">` : '';
        
        html += `
        <tr>
            <td data-label="Fecha Pred." style="font-size:11px; color:var(--text-muted);">${p.FechaPrediccion}</td>
            <td data-label="Persona"><strong>@${escapeHtml(p.NombreUsuario)}</strong><br><span style="font-size:11px; color:var(--text-muted);">${escapeHtml(p.NombreCompleto)}</span></td>
            <td data-label="Partido">${flagL}${escapeHtml(p.Partido)}${flagV}<br><span style="font-size:10px; color:var(--blue-400);">${p.FechaHoraCST} [${p.Estado}]</span></td>
            <td data-label="Marcador Real" style="text-align:center;">${realScore}</td>
            <td data-label="Predicción" style="text-align:center; font-weight:700;">${predScore}</td>
            <td data-label="Puntos" style="text-align:center;">${pts}</td>
            <td data-label="Acciones" style="text-align:center;">
                <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;" onclick="openAdminPrediccionModal(${p.IdUsuario}, ${p.IdPartido}, ${p.GolesLocal}, ${p.GolesVisitante})"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px; vertical-align:middle;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>Editar</button>
            </td>
        </tr>
        `;
    });
    
    // Añadir controles de paginación al final de la tabla
    const prevDisabled = adminPredPage <= 1 ? 'disabled' : '';
    const nextDisabled = adminPredPage >= adminPredTotalPages ? 'disabled' : '';
    
    html += `
        <tr>
            <td colspan="7" style="text-align:center; padding:16px;">
                <button class="btn btn-secondary" style="margin-right: 12px; padding: 6px 12px;" onclick="loadAdminPredicciones(${adminPredPage - 1})" ${prevDisabled}>&laquo; Anterior</button>
                <span style="font-size:13px; color:var(--text-muted);">Página <strong>${adminPredPage}</strong> de <strong>${adminPredTotalPages || 1}</strong></span>
                <button class="btn btn-secondary" style="margin-left: 12px; padding: 6px 12px;" onclick="loadAdminPredicciones(${adminPredPage + 1})" ${nextDisabled}>Siguiente &raquo;</button>
            </td>
        </tr>
    `;
    
    list.innerHTML = html;
}

function openAdminPrediccionModal(idUser = '', idMatch = '', gL = '', gV = '') {
    const modal = document.getElementById('modalAdminPrediccion');
    const title = document.getElementById('modalAdminPrediccionTitle');
    const userSel = document.getElementById('adminPredUser');
    const matchSel = document.getElementById('adminPredMatch');
    const inputL = document.getElementById('adminPredGolesL');
    const inputV = document.getElementById('adminPredGolesV');
    
    // Si viene vacío, es crear, si no, es editar
    if (idUser && idMatch) {
        title.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:middle;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Editar Predicción';
        userSel.value = idUser;
        matchSel.value = idMatch;
        userSel.disabled = true;
        matchSel.disabled = true;
        inputL.value = gL;
        inputV.value = gV;
        
        const searchUserEl = document.getElementById('modalSearchUser');
        const searchMatchEl = document.getElementById('modalSearchMatch');
        searchUserEl.value = 'Usuario seleccionado';
        searchMatchEl.value = 'Partido seleccionado';
        searchUserEl.disabled = true;
        searchMatchEl.disabled = true;
    } else {
        title.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Nueva Predicción';
        userSel.value = '';
        matchSel.value = '';
        userSel.disabled = false;
        matchSel.disabled = false;
        inputL.value = '';
        inputV.value = '';
        
        const searchUserEl = document.getElementById('modalSearchUser');
        const searchMatchEl = document.getElementById('modalSearchMatch');
        searchUserEl.value = '';
        searchMatchEl.value = '';
        searchUserEl.disabled = false;
        searchMatchEl.disabled = false;
    }
    
    // Repoblar las listas iniciales del modal
    if (!idUser || !idMatch) {
        filterModalPrediccionUsers();
        filterModalPrediccionMatches();
    }
    
    modal.classList.add('active');
}

function closeAdminPrediccionModal() {
    document.getElementById('modalAdminPrediccion').classList.remove('active');
}

async function saveAdminPrediccion(e) {
    e.preventDefault();
    
    const idUser = document.getElementById('adminPredUser').value;
    const idMatch = document.getElementById('adminPredMatch').value;
    const gL = document.getElementById('adminPredGolesL').value;
    const gV = document.getElementById('adminPredGolesV').value;
    
    if (!idUser || !idMatch || gL === '' || gV === '') {
        showToast('Completa todos los campos');
        return;
    }
    
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    
    try {
        const data = await apiPost('api/admin/createAdminPrediccion.php', {
            IdUsuario: idUser,
            IdPartido: idMatch,
            GolesLocal: gL,
            GolesVisitante: gV
        });
        if (data.success) {
            showToast('Predicción guardada');
            closeAdminPrediccionModal();
            loadAdminPredicciones();
        } else {
            showToast('' + data.error);
        }
    } catch (err) {
        showToast('Error de conexión');
    }
    
    btn.disabled = false;
    btn.innerHTML = 'Guardar Predicción';
}
function normalizeText(text) {
    if (!text) return '';
    return text.toString().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

function filterModalPrediccionUsers() {
    const term = normalizeText(document.getElementById('modalSearchUser').value);
    const searchWords = term.split(' ').filter(w => w.trim() !== '');
    const dropdown = document.getElementById('dropdownAdminUser');
    let html = '';
    
    if (adminPrediccionesUsers && adminPrediccionesUsers.length > 0) {
        let count = 0;
        adminPrediccionesUsers.forEach(u => {
            const nc = u.NombreCompleto || '';
            const nu = u.NombreUsuario || '';
            const textToSearch = normalizeText(`${nc} @${nu}`);
            
            const matchesAll = searchWords.length === 0 || searchWords.every(word => textToSearch.includes(word));
            
            if (matchesAll) {
                const text = `${escapeHtml(nc)} (@${escapeHtml(nu)})`;
                html += `<button type="button" class="custom-select-option" data-val="${u.Id}">${text}</button>`;
                count++;
            }
        });
        if (count === 0) {
            html = '<div class="custom-select-option empty">Sin resultados</div>';
        }
    } else {
        html = '<div class="custom-select-option empty">No hay usuarios</div>';
    }
    dropdown.innerHTML = html;
}

function filterModalPrediccionMatches() {
    const term = normalizeText(document.getElementById('modalSearchMatch').value);
    const searchWords = term.split(' ').filter(w => w.trim() !== '');
    const dropdown = document.getElementById('dropdownAdminMatch');
    let html = '';
    
    if (adminPrediccionesMatches && adminPrediccionesMatches.length > 0) {
        let count = 0;
        adminPrediccionesMatches.forEach(p => {
            const nl = p.NombreLocal || '';
            const nv = p.NombreVisitante || '';
            const fh = p.FechaHoraCST || '';
            const est = p.Estado || '';
            
            const textToSearch = normalizeText(`${fh} ${nl} ${nv} ${est}`);
            
            const matchesAll = searchWords.length === 0 || searchWords.every(word => textToSearch.includes(word));
            
            if (matchesAll) {
                const text = `${escapeHtml(fh)} - ${escapeHtml(nl)} vs ${escapeHtml(nv)} [${escapeHtml(est)}]`;
                html += `<button type="button" class="custom-select-option" data-val="${p.Id}">${text}</button>`;
                count++;
            }
        });
        if (count === 0) {
            html = '<div class="custom-select-option empty">Sin resultados</div>';
        }
    } else {
        html = '<div class="custom-select-option empty">No hay partidos</div>';
    }
    dropdown.innerHTML = html;
}

function openCustomSelect(wrapperId) {
    document.getElementById(wrapperId).classList.add('open');
}

function selectCustomOption(wrapperId, hiddenId, inputId, val, text) {
    document.getElementById(hiddenId).value = val;
    document.getElementById(inputId).value = text;
    document.getElementById(wrapperId).classList.remove('open');
}

// Delegación de eventos: los botones de las opciones se regeneran en cada
// filtrado (dropdown.innerHTML = html), así que el listener vive en el
// contenedor estático en vez de en cada opción — y lee el texto ya
// decodificado del propio botón, evitando interpolar nombres de usuario o
// partidos (que pueden traer comillas/apóstrofes) dentro de un onclick="".
function setupCustomSelectDelegation(dropdownId, wrapperId, hiddenId, inputId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;
    dropdown.addEventListener('click', (e) => {
        const opt = e.target.closest('.custom-select-option');
        if (!opt || opt.classList.contains('empty')) return;
        selectCustomOption(wrapperId, hiddenId, inputId, opt.dataset.val, opt.textContent);
    });
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.custom-select-wrapper')) {
        document.querySelectorAll('.custom-select-wrapper.open').forEach(w => w.classList.remove('open'));
    }
});

// Inicializar cuando se abre la pestaña Admin -> Predicciones
document.addEventListener('DOMContentLoaded', () => {
    setupCustomSelectDelegation('dropdownAdminUser', 'wrapperAdminUser', 'adminPredUser', 'modalSearchUser');
    setupCustomSelectDelegation('dropdownAdminMatch', 'wrapperAdminMatch', 'adminPredMatch', 'modalSearchMatch');

    // Escuchar cambios de tabs
    const subtabs = document.querySelectorAll('.admin-subtab');
    subtabs.forEach(tab => {
        tab.addEventListener('click', () => {
            if (tab.dataset.subtab === 'predicciones') {
                if (adminPrediccionesUsers.length === 0) {
                    loadAdminPrediccionesOptions().then(() => loadAdminPredicciones());
                } else {
                    loadAdminPredicciones();
                }
            }
        });
    });
});
