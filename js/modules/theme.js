// TEMA CLARO / OSCURO
// ═══════════════════════════════════════════════════════════════
// La aplicación del tema guardado ocurre antes de este archivo, en un
// script inline en <head> (evita el parpadeo de tema al cargar). Este
// módulo sólo conecta el botón de alternancia con esa misma lógica.

function applyTheme(theme) {
    if (theme === 'light' || theme === 'dark') {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('quiniela-theme', theme);
    } else {
        document.documentElement.removeAttribute('data-theme');
        localStorage.removeItem('quiniela-theme');
    }
    document.querySelectorAll('.theme-toggle').forEach(btn => {
        const isLight = document.documentElement.getAttribute('data-theme') === 'light'
            || (!document.documentElement.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: light)').matches);
        btn.setAttribute('aria-pressed', isLight ? 'true' : 'false');
        btn.setAttribute('aria-label', isLight ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro');
    });
}

function setupThemeToggle() {
    const current = document.documentElement.getAttribute('data-theme');
    document.querySelectorAll('.theme-toggle').forEach(btn => {
        const isLight = current === 'light' || (!current && window.matchMedia('(prefers-color-scheme: light)').matches);
        btn.setAttribute('aria-pressed', isLight ? 'true' : 'false');
        btn.setAttribute('aria-label', isLight ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro');

        btn.addEventListener('click', () => {
            const nowLight = document.documentElement.getAttribute('data-theme') === 'light'
                || (!document.documentElement.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: light)').matches);
            applyTheme(nowLight ? 'dark' : 'light');
        });
    });
}

document.addEventListener('DOMContentLoaded', setupThemeToggle);
