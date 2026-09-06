<?php

// ═══════════════════════════════════════════════════════════════
// ApuestaDashboardService — Ensamblado de dashboards de apuestas
// ═══════════════════════════════════════════════════════════════

class ApuestaDashboardService
{
    private $apuestaDashboardRepo;
    private $apuestaRepo;
    private $equipoRepo;
    private $membresiaRepo;
    private $pozoService;
    private $partidoService;
    private $cuotaCampeonService;

    public function __construct()
    {
        $this->apuestaDashboardRepo = new ApuestaDashboardRepository();
        $this->apuestaRepo = new ApuestaRepository();
        $this->equipoRepo = new EquipoRepository();
        $this->membresiaRepo = new MembresiaRepository();
        $this->pozoService = new PozoService();
        $this->partidoService = new PartidoService();
        $this->cuotaCampeonService = new CuotaCampeonService();
    }

    /**
     * Datos para los gráficos de apuestas visibles a cualquier usuario
     * logueado: montos apostados al campeón por equipo, y montos
     * apostados por partido desglosados en Local/Empate/Visitante.
     */
    public function getMemberCharts(int $competicionId): array
    {
        $campeonRows = $this->apuestaDashboardRepo->getResumenApuestasCampeonPorEquipo($competicionId);
        $campeon = array_map(function (array $row) {
            return [
                'Equipo' => $row['Equipo'],
                'MontoTotal' => (float)$row['MontoTotal'],
                'CantidadPersonas' => (int)$row['CantidadPersonas'],
            ];
        }, $campeonRows);

        $partidosRaw = $this->apuestaDashboardRepo->getResumenApuestasPartidoPorPartido($competicionId);

        $partidosProcesados = [];
        foreach ($partidosRaw as $row) {
            $id = $row['IdPartido'];
            if (!isset($partidosProcesados[$id])) {
                $partidosProcesados[$id] = [
                    'Titulo' => $row['Local'] . ' vs ' . $row['Visitante'],
                    'Local' => ['Monto' => 0, 'Personas' => 0],
                    'Empate' => ['Monto' => 0, 'Personas' => 0],
                    'Visitante' => ['Monto' => 0, 'Personas' => 0],
                ];
            }

            $pronostico = $row['Pronostico']; // 'Local', 'Empate', 'Visitante'
            $key = '';
            if ($pronostico == 'Local' || $pronostico == 'L') $key = 'Local';
            elseif ($pronostico == 'Empate' || $pronostico == 'E') $key = 'Empate';
            elseif ($pronostico == 'Visitante' || $pronostico == 'V') $key = 'Visitante';

            if ($key) {
                $partidosProcesados[$id][$key]['Monto'] += (float)$row['MontoTotal'];
                $partidosProcesados[$id][$key]['Personas'] += (int)$row['CantidadPersonas'];
            }
        }

        return [
            'campeon' => $campeon,
            'partidos' => array_values($partidosProcesados),
        ];
    }

    /**
     * Vista de tesorería/admin: todas las apuestas aprobadas de todos
     * los usuarios, con el pago real contra el pozo y un resumen
     * global.
     */
    public function getAllBets(int $competicionId): array
    {
        $pozosPartidos = $this->pozoService->getPozoPartidos($competicionId);
        $pozosCampeon = $this->pozoService->getPozoCampeon($competicionId);

        $apuestasCampeon = [];
        $totalMontoCampeon = 0;
        foreach ($this->apuestaDashboardRepo->findApprovedChampionBets($competicionId) as $row) {
            if ($row['Fecha'] instanceof \DateTime) {
                $row['Fecha'] = $row['Fecha']->format('Y-m-d H:i:s');
            }
            $row['GananciaPotencial'] = round(floatval($row['MontoApostado']) * floatval($row['CuotaFijada']), 2);
            $row['MontoProyectado'] = $row['GananciaPotencial'];
            $row['PagoConPozo'] = $this->pozoService->calcularPagoCampeon($pozosCampeon, $row['IdEquipo'], floatval($row['MontoApostado']), floatval($row['CuotaFijada']), 1);
            $totalMontoCampeon += floatval($row['MontoApostado']);
            $apuestasCampeon[] = $row;
        }

        $apuestasPartidos = [];
        $totalMontoPartidos = 0;
        $pendientesPartidos = 0;
        foreach ($this->apuestaDashboardRepo->findApprovedMatchBets($competicionId) as $row) {
            if ($row['Fecha'] instanceof \DateTime) {
                $row['Fecha'] = $row['Fecha']->format('Y-m-d H:i:s');
            }
            $row['GananciaPotencial'] = round(floatval($row['MontoApostado']) * floatval($row['CuotaFijada']), 2);
            $row['MontoProyectado'] = $row['GananciaPotencial'];
            $row['PagoConPozo'] = $this->pozoService->calcularPagoPartido($pozosPartidos, $row['IdPartido'], $row['EquipoApostado'], floatval($row['MontoApostado']), floatval($row['CuotaFijada']), 1);
            $totalMontoPartidos += floatval($row['MontoApostado']);
            if ($row['EstadoApuesta'] === 'Pendiente') $pendientesPartidos++;
            $apuestasPartidos[] = $row;
        }

        return [
            'apuestasCampeon' => $apuestasCampeon,
            'apuestasPartidos' => $apuestasPartidos,
            'resumen' => [
                'TotalMontoCampeon' => $totalMontoCampeon,
                'TotalApuestasCampeon' => count($apuestasCampeon),
                'TotalMontoPartidos' => $totalMontoPartidos,
                'TotalApuestasPartidos' => count($apuestasPartidos),
                'PendientesPartidos' => $pendientesPartidos,
            ],
        ];
    }

    /**
     * Dashboard financiero personal del Miembro (o Admin): resumen,
     * detalle de apuestas propias con pago real contra el pozo, equipos
     * disponibles para apostar al campeón (con cuota actual) y última
     * membresía.
     */
    public function getMiembroDashboard(int $idUsuario, int $competicionId): array
    {
        $pozosPartidos = $this->pozoService->getPozoPartidos($competicionId);
        $pozosCampeon = $this->pozoService->getPozoCampeon($competicionId);

        $resumenRow = $this->apuestaRepo->getResumenApuestasPartido($idUsuario, $competicionId);
        $gananciaNeta = floatval($resumenRow['TotalGanado']) - floatval($resumenRow['TotalPerdido']);

        $apuestas = [];
        foreach ($this->apuestaRepo->findMatchBetsByUser($idUsuario, $competicionId) as $row) {
            if ($row['Fecha'] instanceof \DateTime) {
                $row['Fecha'] = $row['Fecha']->format('Y-m-d H:i:s');
            }
            $row['MontoProyectado'] = round(floatval($row['MontoApostado']) * floatval($row['CuotaFijada']), 2);
            $row['PagoConPozo'] = $this->pozoService->calcularPagoPartido($pozosPartidos, $row['IdPartido'], $row['EquipoApostado'], floatval($row['MontoApostado']), floatval($row['CuotaFijada']), intval($row['Aprobado']));
            $apuestas[] = $row;
        }

        $apuestasCampeon = [];
        foreach ($this->apuestaRepo->findChampionBetsByUser($idUsuario, $competicionId) as $row) {
            if ($row['Fecha'] instanceof \DateTime) {
                $row['Fecha'] = $row['Fecha']->format('Y-m-d H:i:s');
            }
            $row['MontoProyectado'] = round(floatval($row['MontoApostado']) * floatval($row['CuotaFijada']), 2);
            $row['PagoConPozo'] = $this->pozoService->calcularPagoCampeon($pozosCampeon, $row['IdEquipo'], floatval($row['MontoApostado']), floatval($row['CuotaFijada']), intval($row['Aprobado']));
            $apuestasCampeon[] = $row;
        }

        $eliminados = $this->partidoService->getEquiposEliminados($competicionId);
        $equipos = [];
        foreach ($this->equipoRepo->findAll() as $row) {
            if (in_array((int)$row['Id'], $eliminados)) continue;
            $equipos[] = [
                'Id' => $row['Id'],
                'Nombre' => $row['Nombre'],
                'BanderaURL' => $row['BanderaURL'],
                'CuotaActual' => $this->cuotaCampeonService->calcularCuota($row['Id'], $row['Nombre']),
            ];
        }

        $membresiaRow = $this->membresiaRepo->findByUser($idUsuario)[0] ?? null;
        $membresia = null;
        if ($membresiaRow) {
            $membresia = [
                'MontoPagado' => $membresiaRow['MontoPagado'],
                'FechaPago' => $membresiaRow['FechaPago'] instanceof \DateTime ? $membresiaRow['FechaPago']->format('Y-m-d H:i:s') : $membresiaRow['FechaPago'],
                'Estado' => $membresiaRow['Estado'],
            ];
        }

        return [
            'resumen' => [
                'TotalApostado' => floatval($resumenRow['TotalApostado']),
                'TotalGanado' => floatval($resumenRow['TotalGanado']),
                'TotalPerdido' => floatval($resumenRow['TotalPerdido']),
                'GananciaNeta' => $gananciaNeta,
                'TotalApuestas' => intval($resumenRow['TotalApuestas']),
                'Pendientes' => intval($resumenRow['Pendientes']),
                'Ganadas' => intval($resumenRow['Ganadas']),
                'Perdidas' => intval($resumenRow['Perdidas']),
            ],
            'apuestasPartidos' => $apuestas,
            'apuestasCampeon' => $apuestasCampeon,
            'equipos' => $equipos,
            'membresia' => $membresia,
        ];
    }

    /**
     * Dashboard de tesorería: recaudación de membresías, apuestas al
     * campeón por equipo, recaudación diaria, membresías recientes y
     * resumen de apuestas de partido. Sin casteos: los campos numéricos
     * se devuelven tal como los entrega sqlsrv (algunos DECIMAL salen
     * como string, p.ej. "600.00" — es el comportamiento legacy y el
     * frontend ya lo espera así).
     */
    public function getTesoreroDashboard(int $competicionId): array
    {
        $membresias = $this->membresiaRepo->getResumen($competicionId);
        $campeonData = $this->apuestaDashboardRepo->getCampeonPorEquipoConDetalle($competicionId);
        $porDia = $this->apuestaDashboardRepo->getRecaudacionPorDia($competicionId);

        $memRecientes = [];
        foreach ($this->membresiaRepo->getRecientes($competicionId) as $row) {
            if ($row['FechaPago'] instanceof \DateTime) {
                $row['FechaPago'] = $row['FechaPago']->format('Y-m-d H:i:s');
            }
            $memRecientes[] = $row;
        }

        $apuestasResumen = $this->apuestaDashboardRepo->getResumenApuestasPartidoAprobadas($competicionId);
        $memPorDia = $this->membresiaRepo->getPorDia($competicionId);

        return [
            'membresias' => $membresias,
            'campeonPorEquipo' => $campeonData,
            'recaudacionPorDia' => $porDia,
            'membresiasRecientes' => $memRecientes,
            'apuestasResumen' => $apuestasResumen,
            'membresiasPorDia' => $memPorDia,
        ];
    }

    /**
     * Cola de apuestas pendientes de aprobación (partido + campeón),
     * con el desglose de todas las apuestas del mismo usuario sobre el
     * mismo partido/equipo. El desglose se resuelve con una consulta
     * agrupada por tipo (2 consultas en total) en vez de una por
     * apuesta pendiente, a diferencia del endpoint legacy original.
     */
    public function getPendingBetsDetallado(int $competicionId): array
    {
        $pozosPartidos = $this->pozoService->getPozoPartidos($competicionId);
        $pozosCampeon = $this->pozoService->getPozoCampeon($competicionId);

        $pendingBets = [];

        foreach ($this->apuestaDashboardRepo->findPendingMatchBets($competicionId) as $row) {
            if ($row['Fecha'] instanceof \DateTime) {
                $row['Fecha'] = $row['Fecha']->format('Y-m-d H:i:s');
            }
            $pendingBets[] = [
                'IdUsuario' => $row['IdUsuario'],
                'IdRelacionado' => $row['IdPartido'],
                'Tipo' => 'Partido',
                'Id' => $row['Id'],
                'Usuario' => $row['NombreUsuario'],
                'NombreCompleto' => $row['NombreCompleto'],
                'Monto' => floatval($row['MontoApostado']),
                'Cuota' => floatval($row['CuotaFijada']),
                'Neto' => round(floatval($row['MontoApostado']) * floatval($row['CuotaFijada']), 2),
                'PagoConPozo' => $this->pozoService->calcularPagoPartido($pozosPartidos, $row['IdPartido'], $row['EquipoApostado'], floatval($row['MontoApostado']), floatval($row['CuotaFijada']), 0),
                'Evento' => "{$row['NombreLocal']} vs {$row['NombreVisitante']} ({$row['Fase']})",
                'BanderaURL' => '', // Los partidos no tienen una sola bandera
                'Prediccion' => $row['EquipoApostado'],
                'NumeroApuesta' => $row['NumeroApuesta'],
                'Fecha' => $row['Fecha'],
            ];
        }

        foreach ($this->apuestaDashboardRepo->findPendingChampionBets($competicionId) as $row) {
            if ($row['Fecha'] instanceof \DateTime) {
                $row['Fecha'] = $row['Fecha']->format('Y-m-d H:i:s');
            }
            $pendingBets[] = [
                'IdUsuario' => $row['IdUsuario'],
                'IdRelacionado' => $row['IdEquipo'],
                'Tipo' => 'Campeon',
                'Id' => $row['Id'],
                'Usuario' => $row['NombreUsuario'],
                'NombreCompleto' => $row['NombreCompleto'],
                'Monto' => floatval($row['MontoApostado']),
                'Cuota' => floatval($row['CuotaFijada']),
                'Neto' => round(floatval($row['MontoApostado']) * floatval($row['CuotaFijada']), 2),
                'PagoConPozo' => $this->pozoService->calcularPagoCampeon($pozosCampeon, $row['IdEquipo'], floatval($row['MontoApostado']), floatval($row['CuotaFijada']), 0),
                'Evento' => 'Campeón de la Liga',
                'BanderaURL' => $row['BanderaURL'],
                'Prediccion' => $row['NombreEquipo'],
                'NumeroApuesta' => $row['NumeroApuesta'],
                'Fecha' => $row['Fecha'],
            ];
        }

        // Desglose en lote: agrupar por tipo, batch-fetch, e indexar por
        // "IdUsuario|IdRelacionado" para lookup O(1) por apuesta pendiente.
        $esPartido = function ($pb) { return $pb['Tipo'] === 'Partido'; };
        $esCampeon = function ($pb) { return $pb['Tipo'] === 'Campeon'; };

        $pendingPartido = array_filter($pendingBets, $esPartido);
        $idsUsuarioPartido = array_column($pendingPartido, 'IdUsuario');
        $idsPartido = array_column($pendingPartido, 'IdRelacionado');
        $desglosePartidoRaw = $this->apuestaDashboardRepo->findDesgloseMatchBets($idsUsuarioPartido, $idsPartido);

        $pendingCampeon = array_filter($pendingBets, $esCampeon);
        $idsUsuarioCampeon = array_column($pendingCampeon, 'IdUsuario');
        $idsEquipo = array_column($pendingCampeon, 'IdRelacionado');
        $desgloseCampeonRaw = $this->apuestaDashboardRepo->findDesgloseChampionBets($idsUsuarioCampeon, $idsEquipo);

        $gruposPartido = [];
        foreach ($desglosePartidoRaw as $d) {
            $gruposPartido[$d['IdUsuario'] . '|' . $d['IdPartido']][] = $d;
        }
        $gruposCampeon = [];
        foreach ($desgloseCampeonRaw as $d) {
            $gruposCampeon[$d['IdUsuario'] . '|' . $d['IdEquipo']][] = $d;
        }

        foreach ($pendingBets as &$pb) {
            $clave = $pb['IdUsuario'] . '|' . $pb['IdRelacionado'];
            $grupo = ($pb['Tipo'] === 'Partido' ? ($gruposPartido[$clave] ?? []) : ($gruposCampeon[$clave] ?? []));

            $desglose = [];
            $totalApostado = 0;
            $num = 1;
            foreach ($grupo as $d) {
                $desglose[] = [
                    'Numero' => $num++,
                    'Monto' => floatval($d['MontoApostado']),
                    'Cuota' => floatval($d['CuotaFijada']),
                    'EsEste' => ($d['Id'] === $pb['Id']),
                ];
                $totalApostado += floatval($d['MontoApostado']);
            }
            $pb['Desglose'] = $desglose;
            $pb['TotalApostado'] = $totalApostado;
        }
        unset($pb);

        usort($pendingBets, function ($a, $b) {
            return strtotime($a['Fecha']) - strtotime($b['Fecha']);
        });

        return ['pendingBets' => $pendingBets];
    }
}
