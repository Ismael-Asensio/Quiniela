<?php

// ═══════════════════════════════════════════════════════════════
// PenalizacionService — Lógica de Negocio de Penalizaciones
// ═══════════════════════════════════════════════════════════════

class PenalizacionService
{
    private $penalizacionRepo;
    private $usuarioRepo;
    private $tablaRepo;
    private $puntosService;

    public function __construct()
    {
        $this->penalizacionRepo = new PenalizacionRepository();
        $this->usuarioRepo = new UsuarioRepository();
        $this->tablaRepo = new TablaRepository();
        $this->puntosService = new PuntosService();
    }

    public function listar(?string $estado = null, ?string $busqueda = null): array
    {
        return $this->penalizacionRepo->findAll($estado, $busqueda);
    }

    /**
     * $idTabla es obligatorio y debe pertenecer a la competición activa
     * — antes caía a la tabla 2 hardcodeada, que podía no existir o no
     * corresponder a la competición realmente activa.
     */
    public function crear(array $userIds, int $puntos, string $motivo, int $creadaPor, int $idTabla): array
    {
        if ($puntos <= 0) {
            return ['success' => false, 'error' => 'Los puntos a restar deben ser mayor a 0', 'code' => 400];
        }

        if (empty($motivo)) {
            return ['success' => false, 'error' => 'El motivo es obligatorio', 'code' => 400];
        }

        if (empty($userIds) || !is_array($userIds)) {
            return ['success' => false, 'error' => 'No se seleccionaron usuarios válidos', 'code' => 400];
        }

        if ($idTabla <= 0 || !$this->tablaRepo->existsInCompeticionActiva($idTabla)) {
            return ['success' => false, 'error' => 'Tabla de puntuación inválida para la competición activa', 'code' => 400];
        }

        // Todo o nada: si el proceso muere a mitad del lote, no debe
        // quedar una penalización creada sin su recálculo de puntos
        // correspondiente, ni un lote aplicado solo parcialmente.
        $aplicadas = Database::transaction(function () use ($userIds, $puntos, $motivo, $creadaPor, $idTabla) {
            $aplicadas = 0;
            foreach ($userIds as $idUsuario) {
                $idUsuario = (int)$idUsuario;
                if (!$this->usuarioRepo->exists($idUsuario)) {
                    continue;
                }

                $id = $this->penalizacionRepo->create($idUsuario, $puntos, $motivo, $creadaPor, $idTabla);
                if ($id) {
                    // Recalcular puntos del usuario afectado
                    $this->puntosService->recalcularPuntosUsuario($idUsuario);
                    $aplicadas++;
                }
            }
            return $aplicadas;
        });

        if ($aplicadas === 0) {
            return ['success' => false, 'error' => 'No se pudo aplicar la sanción a ningún usuario (quizás no existían)', 'code' => 404];
        }

        return ['success' => true, 'message' => "Penalización aplicada correctamente a {$aplicadas} usuario(s)"];
    }

    public function anular(int $idPenalizacion, int $anuladaPor): array
    {
        $penalizacion = $this->penalizacionRepo->findById($idPenalizacion);

        if (!$penalizacion) {
            return ['success' => false, 'error' => 'Penalización no encontrada', 'code' => 404];
        }

        if ($penalizacion['Estado'] === 'Anulada') {
            return ['success' => false, 'error' => 'La penalización ya está anulada', 'code' => 400];
        }

        $this->penalizacionRepo->updateStatus($idPenalizacion, 'Anulada', $anuladaPor);
        $this->puntosService->recalcularPuntosUsuario((int)$penalizacion['IdUsuario']);

        return ['success' => true, 'message' => 'Penalización anulada y puntos restituidos'];
    }

    public function reactivar(int $idPenalizacion, int $reactivadaPor): array
    {
        $penalizacion = $this->penalizacionRepo->findById($idPenalizacion);

        if (!$penalizacion) {
            return ['success' => false, 'error' => 'Penalización no encontrada', 'code' => 404];
        }

        if ($penalizacion['Estado'] === 'Activa') {
            return ['success' => false, 'error' => 'La penalización ya está activa', 'code' => 400];
        }

        $this->penalizacionRepo->updateStatus($idPenalizacion, 'Activa', $reactivadaPor);
        $this->puntosService->recalcularPuntosUsuario((int)$penalizacion['IdUsuario']);

        return ['success' => true, 'message' => 'Penalización reactivada y puntos restados nuevamente'];
    }

    public function modificar(int $idPenalizacion, int $puntos, string $motivo, int $modificadaPor): array
    {
        if ($puntos <= 0 || empty($motivo)) {
            return ['success' => false, 'error' => 'Puntos inválidos o motivo vacío', 'code' => 400];
        }

        $penalizacion = $this->penalizacionRepo->findById($idPenalizacion);

        if (!$penalizacion) {
            return ['success' => false, 'error' => 'Penalización no encontrada', 'code' => 404];
        }

        $this->penalizacionRepo->update($idPenalizacion, $puntos, $motivo, $modificadaPor);

        if ($penalizacion['Estado'] === 'Activa') {
            $this->puntosService->recalcularPuntosUsuario((int)$penalizacion['IdUsuario']);
        }

        return ['success' => true, 'message' => 'Penalización actualizada correctamente'];
    }

    public function obtenerResumen(): array
    {
        $resumen = Database::fetchOne("
            SELECT 
                COUNT(Id) AS Total,
                SUM(CASE WHEN Estado = 'Activa' THEN 1 ELSE 0 END) AS Activas,
                SUM(CASE WHEN Estado = 'Anulada' THEN 1 ELSE 0 END) AS Anuladas,
                SUM(CASE WHEN Estado = 'Activa' THEN PuntosRestados ELSE 0 END) AS TotalPuntosRestados
            FROM Penalizaciones
        ");
        
        return [
            'Total' => (int)($resumen['Total'] ?? 0),
            'Activas' => (int)($resumen['Activas'] ?? 0),
            'Anuladas' => (int)($resumen['Anuladas'] ?? 0),
            'TotalPuntosRestados' => (int)($resumen['TotalPuntosRestados'] ?? 0)
        ];
    }
}
