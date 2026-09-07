<?php

// ═══════════════════════════════════════════════════════════════
// UsuarioService — Lógica de Negocio para gestión de Usuarios
// ═══════════════════════════════════════════════════════════════

class UsuarioService
{
    private $usuarioRepo;
    private $membresiaRepo;

    public function __construct()
    {
        $this->usuarioRepo = new UsuarioRepository();
        $this->membresiaRepo = new MembresiaRepository();
    }

    public function eliminarUsuario(int $idUsuarioAEliminar, string $usernameOperador): array
    {
        // No se puede eliminar a los admins principales por seguridad
        if (in_array($this->getUsernameById($idUsuarioAEliminar), Config::ADMINS, true)) {
            return ['success' => false, 'error' => 'No se puede eliminar a un administrador principal del sistema.', 'code' => 403];
        }

        $usuario = $this->usuarioRepo->findById($idUsuarioAEliminar);
        if (!$usuario) {
            return ['success' => false, 'error' => 'Usuario no encontrado', 'code' => 404];
        }

        $this->usuarioRepo->delete($idUsuarioAEliminar);

        return ['success' => true, 'message' => "Usuario '{$usuario['NombreUsuario']}' eliminado correctamente"];
    }

    /**
     * Usado tanto por updateUserAndMembership.php (cualquier rol, sin
     * mensaje de confirmación con detalle) como por convertToMember.php
     * (rol restringido a no-Admin, mensaje con detalle de la conversión)
     * — ambos cambian rol y membresía, esto es el único lugar que
     * realmente lo hace. Ver PLAN_MIGRACION.md §4.2.
     */
    public function actualizarPermisosYMembresia(int $idUsuario, string $nombreCompleto, int $idRol, ?float $montoPagado = null, ?int $competicionId = null): array
    {
        if (!$this->usuarioRepo->exists($idUsuario)) {
            return ['success' => false, 'error' => 'Usuario no encontrado', 'code' => 404];
        }

        if (!in_array($idRol, [Config::ROL_ADMIN, Config::ROL_TESORERO, Config::ROL_MIEMBRO, Config::ROL_USUARIO])) {
            return ['success' => false, 'error' => 'Rol inválido', 'code' => 400];
        }

        $compId = $competicionId ?? Config::competicionActiva();

        $this->usuarioRepo->updateUserAndMembership($idUsuario, $nombreCompleto, $idRol, $montoPagado, $compId);

        return ['success' => true, 'message' => 'Permisos y membresía actualizados'];
    }

    public function listarUsuariosConRoles(int $competicionId = null): array
    {
        $compId = $competicionId ?? Config::competicionActiva();
        return $this->usuarioRepo->findAllWithRoles($compId);
    }

    private function getUsernameById(int $id): string
    {
        $u = $this->usuarioRepo->findById($id);
        return $u ? $u['NombreUsuario'] : '';
    }
}
