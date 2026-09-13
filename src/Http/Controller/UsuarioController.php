<?php

// ═══════════════════════════════════════════════════════════════
// UsuarioController — Controlador de Gestión de Usuarios (Admin)
// ═══════════════════════════════════════════════════════════════

class UsuarioController
{
    private $usuarioService;

    public function __construct()
    {
        $this->usuarioService = new UsuarioService();
    }

    public function deleteUser(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $id = $request->inputInt('id') ?: $request->inputInt('userId');
        if ($id <= 0) {
            JsonResponse::error('ID inválido', 400);
        }

        $adminName = AuthMiddleware::getUsername();

        $result = $this->usuarioService->eliminarUsuario($id, $adminName);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success(null, $result['message']);
    }

    /**
     * Convierte un usuario a Miembro/Tesorero/Usuario Regular con un
     * monto de membresía, devolviendo el detalle de la conversión.
     * Usa el mismo UsuarioService::actualizarPermisosYMembresia() que
     * updateUserAndMembership() — antes tenían SQL duplicado y
     * divergente (ver PLAN_MIGRACION.md §4.2).
     */
    public function convertToMember(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        if (!$request->has('userId') || !$request->has('montoPagado')) {
            JsonResponse::error('Faltan campos: userId, montoPagado', 400);
        }

        $userId = $request->inputInt('userId');
        $montoPagado = $request->inputFloat('montoPagado');
        $nuevoRol = $request->has('rol') ? $request->inputInt('rol') : Config::ROL_MIEMBRO;
        $competicionId = $request->inputInt('competicionId') ?: Config::competicionActiva();

        if (!in_array($nuevoRol, [Config::ROL_TESORERO, Config::ROL_MIEMBRO, Config::ROL_USUARIO])) {
            JsonResponse::error('Rol inválido. Opciones: 2=Tesorero, 3=Miembro, 4=Usuario Regular', 400);
        }

        $usuarioRepo = new UsuarioRepository();
        $usuario = $usuarioRepo->findById($userId);
        if (!$usuario) {
            JsonResponse::error('Usuario no encontrado', 404);
        }

        $result = $this->usuarioService->actualizarPermisosYMembresia($userId, $usuario['NombreCompleto'], $nuevoRol, $montoPagado, $competicionId);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        $rolesNombres = [1 => 'Admin', 2 => 'Tesorero', 3 => 'Miembro', 4 => 'Usuario Regular'];

        JsonResponse::success([
            'usuario' => [
                'Id' => $userId,
                'NombreUsuario' => $usuario['NombreUsuario'],
                'NombreCompleto' => $usuario['NombreCompleto'],
                'RolAnterior' => $rolesNombres[(int)($usuario['IdRol'] ?? 4)],
                'RolNuevo' => $rolesNombres[$nuevoRol],
                'MontoPagado' => $montoPagado,
            ],
        ], "Usuario @{$usuario['NombreUsuario']} convertido a {$rolesNombres[$nuevoRol]} exitosamente");
    }

    public function updateUserAndMembership(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $idUsuario = $request->inputInt('idUsuario') ?: $request->inputInt('userId');
        $nombreCompleto = $request->input('nombreCompleto');
        $idRol = $request->inputInt('idRol');
        $montoPagado = $request->inputFloat('montoPagado', 0); // Opcional, 0 = no pago nuevo

        if ($idUsuario <= 0 || $idRol <= 0) {
            JsonResponse::error('Datos inválidos. Verifica todos los campos.', 400);
        }

        if (empty($nombreCompleto)) {
            $userRepo = new UsuarioRepository();
            $u = $userRepo->findById($idUsuario);
            if ($u) {
                $nombreCompleto = $u['NombreCompleto'];
            } else {
                JsonResponse::error('Usuario no encontrado.', 404);
            }
        }

        $montoToPass = $montoPagado > 0 ? $montoPagado : null;

        $result = $this->usuarioService->actualizarPermisosYMembresia($idUsuario, $nombreCompleto, $idRol, $montoToPass);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success(null, $result['message']);
    }

    public function getUsersPermissions(Request $request, int $competicionId = null): void
    {
        AuthMiddleware::requireAdmin();

        if (!$request->isMethod('GET')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $compId = $competicionId ?? Config::competicionActiva();
        $usuarios = $this->usuarioService->listarUsuariosConRoles($compId);

        $tablaRepo = new TablaRepository();
        $tablas = $tablaRepo->findAllWithMemberCount();
        $membresias = $tablaRepo->findAllMembresias();

        JsonResponse::send([
            'success' => true, 
            'users' => $usuarios, 
            'tablas' => $tablas, 
            'membresias' => $membresias
        ]);
    }
}
