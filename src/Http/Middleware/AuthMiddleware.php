<?php

// ═══════════════════════════════════════════════════════════════
// AuthMiddleware — Validación de Autenticación y Roles
// ═══════════════════════════════════════════════════════════════
// Middleware centralizado para verificar sesión y permisos.
// Cada método termina la ejecución con un JsonResponse si falla.
// ═══════════════════════════════════════════════════════════════

class AuthMiddleware
{
    /**
     * Configura los atributos de la cookie de sesión. Debe llamarse
     * ANTES de session_start() (PHP no permite cambiarlos después).
     * `secure` se activa solo si la petición ya llega por HTTPS: forzarlo
     * siempre rompería el login por completo en el despliegue actual,
     * que corre sobre HTTP plano en Apache/AppServ.
     *
     * @return void
     */
    public static function configureSessionCookie(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Requiere que el usuario esté autenticado (sesión activa).
     * Si no lo está, responde 401 y termina.
     *
     * @return void
     */
    public static function requireAuth(): void
    {
        if (!isset($_SESSION['IdUsuario'])) {
            JsonResponse::error('No autorizado', 401);
        }
    }

    /**
     * Requiere que el usuario sea administrador.
     * Si no lo es, responde 403 y termina.
     *
     * Acepta dos criterios (unificación en curso, ver PLAN_MIGRACION.md
     * §2.6): IdRol = 1 en BD, o pertenecer a Config::ADMINS por nombre
     * de usuario (criterio legacy). Es deliberadamente aditivo — nunca
     * saca a nadie que ya entraba por el criterio anterior — porque no
     * hay forma de confirmar aquí que iasensio/cvande tengan IdRol = 1
     * en la BD de producción antes de aplicar el cambio.
     *
     * @return void
     */
    public static function requireAdmin(): void
    {
        self::requireAuth();

        $esAdminPorRol = self::getUserRole() === Config::ROL_ADMIN;
        $esAdminPorLista = isset($_SESSION['NombreUsuario']) && Config::isAdmin($_SESSION['NombreUsuario']);

        if (!$esAdminPorRol && !$esAdminPorLista) {
            JsonResponse::forbidden('No autorizado. Solo administradores pueden realizar esta acción.');
        }

        if ($esAdminPorLista && !$esAdminPorRol) {
            error_log('[AuthMiddleware] Admin autorizado solo por Config::ADMINS (username), no por IdRol: ' . ($_SESSION['NombreUsuario'] ?? '?'));
        }
    }

    /**
     * Requiere que el usuario tenga rol de Miembro (o Admin).
     * Si no lo tiene, responde 403 y termina.
     *
     * Incluye el mismo fallback aditivo por Config::ADMINS que ya
     * tenían requireAdmin() y requireTesorero() — sin esto, un admin
     * reconocido solo por username (no por IdRol=1) quedaba afuera.
     *
     * @return void
     */
    public static function requireMember(): void
    {
        self::requireAuth();

        $rol = self::getUserRole();
        $esAdmin = isset($_SESSION['NombreUsuario']) && Config::isAdmin($_SESSION['NombreUsuario']);

        if ($rol !== Config::ROL_MIEMBRO && $rol !== Config::ROL_ADMIN && !$esAdmin) {
            JsonResponse::forbidden('Solo los miembros pueden realizar esta acción. Contacta al administrador para obtener membresía.');
        }
    }

    /**
     * Requiere que el usuario tenga rol de Tesorero (o Admin).
     * Si no lo tiene, responde 403 y termina.
     *
     * @return void
     */
    public static function requireTesorero(): void
    {
        self::requireAuth();

        $rol = self::getUserRole();
        $esAdmin = isset($_SESSION['NombreUsuario']) && Config::isAdmin($_SESSION['NombreUsuario']);

        if (!in_array($rol, [Config::ROL_ADMIN, Config::ROL_TESORERO]) && !$esAdmin) {
            JsonResponse::forbidden('Acceso restringido a tesoreros y administradores.');
        }
    }

    /**
     * Requiere acceso monetario (Admin, Tesorero o Miembro).
     *
     * @return void
     */
    public static function requireMonetaryAccess(): void
    {
        self::requireAuth();

        $rol = self::getUserRole();
        if (!in_array($rol, [Config::ROL_ADMIN, Config::ROL_TESORERO, Config::ROL_MIEMBRO])) {
            JsonResponse::forbidden('Acceso restringido.');
        }
    }

    /**
     * Obtiene el ID del usuario de la sesión.
     *
     * @return int
     */
    public static function getUserId(): int
    {
        return (int)($_SESSION['IdUsuario'] ?? 0);
    }

    /**
     * Obtiene el nombre de usuario de la sesión.
     *
     * @return string
     */
    public static function getUsername(): string
    {
        return (string)($_SESSION['NombreUsuario'] ?? '');
    }

    /**
     * Verifica si el usuario actual es admin (mismo criterio aditivo que
     * requireAdmin(), ver ese docblock).
     *
     * @return bool
     */
    public static function isAdmin(): bool
    {
        $esAdminPorRol = self::getUserRole() === Config::ROL_ADMIN;
        $esAdminPorLista = isset($_SESSION['NombreUsuario']) && Config::isAdmin($_SESSION['NombreUsuario']);
        return $esAdminPorRol || $esAdminPorLista;
    }

    /**
     * Obtiene el rol del usuario actual desde la BD.
     *
     * @return int
     */
    private static function getUserRole(): int
    {
        if (isset($_SESSION['IdRol'])) {
            return (int)$_SESSION['IdRol'];
        }

        $userId = self::getUserId();
        if ($userId <= 0) {
            return Config::ROL_USUARIO;
        }

        try {
            $row = Database::fetchOne(
                "SELECT ISNULL(IdRol, ?) AS IdRol FROM Usuarios WHERE Id = ?",
                [Config::ROL_USUARIO, $userId]
            );
            $rol = $row ? (int)$row['IdRol'] : Config::ROL_USUARIO;
            $_SESSION['IdRol'] = $rol;
            return $rol;
        } catch (\Exception $e) {
            return Config::ROL_USUARIO;
        }
    }

    /**
     * Maneja CORS preflight (OPTIONS).
     * Termina la ejecución si es una petición OPTIONS.
     *
     * Con `*` un navegador nunca envía cookies de sesión en una petición
     * cross-origin, así que el comodín no habilitaba nada legítimo — sólo
     * dejaba la puerta abierta a que cualquier sitio leyera la respuesta
     * si alguna vez se relajaran las credenciales. Como todo el frontend
     * es same-origin (ver Config::ALLOWED_ORIGINS), no emitir la cabecera
     * en absoluto para un origen no reconocido no rompe nada real.
     *
     * @return void
     */
    public static function handleCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && in_array($origin, Config::ALLOWED_ORIGINS, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
