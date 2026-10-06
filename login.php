<?php
// Cache-busting real: invalida solo cuando el archivo realmente cambia,
// a diferencia de time() (invalidaba en cada request sin necesidad).
function assetVersion(string $relativePath): string
{
    $fullPath = __DIR__ . '/' . $relativePath;
    return file_exists($fullPath) ? (string)filemtime($fullPath) : (string)time();
}
?>
<!--
THESIS: un login de quiniela debe sentirse como llegar a un estadio de
noche, no como un SaaS genérico — el panel animado ES un marcador vivo
bajo luces de cancha, no un blob decorativo.
OWN-WORLD: verde-cancha + azul-estadio + dorado-trofeo + violeta de
acento, blobs orgánicos a la deriva tras una textura de líneas de
cancha; Bebas Neue para el nombre/marcador, Manrope para la UI. Claro
= tarde de partido a pleno sol; oscuro = noche bajo reflectores.
STORY: el visitante entiende de inmediato "esto es una quiniela seria,
bien administrada" e inicia sesión con confianza, en el tema que prefiera.
FIRST VIEWPORT (desktop): split asimétrico — 56% panel de cancha
animado con el nombre a modo de marcador; 44% tarjeta de vidrio con el
formulario y el toggle de tema. Móvil: la cancha se colapsa a una
franja-hero corta sobre una tarjeta a ancho completo con esquinas
superiores redondeadas, superpuesta como en la referencia del usuario.
FORM: dirección fijada por el usuario (imagen de referencia + pedido
explícito) — concept-seed/serve-question omitidos por tratarse de una
dirección ya fijada por el brief, no de un mundo abierto a decidir.
FINISH: unreviewed and undocumented is unfinished; this build ends
with the finish review, the verdict, and DESIGN.md.
-->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Iniciar sesión en tu Quiniela — predicciones, apuestas y ranking">
    <title>Quiniela — Iniciar Sesión</title>
    <script>
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
    <link rel="stylesheet" href="css/login.css?v=<?php echo assetVersion('css/login.css'); ?>">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%23facc15'/%3E%3Cpath d='M6 9H4.5a2.5 2.5 0 0 1 0-5H6 M18 9h1.5a2.5 2.5 0 0 0 0-5H18 M4 22h16 M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22 M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22 M18 2H6v7a6 6 0 0 0 12 0V2Z' fill='none' stroke='%23071019' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="login-body">

<div class="login-shell">

    <!-- ═══ ESCENARIO: panel animado de cancha nocturna ═══ -->
    <section class="login-stage" aria-hidden="true">
        <div class="stage-blobs">
            <span class="stage-blob stage-blob-1"></span>
            <span class="stage-blob stage-blob-2"></span>
            <span class="stage-blob stage-blob-3"></span>
            <span class="stage-blob stage-blob-4"></span>
        </div>
        <div class="stage-turf"></div>
        <div class="stage-vignette"></div>

        <div class="stage-content">
            <div class="stage-mark">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg>
            </div>
            <h1 class="stage-title">Quiniela</h1>
            <p class="stage-tagline">Predicciones, apuestas y ranking — todo en un solo lugar.</p>

            <div class="stage-ticker">
                <span class="ticker-bar"></span>
                <span class="ticker-bar"></span>
                <span class="ticker-bar"></span>
                <span class="ticker-bar"></span>
                <span class="ticker-bar"></span>
                <span class="ticker-bar"></span>
            </div>

            <p class="stage-trust">Pozos verificados por tesorería · reparto real, no cuotas fijas</p>
        </div>
    </section>

    <!-- ═══ PANEL: formulario ═══ -->
    <section class="login-panel">
        <button class="theme-toggle login-panel-toggle" aria-pressed="false">
            <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path></svg>
        </button>

        <div class="login-panel-inner">
            <p class="login-greeting" id="loginGreeting">Hola</p>
            <h2 class="login-heading">Inicia sesión en tu cuenta</h2>

            <form id="loginForm" class="login-form" autocomplete="off">
                <div class="login-form-group">
                    <label for="loginUsuario">Usuario</label>
                    <div class="login-input-wrapper">
                        <span class="login-input-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </span>
                        <input type="text" id="loginUsuario" name="usuario" placeholder="tu.usuario" required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="login-form-group">
                    <label for="loginContrasena">Contraseña</label>
                    <div class="login-input-wrapper">
                        <span class="login-input-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        </span>
                        <input type="password" id="loginContrasena" name="contrasena" placeholder="••••••••" required autocomplete="current-password">
                    </div>
                </div>

                <div class="login-error" id="loginError" role="alert" aria-live="assertive"></div>

                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="login-btn-text">Iniciar Sesión</span>
                    <span class="login-btn-loader" id="loginLoader" aria-hidden="true"></span>
                </button>
            </form>

            <div class="login-access-note">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Acceso solo por invitación — tu administrador crea tu cuenta.
            </div>

            <p class="login-footer">¿Problemas para entrar? Contacta al administrador de tu grupo.</p>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Saludo según la hora local del visitante (no la del servidor: un
    // saludo es cortesía de interfaz, no una regla de negocio con horario).
    const greetingEl = document.getElementById('loginGreeting');
    if (greetingEl) {
        const h = new Date().getHours();
        greetingEl.textContent = h < 12 ? '¡Buenos días!' : h < 19 ? '¡Buenas tardes!' : '¡Buenas noches!';
    }

    // Toggle de tema (misma lógica que js/modules/theme.js; se duplica aquí
    // en línea porque el login no carga el bundle de módulos de la app).
    document.querySelectorAll('.theme-toggle').forEach(btn => {
        const current = document.documentElement.getAttribute('data-theme');
        const isLight = current === 'light' || (!current && window.matchMedia('(prefers-color-scheme: light)').matches);
        btn.setAttribute('aria-pressed', isLight ? 'true' : 'false');
        btn.setAttribute('aria-label', isLight ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro');
        btn.addEventListener('click', () => {
            const nowLight = document.documentElement.getAttribute('data-theme') === 'light'
                || (!document.documentElement.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: light)').matches);
            const next = nowLight ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('quiniela-theme', next); } catch (e) {}
            btn.setAttribute('aria-pressed', next === 'light' ? 'true' : 'false');
            btn.setAttribute('aria-label', next === 'light' ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro');
        });
    });

    const loginForm = document.getElementById('loginForm');
    const loginError = document.getElementById('loginError');

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        loginError.textContent = '';
        const btn = document.getElementById('loginBtn');
        btn.disabled = true;
        btn.classList.add('loading');

        try {
            const res = await fetch('api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    usuario: document.getElementById('loginUsuario').value.trim(),
                    contrasena: document.getElementById('loginContrasena').value
                })
            });

            const data = await res.json();

            if (data.success) {
                btn.classList.add('success');
                btn.querySelector('.login-btn-text').textContent = '¡Bienvenido!';
                setTimeout(() => { window.location.href = 'index.php'; }, 600);
            } else {
                loginError.textContent = data.error || 'Error al iniciar sesión';
                btn.disabled = false;
                btn.classList.remove('loading');
            }
        } catch (err) {
            loginError.textContent = 'Error de conexión con el servidor';
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    });
});
</script>

</body>
</html>
