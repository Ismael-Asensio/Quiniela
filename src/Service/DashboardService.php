<?php

// ═══════════════════════════════════════════════════════════════
// DashboardService — Lógica de Dashboards (Estadísticas)
// ═══════════════════════════════════════════════════════════════

class DashboardService
{
    private $dashboardRepo;

    public function __construct()
    {
        $this->dashboardRepo = new DashboardRepository();
    }

    public function getUserDashboard(int $idUsuario, int $idTablaRequerida = 0, bool $esAdmin = false): array
    {
        $general = $this->dashboardRepo->getUserStats($idUsuario);
        $porDia = $this->dashboardRepo->getPointsPerDay($idUsuario);
        $porPartidoRaw = $this->dashboardRepo->getDetailedPredictions($idUsuario);

        // Formatear fechas
        $porPartido = [];
        foreach ($porPartidoRaw as $row) {
            if ($row['FechaHoraCST'] instanceof \DateTime) {
                $row['FechaHoraCST'] = $row['FechaHoraCST']->format('Y-m-d H:i:s');
            }
            $porPartido[] = $row;
        }

        // Calcular posición en el ranking
        $posicion = '-';
        $idTabla = $idTablaRequerida;

        if ($idTabla <= 0) {
            if ($esAdmin) {
                $idTabla = $this->dashboardRepo->getFirstTableSystem();
            } else {
                $idTabla = $this->dashboardRepo->getFirstTableForUser($idUsuario);
            }
        }

        if ($idTabla > 0) {
            $posicion = $this->dashboardRepo->getUserPositionByTable($idUsuario, $idTabla);
        }

        return [
            'general' => $general,
            'porDia' => $porDia,
            'porPartido' => $porPartido,
            'posicionRanking' => $posicion
        ];
    }
}
