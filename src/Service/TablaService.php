<?php

// ═══════════════════════════════════════════════════════════════
// TablaService — Lógica de Negocio de Tablas de Puntuación
// ═══════════════════════════════════════════════════════════════

class TablaService
{
    private const CAMPOS_PERMISO_VALIDOS = ['PuedeVer', 'PuedeExportar', 'MostrarEnRanking'];

    private $tablaRepo;
    private $usuarioRepo;

    public function __construct()
    {
        $this->tablaRepo = new TablaRepository();
        $this->usuarioRepo = new UsuarioRepository();
    }

    /**
     * Actualiza (o crea) un permiso puntual de un usuario sobre una
     * tabla de puntuación.
     */
    public function actualizarPermiso(int $idTabla, int $idUsuario, string $field, int $value): array
    {
        if (!in_array($field, self::CAMPOS_PERMISO_VALIDOS, true)) {
            return ['success' => false, 'error' => 'Se requiere userId, idTabla y un field válido', 'code' => 400];
        }

        $this->tablaRepo->upsertPermiso($idTabla, $idUsuario, $field, $value);

        return ['success' => true, 'message' => 'Membresía actualizada correctamente'];
    }

    public function listar(): array
    {
        return $this->tablaRepo->findAll();
    }

    public function crear(string $nombre, string $descripcion, int $creadaPor, string $faseInicio = 'Grupos'): array
    {
        if (empty($nombre)) {
            return ['success' => false, 'error' => 'El nombre de la tabla es obligatorio', 'code' => 400];
        }

        if (strlen($nombre) > 100) {
            return ['success' => false, 'error' => 'El nombre no puede exceder 100 caracteres', 'code' => 400];
        }

        // Validar que la fase sea válida
        if (!in_array($faseInicio, Config::FASES_ORDEN, true)) {
            $faseInicio = 'Grupos';
        }

        if ($this->tablaRepo->findByName($nombre)) {
            return ['success' => false, 'error' => 'Ya existe una tabla con ese nombre', 'code' => 409];
        }

        $id = $this->tablaRepo->create($nombre, $descripcion, $creadaPor, $faseInicio);

        if (!$id) {
            return ['success' => false, 'error' => 'Error al crear tabla', 'code' => 500];
        }

        return [
            'success' => true, 
            'message' => "Tabla '$nombre' creada exitosamente",
            'tabla' => ['Id' => $id, 'Nombre' => $nombre, 'Descripcion' => $descripcion, 'FaseInicio' => $faseInicio]
        ];
    }

    public function agregarMiembro(int $idTabla, int $idUsuario): array
    {
        if (!$this->tablaRepo->findById($idTabla)) {
            return ['success' => false, 'error' => 'Tabla no encontrada', 'code' => 404];
        }

        if (!$this->usuarioRepo->exists($idUsuario)) {
            return ['success' => false, 'error' => 'Usuario no encontrado', 'code' => 404];
        }

        if ($this->tablaRepo->isMember($idTabla, $idUsuario)) {
            return ['success' => true, 'message' => 'El usuario ya es miembro de esta tabla'];
        }

        $this->tablaRepo->addMember($idTabla, $idUsuario);

        return ['success' => true, 'message' => 'Usuario agregado a la tabla'];
    }

    public function quitarMiembro(int $idTabla, int $idUsuario): array
    {
        $this->tablaRepo->removeMember($idTabla, $idUsuario);
        return ['success' => true, 'message' => 'Usuario removido de la tabla'];
    }

    public function obtenerMiembros(int $idTabla): array
    {
        if (!$this->tablaRepo->findById($idTabla)) {
            return ['success' => false, 'error' => 'Tabla no encontrada', 'code' => 404];
        }

        $miembros = $this->tablaRepo->getMembers($idTabla);
        // Formatear campo bool (legacy endpoint compatibility)
        foreach ($miembros as &$m) {
            $m['EsMiembro'] = (int)$m['EsMiembro'];
        }
        return ['success' => true, 'members' => $miembros];
    }
}
