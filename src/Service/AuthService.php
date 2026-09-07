<?php

// ═══════════════════════════════════════════════════════════════
// AuthService — Lógica de Autenticación y Registro
// ═══════════════════════════════════════════════════════════════

class AuthService
{
    private $usuarioRepo;

    public function __construct()
    {
        $this->usuarioRepo = new UsuarioRepository();
    }

    /**
     * Autentica a un usuario y establece las variables de sesión.
     * Retorna un arreglo con el resultado de la operación.
     * NOTA: Contraseñas en texto plano por requerimiento legacy.
     */
    public function authenticate(string $username, string $password): array
    {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Usuario y contraseña son requeridos', 'code' => 400];
        }

        $user = $this->usuarioRepo->findByUsername($username);

        if (!$user) {
            return ['success' => false, 'error' => 'Usuario no encontrado', 'code' => 404];
        }

        if (!self::verificarPassword($password, $user['Contrasena'])) {
            return ['success' => false, 'error' => 'Contraseña incorrecta', 'code' => 401];
        }

        // Rotar el ID de sesión al autenticar (previene session fixation).
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Establecer sesión
        $_SESSION['IdUsuario']     = (int)$user['Id'];
        $_SESSION['NombreUsuario'] = $user['NombreUsuario'];
        $_SESSION['IdRol']         = (int)$user['IdRol'];
        
        // Determinar permisos basados en rol
        $esAdmin = Config::isAdmin($user['NombreUsuario']);
        $rol = (int)$user['IdRol'];
        $esTesorero = ($rol === Config::ROL_TESORERO || $esAdmin);

        return [
            'success' => true,
            'user' => [
                'Id'             => $user['Id'],
                'NombreUsuario'  => $user['NombreUsuario'],
                'NombreCompleto' => $user['NombreCompleto'],
                'PuntosTotales'  => $user['PuntosTotales'],
                'IdRol'          => $user['IdRol'],
                'isAdmin'        => $esAdmin,
                'esTesorero'     => $esTesorero
            ]
        ];
    }

    /**
     * Registra un nuevo usuario.
     */
    public function register(string $username, string $password, string $fullName): array
    {
        if (empty($username) || empty($password) || empty($fullName)) {
            return ['success' => false, 'error' => 'Todos los campos son obligatorios', 'code' => 400];
        }

        if (strlen($username) > 50 || strlen($fullName) > 100) {
            return ['success' => false, 'error' => 'Límite de caracteres excedido', 'code' => 400];
        }

        if ($this->usuarioRepo->usernameExists($username)) {
            return ['success' => false, 'error' => 'El nombre de usuario ya está en uso', 'code' => 409];
        }

        // Crear usuario (password en texto plano legacy)
        $user = $this->usuarioRepo->create($username, $password, $fullName);

        if (!$user) {
            return ['success' => false, 'error' => 'Error interno al crear usuario', 'code' => 500];
        }

        return ['success' => true, 'message' => 'Usuario registrado exitosamente'];
    }

    /**
     * Compara la contraseña en texto plano recibida contra el valor
     * almacenado en Usuarios.Contrasena (también texto plano — decisión
     * explícita del usuario, ver PLAN_MIGRACION.md §0, no se toca el
     * almacenamiento). hash_equals() compara en tiempo constante para no
     * filtrar información por el tiempo de respuesta; no protege contra
     * un volcado de la BD, solo contra un timing attack sobre el login.
     *
     * Si algún día se decide migrar a hash, este es el único método que
     * habría que tocar (más una migración UPDATE de Usuarios.Contrasena).
     */
    public static function verificarPassword(string $passwordPlano, string $passwordAlmacenada): bool
    {
        return hash_equals($passwordAlmacenada, $passwordPlano);
    }

    /**
     * Cierra la sesión actual.
     */
    public function logout(): void
    {
        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }
}
