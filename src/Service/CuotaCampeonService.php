<?php

// ═══════════════════════════════════════════════════════════════
// CuotaCampeonService — Lógica de Negocio de Cuotas Fijas Campeón
// ═══════════════════════════════════════════════════════════════

class CuotaCampeonService
{
    private $cuotaCampeonRepo;
    private $equipoRepo;
    private $partidoRepo;
    private $apuestaRepo;

    public function __construct()
    {
        $this->cuotaCampeonRepo = new CuotaCampeonRepository();
        $this->equipoRepo = new EquipoRepository();
        $this->partidoRepo = new PartidoRepository();
        $this->apuestaRepo = new ApuestaRepository();
    }

    /**
     * Cuota al campeón para un equipo. En cascada: cuota fija del admin
     * (CuotasCampeonFijas) → si no, cuota base de mercado (tabla
     * hardcodeada abajo) × decaimiento por avance del torneo × peso por
     * dinero apostado (crowd), con piso 1.10.
     *
     * Movido tal cual desde config/database.php::getChampionOdds() —
     * la tabla de mercado sigue con selecciones mundialistas y el
     * cálculo de avance sin filtrar por competición; ambas cosas son
     * la Fase 6 del plan de migración, no esta.
     */
    public function calcularCuota(int $idEquipo, string $nombreEquipo = ''): float
    {
        // 0. Cuota fija tiene prioridad
        $fija = $this->cuotaCampeonRepo->findByEquipo($idEquipo);
        if ($fija && !$fija['Excluido']) {
            return (float)$fija['CuotaFija'];
        }

        // 1. Progreso del torneo
        $progreso = $this->partidoRepo->getProgresoTorneo();
        $jugados = (int)($progreso['Jugados'] ?? 0);
        $totales = (int)($progreso['Totales'] ?? 0);
        $avance = ($totales > 0) ? ($jugados / $totales) : 0;

        // 2. Monto apostado al equipo y total global (crowd money)
        $montoEquipo = $this->apuestaRepo->getMontoApostadoPorEquipoCampeon($idEquipo);
        $montoGlobal = $this->apuestaRepo->getMontoApostadoGlobalCampeon();

        // 3. Nombre si no se pasó
        if (empty($nombreEquipo)) {
            $equipo = $this->equipoRepo->findById($idEquipo);
            $nombreEquipo = $equipo ? $equipo['Nombre'] : '';
        }

        $nombreNorm = strtolower(trim($nombreEquipo));

        // 4. Cuota base — tabla explícita de mercado (Doradobet, junio 2026)
        $cuotasMercado = [
            'españa'                            => 5.00,
            'francia'                           => 6.00,
            'inglaterra'                        => 7.00,
            'brasil'                            => 9.00,
            'argentina'                         => 9.50,
            'alemania'                          => 10.00,
            'portugal'                          => 11.00,
            'países bajos'                      => 13.00,
            'bélgica'                           => 17.00,
            'uruguay'                           => 21.00,
            'colombia'                          => 26.00,
            'italia'                            => 28.00,
            'croacia'                           => 34.00,
            'estados unidos'                    => 36.00,
            'méxico'                            => 41.00,
            'japón'                             => 51.00,
            'corea del sur'                     => 67.00,
            'marruecos'                         => 34.00,
            'senegal'                           => 67.00,
            'suiza'                             => 51.00,
            'austria'                           => 51.00,
            'turquía'                           => 51.00,
            'canadá'                            => 81.00,
            'ecuador'                           => 67.00,
            'paraguay'                          => 101.00,
            'australia'                         => 126.00,
            'serbia'                            => 67.00,
            'escocia'                           => 126.00,
            'noruega'                           => 81.00,
            'suecia'                            => 101.00,
            'irán'                              => 201.00,
            'arabia saudita'                    => 201.00,
            'catar'                             => 251.00,
            'nueva zelanda'                     => 301.00,
            'panamá'                            => 201.00,
            'haití'                             => 501.00,
        ];

        $cuotaBase = $cuotasMercado[$nombreNorm] ?? 5.00;
        $cuotaAvance = $cuotaBase * (1.0 - ($avance * 0.8));

        if ($montoGlobal > 50) {
            $pctLiquidez = ($montoEquipo / $montoGlobal);
            if ($pctLiquidez > 0) {
                $cuotaCrowd = min(0.85 / $pctLiquidez, $cuotaBase * 2);
            } else {
                $cuotaCrowd = $cuotaBase * 2;
            }
            $pesoCrowd = min($montoGlobal / 1000, 0.8);
            $cuotaFinal = ($cuotaAvance * (1 - $pesoCrowd)) + ($cuotaCrowd * $pesoCrowd);
        } else {
            $cuotaFinal = $cuotaAvance;
        }

        return max(1.10, round($cuotaFinal, 2));
    }

    /**
     * Obtener todos los equipos con su estado de cuota fija
     * Retorna lista completa: equipos sin cuota fija + equipos con cuota fija
     */
    public function getAllEquiposConCuotas(): array
    {
        $equipos = $this->equipoRepo->findAll();
        $cuotasFijas = $this->cuotaCampeonRepo->findAll();

        // Crear mapa de cuotas fijas por IdEquipo
        $fixedMap = [];
        foreach ($cuotasFijas as $cf) {
            $fixedMap[(int)$cf['IdEquipo']] = $cf;
        }

        $result = [];
        foreach ($equipos as $equipo) {
            $idEquipo = (int)$equipo['Id'];
            $item = [
                'IdEquipo'       => $idEquipo,
                'Nombre'         => $equipo['Nombre'],
                'BanderaURL'     => $equipo['BanderaURL'],
                'CodigoISO'      => $equipo['CodigoISO'] ?? null,
                'CuotaDinamica'  => $this->calcularCuota($idEquipo, $equipo['Nombre']),
                'CuotaFija'      => null,
                'Excluido'       => false,
                'TieneCuotaFija' => false,
            ];

            if (isset($fixedMap[$idEquipo])) {
                $item['CuotaFija']      = (float)$fixedMap[$idEquipo]['CuotaFija'];
                $item['Excluido']       = (bool)$fixedMap[$idEquipo]['Excluido'];
                $item['TieneCuotaFija'] = true;
                $item['FechaActualizacion'] = $fixedMap[$idEquipo]['FechaActualizacion'];
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Establecer cuota fija para un equipo
     */
    public function setFixed(int $idEquipo, float $cuotaFija, bool $excluido, int $adminId): array
    {
        // Validar equipo existe
        $equipo = $this->equipoRepo->findById($idEquipo);
        if (!$equipo) {
            return ['success' => false, 'error' => 'Equipo no encontrado', 'code' => 404];
        }

        // Validar cuota
        if ($cuotaFija < 1.01) {
            return ['success' => false, 'error' => 'La cuota debe ser mayor a 1.00', 'code' => 400];
        }

        $this->cuotaCampeonRepo->upsert($idEquipo, $cuotaFija, $excluido, $adminId);

        $accion = $excluido ? 'excluido con cuota fija' : 'cuota fija establecida';
        return [
            'success' => true,
            'message' => "Equipo '{$equipo['Nombre']}': {$accion} a {$cuotaFija}"
        ];
    }

    /**
     * Eliminar cuota fija de un equipo (volver a cálculo dinámico)
     */
    public function removeFixed(int $idEquipo): array
    {
        $equipo = $this->equipoRepo->findById($idEquipo);
        if (!$equipo) {
            return ['success' => false, 'error' => 'Equipo no encontrado', 'code' => 404];
        }

        $this->cuotaCampeonRepo->delete($idEquipo);

        return [
            'success' => true,
            'message' => "Cuota fija eliminada para '{$equipo['Nombre']}'. Se usará el cálculo dinámico."
        ];
    }

    /**
     * Verificar si un equipo está excluido manualmente
     */
    public function isExcluido(int $idEquipo): bool
    {
        $fixed = $this->cuotaCampeonRepo->findByEquipo($idEquipo);
        return $fixed && (bool)$fixed['Excluido'];
    }

    /**
     * Obtener todos los IDs de equipos excluidos manualmente
     */
    public function getExcluidoIds(): array
    {
        return $this->cuotaCampeonRepo->getExcluidoIds();
    }
}
