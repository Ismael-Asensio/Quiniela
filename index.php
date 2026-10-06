<?php
require_once __DIR__ . '/config/database.php';

AuthMiddleware::configureSessionCookie();
session_start();

if (!isset($_SESSION['IdUsuario'])) {
    header('Location: login.php');
    exit;
}
$nombreUsuario = htmlspecialchars($_SESSION['NombreUsuario']);
$nombreCompleto = htmlspecialchars($_SESSION['NombreCompleto'] ?? $_SESSION['NombreUsuario']);
$idRol = isset($_SESSION['IdRol']) ? intval($_SESSION['IdRol']) : 4;

$esAdmin = Config::isAdmin($nombreUsuario);
$esTesorero = ($idRol === 2);
$esMiembro = ($idRol === 3);

// Nombre de la competición activa para la marca del header — la app no
// está atada a un solo torneo (ver PRODUCT.md § Brand Commitments), así
// que el nombre se lee de BD en vez de codificarse. Si la BD no responde,
// el shell igual debe renderizar (antes de este cambio no dependía de BD).
$nombreCompeticion = 'Quiniela';
try {
    foreach ((new CompeticionRepository())->findAll() as $c) {
        if (!empty($c['Activa'])) {
            $nombreCompeticion = $c['Nombre'];
            break;
        }
    }
} catch (\Throwable $e) {
    // Se mantiene el nombre genérico por defecto.
}

// Cache-busting real: invalida solo cuando el archivo realmente cambia,
// a diferencia de time() (invalidaba en cada request sin necesidad).
function assetVersion(string $relativePath): string
{
    $fullPath = __DIR__ . '/' . $relativePath;
    return file_exists($fullPath) ? (string)filemtime($fullPath) : (string)time();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($nombreCompeticion); ?> — Dashboard de predicciones, puntos y ranking">
    <title><?php echo htmlspecialchars($nombreCompeticion); ?> — Dashboard</title>
    <script>
        // Aplica el tema guardado ANTES del primer pintado, para evitar el
        // parpadeo claro→oscuro (o viceversa) al cargar la página.
        (function () {
            try {
                var saved = localStorage.getItem('quiniela-theme');
                if (saved === 'light' || saved === 'dark') {
                    document.documentElement.setAttribute('data-theme', saved);
                }
            } catch (e) { /* localStorage no disponible */ }
        })();
    </script>
    <link rel="stylesheet" href="css/theme.css?v=<?php echo assetVersion('css/theme.css'); ?>">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo assetVersion('css/styles.css'); ?>">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%23facc15'/%3E%3Cpath d='M6 9H4.5a2.5 2.5 0 0 1 0-5H6 M18 9h1.5a2.5 2.5 0 0 0 0-5H18 M4 22h16 M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22 M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22 M18 2H6v7a6 6 0 0 0 12 0V2Z' fill='none' stroke='%23071019' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<!-- ═══ HEADER (Unified Navbar) ═══ -->
<header class="header">
    <div class="header-inner">
        <!-- Logo -->
        <div class="logo">
            <span class="logo-mark" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg></span>
            <h1 class="logo-text">Quiniela<span class="logo-comp"><?php echo htmlspecialchars($nombreCompeticion); ?></span></h1>
        </div>

        <!-- Navigation Tabs -->
        <nav class="main-nav" aria-label="Navegación principal">
            <div class="nav-tabs" role="tablist" aria-label="Secciones">
                <button class="nav-tab active" data-section="predicciones" id="navTab-predicciones" role="tab" aria-selected="true" aria-controls="sectionPredicciones">
                    <span class="nav-tab-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 12l4-4-4-4M12 12l-4 4-4-4M12 12l-5 2M12 12l5 2M12 12v5"></path></svg></span>
                    <span class="nav-tab-text">Predicciones</span>
                </button>
                <button class="nav-tab" data-section="dashboards" id="navTab-dashboards" role="tab" aria-selected="false" aria-controls="sectionDashboards">
                    <span class="nav-tab-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg></span>
                    <span class="nav-tab-text">Mis Puntos</span>
                </button>
                <button class="nav-tab" data-section="ranking" id="navTab-ranking" role="tab" aria-selected="false" aria-controls="sectionRanking">
                    <span class="nav-tab-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg></span>
                    <span class="nav-tab-text">Ranking</span>
                </button>
                <?php if ($esTesorero || $esAdmin): ?>
                <button class="nav-tab" data-section="tesorero" id="navTab-tesorero" role="tab" aria-selected="false" aria-controls="sectionTesorero">
                    <span class="nav-tab-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg></span>
                    <span class="nav-tab-text">Tesorería</span>
                </button>
                <?php endif; ?>
                <?php if ($esAdmin): ?>
                <button class="nav-tab" data-section="admin" id="navTab-admin" role="tab" aria-selected="false" aria-controls="sectionAdmin">
                    <span class="nav-tab-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg></span>
                    <span class="nav-tab-text">Admin</span>
                </button>
                <?php endif; ?>
            </div>
        </nav>

        <!-- Right Side: Stats & User Menu -->
        <div class="header-right">
            <div class="quick-stats">
                <div class="stat-pill puntos">
                    <span class="dot"></span>
                    <span id="statPuntos">0</span> pts
                </div>
                <div class="stat-pill posicion">
                    <span class="dot"></span>
                    #<span id="statPosicion">-</span>
                </div>
            </div>

            <?php if ($esAdmin): ?>
            <!-- Sync Button -->
            <button class="sync-btn" id="syncBtn" title="Sincronizar partidos desde API" aria-label="Sincronizar partidos desde API">
                <span class="sync-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg></span>
            </button>
            <?php endif; ?>

            <!-- Theme Toggle -->
            <button class="theme-toggle" aria-pressed="false">
                <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path></svg>
            </button>

            <!-- User Menu -->
            <div class="user-menu" id="userMenu">
                <button class="user-menu-btn" id="userMenuBtn" title="<?php echo $nombreUsuario; ?>" aria-label="Menú de usuario: <?php echo $nombreUsuario; ?>" aria-haspopup="true" aria-expanded="false" aria-controls="userDropdown">
                    <span class="user-avatar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
                    <span class="user-name"><?php echo $nombreUsuario; ?></span>
                    <span class="user-chevron" aria-hidden="true">▾</span>
                </button>
                <div class="user-dropdown" id="userDropdown" role="menu">
                    <div class="user-dropdown-header">
                        <span class="user-dropdown-name"><?php echo $nombreCompleto; ?></span>
                        <span class="user-dropdown-user">@<?php echo $nombreUsuario; ?></span>
                    </div>
                    <div class="user-dropdown-divider"></div>
                    <button class="user-dropdown-item" role="menuitem" onclick="logout()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Cerrar Sesión
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- ═══ SECTION: PREDICCIONES ═══ -->
<section class="section" id="sectionPredicciones" role="tabpanel" aria-labelledby="navTab-predicciones" tabindex="-1">
    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-group">
            <label>Fase:</label>
            <select class="filter-select" id="filterFase">
                <option value="">Todas las fases</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Fecha:</label>
            <select class="filter-select" id="filterFecha">
                <option value="">Todas las fechas</option>
            </select>
        </div>
    </div>

    <!-- Partidos Feed -->
    <div class="partidos-feed" id="partidosFeed">
        <div class="loading" id="loadingPartidos">
            <div class="spinner"></div>
            Cargando partidos...
        </div>
    </div>
</section>

<!-- ═══ SECTION: DASHBOARDS ═══ -->
<section class="section hidden" id="sectionDashboards" role="tabpanel" aria-labelledby="navTab-dashboards" tabindex="-1">
    <!-- Resumen General -->
    <div class="dashboard-grid">
        <div class="dash-card dash-total">
            <div class="dash-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg></div>
            <div class="dash-card-value" id="dashTotal">0</div>
            <div class="dash-card-label">Puntos Totales</div>
        </div>
        <div class="dash-card dash-predicciones">
            <div class="dash-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></div>
            <div class="dash-card-value" id="dashPredicciones">0</div>
            <div class="dash-card-label">Predicciones</div>
        </div>
        <div class="dash-card dash-exactos">
            <div class="dash-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg></div>
            <div class="dash-card-value" id="dashExactos">0</div>
            <div class="dash-card-label">Exactos</div>
        </div>
        <div class="dash-card dash-aciertos">
            <div class="dash-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <div class="dash-card-value" id="dashAciertos">0</div>
            <div class="dash-card-label">Aciertos</div>
        </div>
    </div>

    <?php if ($esMiembro || $esAdmin): ?>
    <!-- Métricas Financieras (Solo Miembros) -->
    <div class="panel" id="panelFinanciero">
        <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--green-400)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg> Mis Apuestas</h2>
        <div class="dashboard-grid">
            <div class="dash-card card-won">
                <div class="dash-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg></div>
                <div class="dash-card-value" id="dashGanado">$0</div>
                <div class="dash-card-label">Total Ganado</div>
            </div>
            <div class="dash-card card-lost">
                <div class="dash-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"></polyline><polyline points="16 17 22 17 22 11"></polyline></svg></div>
                <div class="dash-card-value" id="dashPerdido">$0</div>
                <div class="dash-card-label">Total Perdido</div>
            </div>
            <div class="dash-card card-net">
                <div class="dash-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg></div>
                <div class="dash-card-value" id="dashNeto">$0</div>
                <div class="dash-card-label">Ganancia Neta</div>
            </div>
            <div class="dash-card card-pending">
                <div class="dash-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></div>
                <div class="dash-card-value" id="dashPendientes">0</div>
                <div class="dash-card-label">Apuestas Pendientes</div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 16px; margin-bottom: 24px;">
            <button class="btn btn-primary" id="btnApostarCampeon" onclick="openChampionModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg> Apostar al Campeón de la Liga
            </button>
        </div>

        <div id="misApuestasCampeonContainer" style="display: none;">
            <h3 style="margin-bottom: 12px; color: var(--text-secondary); font-size: 14px;">Tus predicciones al Campeón:</h3>
            <div id="listaApuestasCampeon" style="display: flex; flex-direction: column; gap: 8px;">
                <!-- Dynamically populated -->
            </div>
        </div>
    </div>

    <!-- Historial de Apuestas -->
    <div class="panel">
        <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg> Historial de Apuestas</h2>
        <div class="table-responsive">
            <div id="historialApuestas">
                <p class="empty-text">Cargando historial...</p>
            </div>
        </div>
    </div>

    <!-- Gráficos para Miembros -->
    <div class="panel" id="panelGraficosMiembro">
        <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--blue-400)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg> Comportamiento Global de Apuestas</h2>
        <p class="admin-desc">Estadísticas generales de toda la plataforma.</p>
        
        <div class="dashboard-grid charts-grid">
            <div class="chart-container" style="background: var(--surface-light); padding: 15px; border-radius: 8px;">
                <h3 style="text-align: center; margin-bottom: 10px;">Apuestas a Campeón</h3>
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="chartMemberCampeon"></canvas>
                </div>
            </div>
            
            <div class="chart-container" style="background: var(--surface-light); padding: 15px; border-radius: 8px;">
                <h3 style="text-align: center; margin-bottom: 10px;">Apuestas por Partido (Activos)</h3>
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="chartMemberPartidos"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Puntos por Día (Gráfico de barras CSS) -->
    <div class="panel">
        <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--purple-400)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> Puntos por Día</h2>
        <div class="chart-container" id="chartPorDia">
            <p class="empty-text">Sin datos aún</p>
        </div>
    </div>

    <!-- Desglose por Partido -->
    <div class="panel">
        <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg> Desglose por Partido</h2>
        <div class="desglose-container" id="desglosePorPartido">
            <p class="empty-text">Sin partidos evaluados aún</p>
        </div>
    </div>
</section>

<!-- ═══ SECTION: RANKING ═══ -->
<section class="section hidden" id="sectionRanking" role="tabpanel" aria-labelledby="navTab-ranking" tabindex="-1">
    <div class="panel">
        <div class="panel-header-actions">
            <h2 class="panel-title">Tabla de Posiciones</h2>
            <div class="ranking-actions">
                <select class="filter-select table-selector" id="rankingTablaSelect">
                    <option value="">Cargando tablas...</option>
                </select>
                <button class="btn btn-secondary" id="exportRankingBtn" style="display: none;">
                    Exportar Imagen
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <div class="ranking-container" id="rankingContainer">
                <div class="loading" id="loadingRanking">
                    <div class="spinner"></div>
                    Cargando ranking...
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ SECTION: TESORERO ═══ -->
<?php if ($esTesorero || $esAdmin): ?>
<section class="section hidden" id="sectionTesorero" role="tabpanel" aria-labelledby="navTab-tesorero" tabindex="-1">
    <div style="display: flex; justify-content: flex-end; margin-bottom: 16px;">
        <select id="competicionFilterTes" class="filter-select" onchange="loadTesoreroDashboard()">
            <option value="">Cargando competiciones...</option>
        </select>
    </div>
    <!-- KPIs -->
    <div class="dashboard-grid">
        <div class="dash-card">
            <div class="dash-card-icon" style="color:var(--green-400)"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
            <div class="dash-card-value" id="tesRecaudacion" style="color:var(--green-400)">$0</div>
            <div class="dash-card-label">Recaudación Membresías</div>
        </div>
        <div class="dash-card">
            <div class="dash-card-icon" style="color:var(--blue-400)"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></div>
            <div class="dash-card-value" id="tesTotalApuestas" style="color:var(--blue-400)">0</div>
            <div class="dash-card-label">Total Apuestas</div>
        </div>
        <div class="dash-card">
            <div class="dash-card-icon" style="color:var(--gold-400)"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg></div>
            <div class="dash-card-value" id="tesMontoApuestas" style="color:var(--gold-400)">$0</div>
            <div class="dash-card-label">Monto Apostado Total</div>
        </div>
        <div class="dash-card">
            <div class="dash-card-icon" style="color:var(--red-400)"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg></div>
            <div class="dash-card-value" id="tesGananciasPagadas" style="color:var(--red-400)">$0</div>
            <div class="dash-card-label">Ganancias Pagadas</div>
        </div>
    </div>

    <!-- Gráficos Chart.js -->
    <div class="dashboard-grid charts-grid">
        <div class="panel">
            <h2 class="panel-title">Membresías</h2>
            <div style="max-height: 300px; display:flex; justify-content:center;">
                <canvas id="chartMembresias"></canvas>
            </div>
        </div>
        <div class="panel">
            <h2 class="panel-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg> Apuestas Campeón por Equipo</h2>
            <div style="max-height: 300px; display:flex; justify-content:center;">
                <canvas id="chartCampeon"></canvas>
            </div>
        </div>
    </div>

    <div class="panel">
        <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--blue-400)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg> Recaudación por Día</h2>
        <div style="max-height: 350px;">
            <canvas id="chartRecaudacionDia"></canvas>
        </div>
    </div>

    <!-- Exportar -->
    <div style="text-align: center; margin: 16px 0;">
        <button class="btn btn-secondary" id="exportTesoreroPNG" onclick="exportTesorero('png')">
            Exportar como Imagen
        </button>
        <button class="btn btn-secondary" id="exportTesoreroCSV" onclick="exportTesorero('csv')">
            Exportar CSV
        </button>
    </div>

    <!-- ═══ Bandeja de Aprobación de Apuestas ═══ -->
    <div class="panel" id="panelBandejaAprobacion">
        <h2 class="panel-title">Bandeja de Aprobación de Apuestas</h2>
        <p style="color: var(--text-muted); font-size: 12px; margin-bottom: 12px;">
            Las apuestas pendientes de aprobación aparecen aquí. Solo las apuestas aprobadas se suman a los totales de recaudación.
        </p>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Persona</th>
                        <th>Equipo</th>
                        <th>Desglose de Apuestas</th>
                        <th>Total Apostado</th>
                        <th>Monto por Aprobar</th>
                        <th>Total Pago con Pozo</th>
                        <th style="text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody id="bandejaAprobacionList">
                    <tr><td colspan="7" class="empty-text">Cargando bandeja...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══ Apuestas de Todas las Personas ═══ -->
    <div class="panel" id="panelAllBets">
        <h2 class="panel-title">Apuestas del Mundial — Todas las Personas</h2>
        <p style="color: var(--text-muted); font-size: 12px; margin-bottom: 12px;">
            Vista de todas las apuestas al campeón y por partidos realizadas por los participantes. Solo visible para Tesorería y Admin.
        </p>
        
        <!-- KPIs Globales -->
        <div class="dashboard-grid" style="margin-bottom: 16px;">
            <div class="dash-card">
                <div class="dash-card-icon" style="color:var(--gold-400)"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg></div>
                <div class="dash-card-value" id="allBetsCampeonMonto" style="color:var(--gold-400)">$0</div>
                <div class="dash-card-label">Monto Total Campeón</div>
            </div>
            <div class="dash-card">
                <div class="dash-card-icon" style="color:var(--blue-400)"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
                <div class="dash-card-value" id="allBetsPartidosMonto" style="color:var(--blue-400)">$0</div>
                <div class="dash-card-label">Monto Total Partidos</div>
            </div>
            <div class="dash-card">
                <div class="dash-card-icon" style="color:var(--green-400)"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg></div>
                <div class="dash-card-value" id="allBetsTotalApuestas" style="color:var(--green-400)">0</div>
                <div class="dash-card-label">Total de Apuestas</div>
            </div>
            <div class="dash-card">
                <div class="dash-card-icon" style="color:var(--purple-400)"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14"></path><path d="M5 2h14"></path><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path></svg></div>
                <div class="dash-card-value" id="allBetsPendientes" style="color:var(--purple-400)">0</div>
                <div class="dash-card-label">Pendientes Partidos</div>
            </div>
        </div>

        <!-- Tabla Apuestas al Campeón de Todos -->
        <h3 style="margin-bottom: 10px; color: var(--text-secondary); font-size: 14px;"> Apuestas al Campeón de la Liga</h3>
        
        <!-- Filtros Apuestas Campeón -->
        <div class="filters-container" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
            <div style="position: relative; flex: 1; min-width: 200px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="filterCampeonUser" class="admin-input" placeholder="Buscar por persona..." style="width: 100%; padding-left: 38px !important; box-sizing: border-box;">
            </div>
            <select id="filterCampeonStatus" class="admin-input" style="flex: 1; min-width: 150px;">
                <option value="todos">Todos los equipos</option>
                <option value="vigentes">Equipos Vigentes</option>
                <option value="eliminados">Equipos Eliminados</option>
            </select>
            <select id="filterCampeonSort" class="admin-input" style="flex: 1; min-width: 180px;">
                <option value="apostado_desc">Mayor Apostado</option>
                <option value="apostado_asc">Menor Apostado</option>
                <option value="pago_desc">Mayor Pago Potencial</option>
                <option value="pago_asc">Menor Pago Potencial</option>
            </select>
        </div>

        <div class="table-responsive" style="margin-bottom: 24px;">
            <div id="allBetsCampeonTable">
                <p class="empty-text">Cargando apuestas...</p>
            </div>
        </div>

        <!-- Tabla Apuestas por Partido de Todos -->
        <h3 style="margin-bottom: 10px; color: var(--text-secondary); font-size: 14px;"> Apuestas por Partido — Independientes</h3>
        <div class="table-responsive">
            <div id="allBetsPartidosTable">
                <p class="empty-text">Cargando apuestas...</p>
            </div>
        </div>
    </div>

    <!-- Membresías Recientes -->
    <div class="panel">
        <h2 class="panel-title">Membresías Recientes</h2>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Monto</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="tesMembresiasLista">
                    <tr><td colspan="5" class="empty-text">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══ SECTION: ADMIN ═══ -->
<?php if ($esAdmin): ?>
<section class="section hidden" id="sectionAdmin" role="tabpanel" aria-labelledby="navTab-admin" tabindex="-1">
    <!-- Admin Sub-tabs -->
    <div class="admin-subtabs" id="adminSubtabs">
        <button class="admin-subtab active" data-subtab="usuarios">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            Usuarios
        </button>
        <button class="admin-subtab" data-subtab="predicciones">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            Predicciones
        </button>
        <button class="admin-subtab" data-subtab="tablas">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
            Tablas
        </button>
        <button class="admin-subtab" data-subtab="membresias">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>
            Membresías
        </button>
        <button class="admin-subtab" data-subtab="cuotas">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            Cuotas
        </button>
        <button class="admin-subtab" data-subtab="cuotasCampeon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            Cuotas Campeón
        </button>
        <button class="admin-subtab" data-subtab="penalizaciones">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            Penalizaciones
        </button>
    </div>

    <!-- Sub-tab: Usuarios -->
    <div class="admin-subtab-content active" id="subtabUsuarios" data-subtab-content="usuarios">
        <div class="panel admin-panel">
            <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; vertical-align:middle;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Gestionar Usuarios</h2>
            <p class="admin-desc">
                Agrega nuevos usuarios al sistema o elimina usuarios existentes. Solo el administrador puede gestionar las cuentas.
            </p>
            
            <!-- Formulario para crear usuario -->
            <div class="create-table-form" style="margin-bottom: 20px;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="newUserName">Nombre Completo</label>
                        <input type="text" id="newUserName" class="admin-input" placeholder="Ej: Juan Pérez" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="newUserUsername">Usuario</label>
                        <input type="text" id="newUserUsername" class="admin-input" placeholder="Ej: juanperez" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="newUserPassword">Contraseña</label>
                        <input type="text" id="newUserPassword" class="admin-input" placeholder="Contraseña inicial" maxlength="100">
                    </div>
                    <button class="btn btn-primary" id="createUserBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Crear Usuario
                    </button>
                </div>
            </div>

            <!-- Buscador y Filtros de usuarios -->
            <div class="admin-search-wrapper" style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
                <input type="text" id="adminSearchUsers" class="admin-search-input" placeholder="Buscar usuario..." oninput="filterAdminUsersList()" style="flex: 1;">
                
                <select id="adminRoleFilter" class="admin-input" onchange="filterAdminUsersList()" style="width: auto;">
                    <option value="">Todos los Roles</option>
                    <option value="1">Admin</option>
                    <option value="2">Tesorero</option>
                    <option value="3">Miembro</option>
                    <option value="4">Usuario</option>
                </select>

                <button class="btn btn-outline" id="exportAdminUsersBtn" onclick="exportAdminUsersCSV()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:middle;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Exportar CSV
                </button>
            </div>

            <!-- Lista de usuarios -->
            <div class="table-responsive" style="overflow: visible;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Rol</th>
                            <th>Monto Pagado</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="adminUsersManageList">
                        <tr>
                            <td colspan="4" class="empty-text">Cargando usuarios...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sub-tab: Tablas -->
    <div class="admin-subtab-content" id="subtabTablas" data-subtab-content="tablas">
        <!-- Crear Tabla -->
        <div class="panel admin-panel">
            <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg> Crear Tabla de Puntuación</h2>
            <p class="admin-desc">
                Crea tablas (grupos) de puntuación. Cada tabla tiene su propio ranking y los usuarios solo ven las tablas a las que pertenecen.
            </p>
            <div class="create-table-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="newTableName">Nombre de la tabla</label>
                        <input type="text" id="newTableName" class="admin-input" placeholder="Ej: Oficina, Amigos, Familia..." maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="newTableDesc">Descripción (opcional)</label>
                        <input type="text" id="newTableDesc" class="admin-input" placeholder="Descripción breve..." maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="newTableFaseInicio">Puntos desde fase</label>
                        <select id="newTableFaseInicio" class="admin-input filter-select">
                            <option value="Grupos">Grupos (todas las fases)</option>
                            <option value="Dieciseisavos">Dieciseisavos en adelante</option>
                            <option value="Octavos">Octavos en adelante</option>
                            <option value="Cuartos">Cuartos en adelante</option>
                            <option value="Semifinales">Semifinales en adelante</option>
                            <option value="Tercer Lugar">Tercer Lugar en adelante</option>
                            <option value="Final">Solo Final</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" id="createTableBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Crear Tabla
                    </button>
                </div>
            </div>
        </div>

        <!-- Gestión de Tablas y Miembros -->
        <div class="panel admin-panel">
            <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; vertical-align:middle;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg> Gestionar Tablas y Miembros</h2>
            <p class="admin-desc">
                Selecciona una tabla y activa/desactiva usuarios para darles acceso a participar, ver el ranking y exportar.
            </p>
            <div class="admin-table-selector" style="display: flex; gap: 12px; align-items: center; margin-bottom: 16px;">
                <label for="adminTablaSelect" style="white-space: nowrap;">Tabla:</label>
                <select class="filter-select" id="adminTablaSelect" style="flex: 1;">
                    <option value="">Selecciona una tabla</option>
                </select>
            </div>
            
            <div class="admin-search-wrapper" style="margin-bottom: 16px;">
                <input type="text" id="adminSearchTableUsers" class="admin-search-input" placeholder="Buscar miembro en esta tabla..." oninput="filterAdminTableUsers(this.value)">
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th style="text-align: center;">Aparece en Ranking</th>
                            <th style="text-align: center;">Puede Ver</th>
                            <th style="text-align: center;">Puede Exportar</th>
                        </tr>
                    </thead>
                    <tbody id="adminUsersList">
                        <tr>
                            <td colspan="5" class="empty-text">Selecciona una tabla arriba</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sub-tab: Membresías -->
    <div class="admin-subtab-content" id="subtabMembresias" data-subtab-content="membresias">
        <div class="panel admin-panel">
            <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; vertical-align:middle;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg> Convertir a Miembro</h2>
            <p class="admin-desc">
                Busca un usuario regular e ingrésale el monto pagado para convertirlo en Miembro. Los miembros pueden acceder al sistema de apuestas con dinero real.
            </p>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <!-- Buscador de conversión -->
                <div class="admin-search-wrapper" style="flex: 1; margin-right: 16px;">
                    <input type="text" id="searchConvertUser" class="admin-search-input" placeholder="Buscar usuario para convertir..." oninput="filterConvertUsers(this.value)">
                </div>
                <div>
                    <select id="competicionFilterMem" class="filter-select" onchange="loadAdminPermissions()">
                        <option value="">Cargando competiciones...</option>
                    </select>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Rol Actual</th>
                            <th>Monto USD</th>
                            <th style="text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="convertUsersList">
                        <tr><td colspan="5" class="empty-text">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sub-tab: Predicciones -->
    <div class="admin-subtab-content" id="subtabPredicciones" data-subtab-content="predicciones">
        <div class="panel admin-panel">
            <h2 class="panel-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; vertical-align:middle;"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg> Gestión de Predicciones</h2>
            <p class="admin-desc">
                Consulta, ingresa o edita las predicciones (marcadores) de los usuarios para cualquier partido, sin importar si ya comenzó.
            </p>
            
            <!-- Acciones y Filtros -->
            <div class="admin-search-wrapper" style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
                <select id="competicionFilterPred" class="filter-select" onchange="loadAdminPredicciones()" style="width: auto;">
                    <option value="">Cargando competiciones...</option>
                </select>
                <input type="text" id="adminSearchPrediccionUser" class="admin-search-input" placeholder="Buscar usuario..." oninput="filterAdminPredicciones()" style="flex:1;">
                <input type="text" id="adminSearchPrediccionMatch" class="admin-search-input" placeholder="Buscar partido..." oninput="filterAdminPredicciones()" style="flex:1;">
                <button class="btn btn-secondary" onclick="loadAdminPredicciones()" title="Actualizar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg>
                </button>
                <button class="btn btn-primary" onclick="openAdminPrediccionModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Nueva Predicción
                </button>
            </div>

            <!-- Tabla de Predicciones -->
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Fecha Pred.</th>
                            <th>Persona</th>
                            <th>Partido</th>
                            <th>Marcador Real</th>
                            <th style="text-align: center;">Predicción</th>
                            <th style="text-align: center;">Puntos</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="adminPrediccionesList">
                        <tr><td colspan="7" class="empty-text">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Sub-tab: Cuotas -->
    <div class="admin-subtab-content" id="subtabCuotas" data-subtab-content="cuotas">
        <div class="panel admin-panel">
            <h2 class="panel-title"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg> Sincronización de Cuotas (Odds)</h2>
            <p class="admin-desc">
                Sincroniza las cuotas del mercado desde The Odds API. Las cuotas se inyectan en los partidos que aún no han comenzado para que los miembros puedan apostar.
            </p>
            <div style="text-align: center; padding: 20px;">
                <button class="btn btn-primary" id="syncOddsBtn" onclick="syncOdds()" style="font-size: 16px; padding: 14px 32px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg> Sincronizar Cuotas Ahora
                </button>
                <div id="syncOddsResult" style="margin-top: 16px;"></div>
            </div>
        </div>
    </div>

    <!-- Sub-tab: Cuotas Campeón (Fijas + Exclusiones) -->
    <div class="admin-subtab-content" id="subtabCuotasCampeon" data-subtab-content="cuotasCampeon">
        <div class="panel admin-panel">
            <h2 class="panel-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg> Cuotas Fijas — Campeón de la Liga</h2>
            <p class="admin-desc">
                Establece cuotas fijas para equipos específicos. Las cuotas fijas prevalecen sobre el cálculo dinámico.
                También puedes excluir equipos para que no aparezcan como opción de apuesta al campeón.
            </p>

            <!-- Leyenda de estados -->
            <div style="display:flex; gap:16px; margin-bottom:16px; flex-wrap:wrap;">
                <div style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted);">
                    <span class="ccf-badge ccf-badge-dynamic">Dinámica</span> Usa cálculo automático
                </div>
                <div style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted);">
                    <span class="ccf-badge ccf-badge-fixed">Fija</span> Cuota establecida manualmente
                </div>
                <div style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted);">
                    <span class="ccf-badge ccf-badge-excluded">Excluido</span> No disponible para apuestas
                </div>
            </div>

            <!-- Buscador -->
            <div class="admin-search-wrapper" style="margin-bottom: 16px;">
                <input type="text" id="searchCuotasCampeon" class="admin-search-input" placeholder="Buscar equipo..." oninput="filterCuotasCampeonList(this.value)">
            </div>

            <!-- Contenedor de equipos -->
            <div id="cuotasCampeonList" class="ccf-grid">
                <p class="empty-text">Haz clic en la pestaña para cargar datos.</p>
            </div>
        </div>
    </div>

    <!-- Sub-tab: Penalizaciones -->
    <div class="admin-subtab-content" id="subtabPenalizaciones" data-subtab-content="penalizaciones">
        <!-- KPIs de Penalizaciones -->
        <div class="dashboard-grid" style="margin-bottom: 24px; gap: 16px;">
            <div class="dash-card pen-kpi-card" style="display:flex; align-items:center; gap:16px; padding:16px; border:1px solid var(--border-glass);">
                <div class="dash-card-icon" style="background:var(--bg-glass); border:1px solid var(--border-glass); border-radius:8px; padding:12px; display:flex;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-secondary);"><path d="M14 13.5V16.5L15.5 18L14 19.5L12 17.5L10 19.5L8.5 18L10 16.5V13.5L8.5 12L10 10.5L12 12.5L14 10.5L15.5 12L14 13.5Z"/><path d="M14 6L18 10L21 7L17 3L14 6Z"/><path d="M3 21L7 17"/></svg>
                </div>
                <div>
                    <div class="dash-card-label" style="font-size:12px; color:var(--text-muted);">Total Sanciones</div>
                    <div class="dash-card-value" id="penTotal" style="font-size:24px; font-weight:700; line-height:1;">0</div>
                </div>
            </div>
            <div class="dash-card pen-kpi-card" style="display:flex; align-items:center; gap:16px; padding:16px; border:1px solid var(--border-glass);">
                <div class="dash-card-icon" style="background:var(--bg-glass); border:1px solid var(--border-glass); border-radius:8px; padding:12px; display:flex;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-secondary);"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                </div>
                <div>
                    <div class="dash-card-label" style="font-size:12px; color:var(--text-muted);">Vigentes</div>
                    <div class="dash-card-value" id="penActivas" style="font-size:24px; font-weight:700; line-height:1;">0</div>
                </div>
            </div>
            <div class="dash-card pen-kpi-card" style="display:flex; align-items:center; gap:16px; padding:16px; border:1px solid var(--border-glass);">
                <div class="dash-card-icon" style="background:var(--bg-glass); border:1px solid var(--border-glass); border-radius:8px; padding:12px; display:flex;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-secondary);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div>
                    <div class="dash-card-label" style="font-size:12px; color:var(--text-muted);">Anuladas</div>
                    <div class="dash-card-value" id="penAnuladas" style="font-size:24px; font-weight:700; line-height:1;">0</div>
                </div>
            </div>
            <div class="dash-card pen-kpi-card" style="display:flex; align-items:center; gap:16px; padding:16px; border:1px solid var(--border-glass);">
                <div class="dash-card-icon" style="background:var(--bg-glass); border:1px solid var(--border-glass); border-radius:8px; padding:12px; display:flex;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-secondary);"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"></polyline><polyline points="16 17 22 17 22 11"></polyline></svg>
                </div>
                <div>
                    <div class="dash-card-label" style="font-size:12px; color:var(--text-muted);">Pts. Descontados</div>
                    <div class="dash-card-value" id="penPuntosRestados" style="font-size:24px; font-weight:700; line-height:1;">0</div>
                </div>
            </div>
        </div>

        <!-- Panel: Crear Penalización -->
        <div class="panel admin-panel pen-create-panel" style="padding:0; background:transparent; border:none; box-shadow:none;">
            <div class="pen-create-header" style="margin-bottom:16px;">
                <h2 class="panel-title" style="margin-bottom:0; font-size:16px;">Aplicar Nueva Sanción</h2>
            </div>

            <!-- Paso 1: Seleccionar Usuarios -->
            <div class="pen-step" style="margin-bottom:24px;">
                <div class="pen-step-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="pen-step-number" style="background:var(--green-500); color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:bold;">1</span>
                        <span class="pen-step-title" style="font-weight:600; font-size:15px;">Seleccionar Usuarios</span>
                        <span id="penSelectedCount" class="pen-selected-badge"></span>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-secondary" onclick="toggleAllPenUsers(true)" style="padding:6px 12px; font-size:12px; border:1px solid var(--border-glass); background:transparent;">Todos</button>
                        <button class="btn btn-secondary" onclick="toggleAllPenUsers(false)" style="padding:6px 12px; font-size:12px; border:1px solid var(--border-glass); background:transparent;">Limpiar</button>
                    </div>
                </div>
                
                <div class="pen-step-body" style="background:var(--bg-card); border:1px solid var(--border-glass); border-radius:var(--radius-sm); padding:1px;">
                    <input type="text" class="admin-search-input" placeholder="Filtrar usuarios..." oninput="filterPenUsers(this.value)" style="width:100%; border:none; border-bottom:1px solid var(--border-glass); border-radius:var(--radius-sm) var(--radius-sm) 0 0; background:transparent; padding:12px 16px; font-size:14px;">
                    <div id="penUsersGrid" class="pen-users-grid" onclick="updatePenSelectedCount()" style="padding:16px; max-height:200px; overflow-y:auto;">
                        <p class="empty-text">Cargando usuarios...</p>
                    </div>
                </div>
            </div>

            <!-- Paso 2: Definir Sanción -->
            <div class="pen-step" style="margin-bottom:24px;">
                <div class="pen-step-header" style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                    <span class="pen-step-number" style="background:var(--green-500); color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:bold;">2</span>
                    <span class="pen-step-title" style="font-weight:600; font-size:15px;">Definir Sanción</span>
                </div>
                <div class="pen-step-body" style="background:var(--bg-card); border:1px solid var(--border-glass); border-radius:var(--radius-sm); padding:16px;">
                    <div style="display:flex; gap:16px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:200px;">
                            <label for="penPuntosInput" style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:8px;">Puntos</label>
                            <input type="number" id="penPuntosInput" class="admin-input" placeholder="" min="1" required style="width:100%; padding:10px 14px; background:transparent;">
                        </div>
                        <div style="flex:2; min-width:300px;">
                            <label for="penMotivoInput" style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:8px;">Motivo de la Sanción</label>
                            <input type="text" id="penMotivoInput" class="admin-input" placeholder="" maxlength="500" style="width:100%; padding:10px 14px; background:transparent;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón de Aplicar -->
            <div class="pen-apply-section">
                <button class="btn btn-primary" id="btnAplicarPenalizacion" onclick="createPenalizacion()" style="padding:10px 20px; font-weight:600;">
                    Aplicar Sanción
                </button>
            </div>
        </div>

        <!-- Panel: Historial de Penalizaciones -->
        <div class="panel admin-panel">
            <div class="pen-historial-header">
                <h2 class="panel-title" style="margin-bottom:0;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 3v5h5M16 13H8M16 17H8M10 9H8"/></svg> Historial de Sanciones</h2>
                <div class="pen-historial-controls">
                    <select id="filterPenEstado" class="admin-input pen-filter-select" onchange="filterPenalizacionesHistorial()">
                        <option value="">Todos</option>
                        <option value="Activa">Vigentes</option>
                        <option value="Anulada">Anuladas</option>
                    </select>
                    <button class="btn btn-secondary" onclick="loadPenalizaciones()" title="Actualizar" style="padding:8px 12px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg>
                    </button>
                </div>
            </div>
            <div style="margin-top:12px;">
                <input type="text" id="searchPenalizaciones" class="admin-search-input" placeholder="Buscar por usuario o motivo..." oninput="filterPenalizacionesHistorial()" style="padding:10px 14px;">
            </div>

            <div class="table-responsive" style="margin-top:16px;">
                <table class="admin-table pen-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th style="text-align:center;">Pts.</th>
                            <th>Tabla</th>
                            <th>Motivo</th>
                            <th style="text-align:center;">Estado</th>
                            <th>Fecha</th>
                            <th style="text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="penalizacionesHistorial">
                        <tr><td colspan="6" class="empty-text">Haz click en la pestaña para cargar datos.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══ MODAL: Apostar al Campeón ═══ -->
<div class="modal-overlay" id="modalCampeon" role="dialog" aria-modal="true" aria-labelledby="modalCampeonTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalCampeonTitle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg> Apostar al Campeón de la Liga</h2>
            <button class="modal-close" onclick="closeChampionModal()" aria-label="Cerrar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
        </div>
        <div class="modal-body">
            <div class="admin-search-wrapper" style="margin-bottom: 16px;">
                <input type="text" id="searchChampionTeam" class="admin-search-input" placeholder="Buscar equipo..." oninput="filterChampionTeams(this.value)">
            </div>
            <div id="championTeamsList" class="champion-teams-grid">
                <p class="empty-text">Cargando equipos...</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL: Crear/Editar Predicción (Admin) ═══ -->
<div class="modal-overlay" id="modalAdminPrediccion" role="dialog" aria-modal="true" aria-labelledby="modalAdminPrediccionTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalAdminPrediccionTitle">Nueva Predicción</h2>
            <button class="modal-close" onclick="closeAdminPrediccionModal()" aria-label="Cerrar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
        </div>
        <div class="modal-body">
            <form id="formAdminPrediccion" onsubmit="saveAdminPrediccion(event)">
                <div class="form-group">
                    <label style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 6px;">Usuario</label>
                    <div class="custom-select-wrapper" id="wrapperAdminUser">
                        <div class="custom-select-input-container">
                            <input type="text" id="modalSearchUser" class="admin-input" placeholder="Buscar o seleccionar usuario..." oninput="filterModalPrediccionUsers()" onfocus="openCustomSelect('wrapperAdminUser')" autocomplete="off" required>
                            <span class="custom-select-arrow">▼</span>
                        </div>
                        <div class="custom-select-dropdown" id="dropdownAdminUser"></div>
                        <input type="hidden" id="adminPredUser" required>
                    </div>
                </div>
                <div class="form-group">
                    <label style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 6px;">Partido</label>
                    <div class="custom-select-wrapper" id="wrapperAdminMatch">
                        <div class="custom-select-input-container">
                            <input type="text" id="modalSearchMatch" class="admin-input" placeholder="Buscar o seleccionar partido..." oninput="filterModalPrediccionMatches()" onfocus="openCustomSelect('wrapperAdminMatch')" autocomplete="off" required>
                            <span class="custom-select-arrow">▼</span>
                        </div>
                        <div class="custom-select-dropdown" id="dropdownAdminMatch"></div>
                        <input type="hidden" id="adminPredMatch" required>
                    </div>
                </div>
                <div style="display:flex; gap:10px; margin-bottom:15px;">
                    <div class="form-group" style="flex:1; text-align:center;">
                        <label style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 6px;">Goles Local</label>
                        <input type="number" id="adminPredGolesL" class="admin-input" min="0" required style="text-align:center; font-size: 20px;">
                    </div>
                    <div class="form-group" style="flex:1; text-align:center;">
                        <label style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 6px;">Goles Visitante</label>
                        <input type="number" id="adminPredGolesV" class="admin-input" min="0" required style="text-align:center; font-size: 20px;">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; justify-content: center; font-weight: 600; font-size: 14px;">
                    Guardar Predicción
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ═══ MODAL: Apostar en Partido ═══ -->
<div class="modal-overlay" id="modalBetMatch" role="dialog" aria-modal="true" aria-labelledby="modalBetMatchTitle">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <h2 id="modalBetMatchTitle">Apostar en Partido</h2>
            <button class="modal-close" onclick="closeBetMatchModal()" aria-label="Cerrar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
        </div>
        <div class="modal-body" id="betMatchBody">
            <!-- Populated dynamically -->
        </div>
    </div>
</div>

<!-- ═══ MODAL: Editar Penalización ═══ -->
<div class="modal-overlay" id="modalEditPenalizacion" role="dialog" aria-modal="true" aria-labelledby="modalEditPenalizacionTitle">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header">
            <h2 id="modalEditPenalizacionTitle">Modificar Penalización <span id="editPenId" style="color:var(--text-muted);font-size:14px;"></span></h2>
            <button class="modal-close" onclick="closeEditPenalizacionModal()" aria-label="Cerrar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
        </div>
        <div class="modal-body">
            <form onsubmit="saveEditPenalizacion(event)">
                <div class="form-group" style="margin-bottom:12px;">
                    <label>Usuario</label>
                    <div id="editPenUser" style="font-size:15px;font-weight:700;color:var(--text-primary);padding:8px 0;"></div>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label for="editPenPuntos">Puntos a Restar</label>
                    <input type="number" id="editPenPuntos" class="admin-input" min="1" required style="text-align:center;font-size:20px;font-weight:700;">
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label for="editPenMotivo">Motivo</label>
                    <textarea id="editPenMotivo" class="admin-input" maxlength="500" rows="3" required style="resize:vertical;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;justify-content:center;">
                    Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ═══ MOBILE BOTTOM NAVIGATION ═══ -->
<nav class="mobile-bottom-nav" id="mobileBottomNav" role="tablist" aria-label="Secciones">
    <button class="mobile-nav-item active" data-section="predicciones" id="mobileNavTab-predicciones" role="tab" aria-selected="true" aria-controls="sectionPredicciones">
        <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 12l4-4-4-4M12 12l-4 4-4-4M12 12l-5 2M12 12l5 2M12 12v5"></path></svg></span>
        Partidos
    </button>
    <button class="mobile-nav-item" data-section="dashboards" id="mobileNavTab-dashboards" role="tab" aria-selected="false" aria-controls="sectionDashboards">
        <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg></span>
        Puntos
    </button>
    <button class="mobile-nav-item" data-section="ranking" id="mobileNavTab-ranking" role="tab" aria-selected="false" aria-controls="sectionRanking">
        <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg></span>
        Ranking
    </button>
    <?php if ($esTesorero || $esAdmin): ?>
    <button class="mobile-nav-item" data-section="tesorero" id="mobileNavTab-tesorero" role="tab" aria-selected="false" aria-controls="sectionTesorero">
        <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg></span>
        Tesorería
    </button>
    <?php endif; ?>
    <?php if ($esAdmin): ?>
    <button class="mobile-nav-item" data-section="admin" id="mobileNavTab-admin" role="tab" aria-selected="false" aria-controls="sectionAdmin">
        <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg></span>
        Admin
    </button>
    <?php endif; ?>
</nav>

<!-- ═══ TOAST CONTAINER ═══ -->
<div class="toast-container" id="toastContainer" role="status" aria-live="polite" aria-atomic="false"></div>

<script>
    // Global user metadata passed from session
    const CURRENT_USER = {
        username: '<?php echo $nombreUsuario; ?>',
        nombreCompleto: '<?php echo $nombreCompleto; ?>',
        isAdmin: <?php echo $esAdmin ? 'true' : 'false'; ?>,
        role: <?php echo $idRol; ?>,
        isMiembro: <?php echo ($esMiembro || $esAdmin) ? 'true' : 'false'; ?>,
        isTesorero: <?php echo ($esTesorero || $esAdmin) ? 'true' : 'false'; ?>
    };
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="js/state.js?v=<?php echo assetVersion('js/state.js'); ?>"></script>
<script src="js/api.js?v=<?php echo assetVersion('js/api.js'); ?>"></script>
<script src="js/modules/theme.js?v=<?php echo assetVersion('js/modules/theme.js'); ?>"></script>
<script src="js/modules/navigation.js?v=<?php echo assetVersion('js/modules/navigation.js'); ?>"></script>
<script src="js/modules/auth.js?v=<?php echo assetVersion('js/modules/auth.js'); ?>"></script>
<script src="js/modules/partidos.js?v=<?php echo assetVersion('js/modules/partidos.js'); ?>"></script>
<script src="js/modules/predicciones.js?v=<?php echo assetVersion('js/modules/predicciones.js'); ?>"></script>
<script src="js/modules/apuestas.js?v=<?php echo assetVersion('js/modules/apuestas.js'); ?>"></script>
<script src="js/modules/dashboards.js?v=<?php echo assetVersion('js/modules/dashboards.js'); ?>"></script>
<script src="js/modules/ranking.js?v=<?php echo assetVersion('js/modules/ranking.js'); ?>"></script>
<script src="js/modules/admin.js?v=<?php echo assetVersion('js/modules/admin.js'); ?>"></script>
<script src="js/modules/adminPredicciones.js?v=<?php echo assetVersion('js/modules/adminPredicciones.js'); ?>"></script>
<script src="js/modules/adminPenalizaciones.js?v=<?php echo assetVersion('js/modules/adminPenalizaciones.js'); ?>"></script>
<script src="js/main.js?v=<?php echo assetVersion('js/main.js'); ?>"></script>
</body>
</html>
