<?php

// ═══════════════════════════════════════════════════════════════
// RankingService — Lógica de Tablas de Posiciones
// ═══════════════════════════════════════════════════════════════

class RankingService
{
    private $dashboardRepo;

    public function __construct()
    {
        $this->dashboardRepo = new DashboardRepository();
    }

    public function getRanking(int $idTabla = 0, int $idUsuario = 0, bool $esAdmin = false): array
    {
        $tablaRepo = new TablaRepository();
        $tablasDisponibles = [];
        $tablaActual = null;

        if ($esAdmin) {
            $tablasDisponibles = $tablaRepo->findAll();
        } else if ($idUsuario > 0) {
            $tablasDisponibles = $tablaRepo->findByUser($idUsuario);
        }

        if ($idTabla === 0 && count($tablasDisponibles) > 0) {
            $idTabla = $tablasDisponibles[0]['Id'];
        }

        // Si no se especifica y no es admin, intentar primera tabla
        if ($idTabla <= 0) {
            $idTabla = $this->dashboardRepo->getFirstTableForUser($idUsuario);
            if (!$idTabla) {
                return ['success' => false, 'error' => 'No hay tablas disponibles', 'code' => 404, 'sinTablas' => true];
            }
        }
        // Ranking por tabla específica
        $tabla = $tablaRepo->findById($idTabla);

        if (!$tabla) {
            return ['success' => false, 'error' => 'Tabla no encontrada', 'code' => 404];
        }

        // Determinar si la tabla filtra por fase
        $faseInicio = $tabla['FaseInicio'] ?? 'Grupos';

        if ($faseInicio !== 'Grupos') {
            // Ranking filtrado: solo puntos desde la fase indicada en adelante
            $fasesPermitidas = Config::getFasesDesde($faseInicio);
            $rankingRaw = $this->dashboardRepo->getRankingByTableWithPhase($idTabla, $fasesPermitidas);
        } else {
            // Ranking completo: usa la vista vw_Ranking (comportamiento original)
            $rankingRaw = $this->dashboardRepo->getRankingByTable($idTabla);
        }

        $nombreTabla = $tabla['Nombre'];
        $descripcion = $tabla['Descripcion'];
        $tablaActual = $tabla;


        // Formatear numéricos
        $ranking = [];
        $pos = 1;
        foreach ($rankingRaw as $row) {
            $row['Posicion'] = $pos++;
            $row['PuntosTotales'] = (int)$row['PuntosTotales'];
            $row['PrediccionesRealizadas'] = (int)$row['PrediccionesRealizadas'];
            $row['Aciertos'] = (int)$row['Aciertos'];
            $row['AciertosExactos'] = (int)($row['AciertosExactos'] ?? 0);
            $row['PorcentajeAcierto'] = (float)$row['PorcentajeAcierto'];
            $ranking[] = $row;
        }

        return [
            'success' => true,
            'nombreTabla' => $nombreTabla,
            'descripcionTabla' => $descripcion,
            'faseInicio' => $faseInicio,
            'ranking' => $ranking,
            'tablasDisponibles' => $tablasDisponibles,
            'tablaActual' => $tablaActual
        ];
    }
}
