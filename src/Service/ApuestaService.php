<?php

// ═══════════════════════════════════════════════════════════════
// ApuestaService — Lógica de Negocio de Apuestas
// ═══════════════════════════════════════════════════════════════

class ApuestaService
{
    private $apuestaRepo;
    private $partidoRepo;
    private $equipoRepo;
    private $partidoService;
    private $cuotaCampeonService;

    public function __construct()
    {
        $this->apuestaRepo = new ApuestaRepository();
        $this->partidoRepo = new PartidoRepository();
        $this->equipoRepo = new EquipoRepository();
        $this->partidoService = new PartidoService();
        $this->cuotaCampeonService = new CuotaCampeonService();
    }

    public function apostarPartido(int $idUsuario, int $idPartido, float $monto, string $equipoApostado): array
    {
        if ($monto <= 0) {
            return ['success' => false, 'error' => 'El monto debe ser mayor a 0', 'code' => 400];
        }

        if (!in_array($equipoApostado, Config::EQUIPOS_APUESTA_VALIDOS)) {
            return ['success' => false, 'error' => 'Equipo apostado inválido. Usa: Local, Empate, Visitante', 'code' => 400];
        }

        // Todo dentro de una transacción con bloqueo de fila sobre el
        // partido: cierra la ventana entre "leer que no ha empezado" y
        // "insertar la apuesta" frente a una sincronización concurrente
        // que marque el partido como iniciado.
        return Database::transaction(function () use ($idUsuario, $idPartido, $monto, $equipoApostado) {
            $partidoRow = $this->partidoRepo->findByIdForUpdate($idPartido);
            if (!$partidoRow) {
                return ['success' => false, 'error' => 'Partido no encontrado', 'code' => 404];
            }

            $partido = Partido::fromRow($partidoRow);

            // Validar bloqueo temporal
            $ahora = Config::now();
            $fechaDT = new \DateTime($partido->getFechaHoraCST(), new \DateTimeZone(Config::TIMEZONE));
            if ($ahora >= $fechaDT || $partido->isBloqueado()) {
                return ['success' => false, 'error' => 'El partido ya ha comenzado. Las apuestas están cerradas.', 'code' => 403];
            }

            $cuota = $partido->getCuota($equipoApostado);
            if ($cuota === null || $cuota <= 0) {
                return ['success' => false, 'error' => 'No hay cuotas disponibles para esta selección.', 'code' => 400];
            }

            $id = $this->apuestaRepo->createMatchBet($idUsuario, $idPartido, $monto, $cuota, $equipoApostado);

            return ['success' => true, 'message' => 'Apuesta registrada correctamente y pendiente de aprobación', 'id' => $id];
        });
    }

    public function apostarCampeon(int $idUsuario, int $idEquipo, float $monto, float $cuotaFijadaManual = 0): array
    {
        if ($monto <= 0) {
            return ['success' => false, 'error' => 'El monto debe ser mayor a 0', 'code' => 400];
        }

        $equipo = $this->equipoRepo->findById($idEquipo);
        if (!$equipo) {
            return ['success' => false, 'error' => 'Equipo no encontrado', 'code' => 404];
        }

        // Verificar si el equipo está excluido de las apuestas al campeón
        $cuotaCampeonRepo = new CuotaCampeonRepository();
        $cuotaFijaRow = $cuotaCampeonRepo->findByEquipo($idEquipo);
        if ($cuotaFijaRow && $cuotaFijaRow['Excluido']) {
            return ['success' => false, 'error' => 'Este equipo ha sido excluido de las apuestas al campeón', 'code' => 403];
        }

        // Verificar si el equipo fue eliminado del torneo
        $eliminados = $this->partidoService->getEquiposEliminados();
        if (in_array($idEquipo, $eliminados)) {
            return ['success' => false, 'error' => 'Este equipo ya fue eliminado del torneo', 'code' => 403];
        }

        // Obtener cuota: calcularCuota() ya prioriza cuotas fijas sobre dinámicas
        $cuota = $cuotaFijadaManual > 0 ? $cuotaFijadaManual : $this->cuotaCampeonService->calcularCuota($idEquipo, $equipo['Nombre']);


        $id = $this->apuestaRepo->createChampionBet($idUsuario, $idEquipo, $monto, $cuota);

        return ['success' => true, 'message' => 'Apuesta de campeón registrada correctamente y pendiente de aprobación', 'id' => $id, 'cuota' => $cuota, 'ganancia' => round($monto * $cuota, 2)];
    }
}
