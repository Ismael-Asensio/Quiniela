<?php

// ═══════════════════════════════════════════════════════════════
// DashboardRepository — Consultas complejas para estadísticas
// ═══════════════════════════════════════════════════════════════

class DashboardRepository
{
    public function getUserStats(int $idUsuario): ?array
    {
        return Database::fetchOne("
            SELECT 
                u.PuntosTotales,
                (SELECT COUNT(*) FROM Predicciones pr INNER JOIN Partidos p ON pr.IdPartido = p.Id WHERE pr.IdUsuario = u.Id AND p.IdCompeticion = ?) AS TotalPredicciones,
                (SELECT COUNT(*) FROM Predicciones pr 
                 INNER JOIN Partidos p ON pr.IdPartido = p.Id 
                 WHERE pr.IdUsuario = u.Id AND p.Estado IN ('FT', 'AET', 'PEN') AND p.IdCompeticion = ?) AS PrediccionesEvaluadas,
                (SELECT COUNT(*) FROM Predicciones pr 
                 INNER JOIN Partidos p ON pr.IdPartido = p.Id 
                 WHERE pr.IdUsuario = u.Id AND p.Estado IN ('FT', 'AET', 'PEN') AND pr.PuntosObtenidos > 0 AND p.IdCompeticion = ?) AS Aciertos,
                (SELECT COUNT(*) FROM Predicciones pr 
                 INNER JOIN Partidos p ON pr.IdPartido = p.Id 
                 WHERE pr.IdUsuario = u.Id AND p.Estado IN ('FT', 'AET', 'PEN') 
                 AND pr.GolesLocal = p.GolesLocal AND pr.GolesVisitante = p.GolesVisitante AND p.IdCompeticion = ?) AS AciertosExactos,
                (SELECT COUNT(*) FROM Partidos WHERE Estado NOT IN ('FT','AET','PEN','CANC','ABD') AND IdCompeticion = ?) AS PartidosRestantes,
                (SELECT COUNT(*) FROM Partidos WHERE IdCompeticion = ?) AS TotalPartidos
            FROM Usuarios u WHERE u.Id = ?
        ", [Config::competicionActiva(), Config::competicionActiva(), Config::competicionActiva(), Config::competicionActiva(), Config::competicionActiva(), Config::competicionActiva(), $idUsuario]);
    }

    public function getPointsPerDay(int $idUsuario): array
    {
        return Database::fetchAll("
            SELECT 
                CONVERT(VARCHAR(10), p.FechaHoraCST, 120) AS Fecha,
                SUM(pr.PuntosObtenidos) AS Puntos,
                COUNT(*) AS Partidos
            FROM Predicciones pr
            INNER JOIN Partidos p ON pr.IdPartido = p.Id
            WHERE pr.IdUsuario = ? AND p.Estado IN ('FT', 'AET', 'PEN') AND p.IdCompeticion = ?
            GROUP BY CONVERT(VARCHAR(10), p.FechaHoraCST, 120)
            ORDER BY Fecha
        ", [$idUsuario, Config::competicionActiva()]);
    }

    public function getDetailedPredictions(int $idUsuario): array
    {
        return Database::fetchAll("
            SELECT 
                p.Id, p.Fase, p.MultiplicadorFase,
                p.GolesLocal AS RealLocal, p.GolesVisitante AS RealVisitante,
                eL.Nombre AS NombreLocal, eL.BanderaURL AS BanderaLocal,
                eV.Nombre AS NombreVisitante, eV.BanderaURL AS BanderaVisitante,
                pr.GolesLocal AS PredLocal, pr.GolesVisitante AS PredVisitante,
                pr.PuntosObtenidos,
                p.FechaHoraCST,
                CASE 
                    WHEN pr.GolesLocal = p.GolesLocal AND pr.GolesVisitante = p.GolesVisitante THEN 'exacto'
                    WHEN pr.PuntosObtenidos > 0 THEN 'resultado'
                    ELSE 'fallo'
                END AS TipoAcierto
            FROM Predicciones pr
            INNER JOIN Partidos p ON pr.IdPartido = p.Id
            LEFT JOIN Equipos eL ON p.IdLocal = eL.Id
            LEFT JOIN Equipos eV ON p.IdVisitante = eV.Id
            WHERE pr.IdUsuario = ? AND p.Estado IN ('FT', 'AET', 'PEN') AND p.IdCompeticion = ?
            ORDER BY p.FechaHoraCST DESC
        ", [$idUsuario, Config::competicionActiva()]);
    }

    public function getRankingGlobal(): array
    {
        return Database::fetchAll("
            SELECT * FROM vw_Ranking 
            ORDER BY PuntosTotales DESC, Aciertos DESC, PorcentajeAcierto DESC, NombreUsuario ASC
        ");
    }

    public function getRankingByTable(int $idTabla): array
    {
        return Database::fetchAll("
            SELECT 
                u.Id,
                u.NombreUsuario,
                u.NombreCompleto,
                COALESCE(pts.PuntosPredicciones, 0) - COALESCE(pen.PuntosRestados, 0) AS PuntosTotales,
                COALESCE(pts.PrediccionesRealizadas, 0) AS PrediccionesRealizadas,
                COALESCE(pts.PartidosEvaluados, 0) AS PartidosEvaluados,
                COALESCE(pts.Aciertos, 0) AS Aciertos,
                COALESCE(pts.AciertosExactos, 0) AS AciertosExactos,
                CASE 
                    WHEN COALESCE(pts.PartidosEvaluados, 0) > 0 
                    THEN CAST(ROUND(
                        (CAST(COALESCE(pts.PuntosPredicciones, 0) AS FLOAT) 
                        / (COALESCE(pts.PartidosEvaluados, 0) * 3)) * 100, 1
                    ) AS DECIMAL(5,1))
                    ELSE 0 
                END AS PorcentajeAcierto
            FROM Usuarios u
            INNER JOIN TablaMiembros tm ON u.Id = tm.IdUsuario
            LEFT JOIN (
                SELECT 
                    pr.IdUsuario,
                    SUM(pr.PuntosObtenidos) AS PuntosPredicciones,
                    COUNT(pr.IdPartido) AS PrediccionesRealizadas,
                    SUM(CASE WHEN p.Estado IN ('FT','AET','PEN') THEN 1 ELSE 0 END) AS PartidosEvaluados,
                    SUM(CASE WHEN pr.PuntosObtenidos > 0 THEN 1 ELSE 0 END) AS Aciertos,
                    SUM(CASE WHEN p.Estado IN ('FT','AET','PEN') 
                             AND pr.GolesLocal = p.GolesLocal 
                             AND pr.GolesVisitante = p.GolesVisitante THEN 1 ELSE 0 END) AS AciertosExactos
                FROM Predicciones pr
                INNER JOIN Partidos p ON pr.IdPartido = p.Id
                WHERE p.IdCompeticion = ?
                GROUP BY pr.IdUsuario
            ) pts ON u.Id = pts.IdUsuario
            LEFT JOIN (
                SELECT IdUsuario, SUM(PuntosRestados) AS PuntosRestados
                FROM Penalizaciones
                WHERE Estado = 'Activa' AND IdTabla = ?
                GROUP BY IdUsuario
            ) pen ON u.Id = pen.IdUsuario
            WHERE tm.IdTabla = ? AND tm.MostrarEnRanking = 1
            ORDER BY PuntosTotales DESC, Aciertos DESC, PorcentajeAcierto DESC, u.NombreUsuario ASC
        ", [Config::competicionActiva(), $idTabla, $idTabla]);
    }

    /**
     * Ranking filtrado por fase: calcula puntos solo de partidos en las fases indicadas.
     * Se usa para tablas con FaseInicio != 'Grupos'.
     * Resta penalizaciones activas de cada usuario.
     *
     * @param int $idTabla
     * @param array $fasesPermitidas Lista de fases a incluir (ej: ['Dieciseisavos','Octavos',...])
     * @return array
     */
    public function getRankingByTableWithPhase(int $idTabla, array $fasesPermitidas): array
    {
        if (empty($fasesPermitidas)) {
            return [];
        }

        // Construir placeholders para las fases
        $placeholders = implode(',', array_fill(0, count($fasesPermitidas), '?'));

        // Parámetros: primero las fases (para subquery), luego idCompeticion, idTabla (penalizaciones), idTabla (filtro principal)
        $params = array_merge($fasesPermitidas, [Config::competicionActiva(), $idTabla, $idTabla]);

        return Database::fetchAll("
            SELECT 
                u.Id,
                u.NombreUsuario,
                u.NombreCompleto,
                COALESCE(pts.PuntosPredicciones, 0) - COALESCE(pen.PuntosRestados, 0) AS PuntosTotales,
                COALESCE(pts.PrediccionesRealizadas, 0) AS PrediccionesRealizadas,
                COALESCE(pts.PartidosEvaluados, 0) AS PartidosEvaluados,
                COALESCE(pts.Aciertos, 0) AS Aciertos,
                COALESCE(pts.AciertosExactos, 0) AS AciertosExactos,
                CASE 
                    WHEN COALESCE(pts.PartidosEvaluados, 0) > 0 
                    THEN CAST(ROUND(
                        (CAST(COALESCE(pts.PuntosPredicciones, 0) AS FLOAT) 
                        / (COALESCE(pts.PartidosEvaluados, 0) * 3)) * 100, 1
                    ) AS DECIMAL(5,1))
                    ELSE 0 
                END AS PorcentajeAcierto
            FROM Usuarios u
            INNER JOIN TablaMiembros tm ON u.Id = tm.IdUsuario
            LEFT JOIN (
                SELECT 
                    pr.IdUsuario,
                    SUM(pr.PuntosObtenidos) AS PuntosPredicciones,
                    COUNT(pr.IdPartido) AS PrediccionesRealizadas,
                    SUM(CASE WHEN p.Estado IN ('FT','AET','PEN') THEN 1 ELSE 0 END) AS PartidosEvaluados,
                    SUM(CASE WHEN pr.PuntosObtenidos > 0 THEN 1 ELSE 0 END) AS Aciertos,
                    SUM(CASE WHEN p.Estado IN ('FT','AET','PEN') 
                             AND pr.GolesLocal = p.GolesLocal 
                             AND pr.GolesVisitante = p.GolesVisitante THEN 1 ELSE 0 END) AS AciertosExactos
                FROM Predicciones pr
                INNER JOIN Partidos p ON pr.IdPartido = p.Id
                WHERE p.Fase IN ($placeholders) AND p.IdCompeticion = ?
                GROUP BY pr.IdUsuario
            ) pts ON u.Id = pts.IdUsuario
            LEFT JOIN (
                SELECT IdUsuario, SUM(PuntosRestados) AS PuntosRestados
                FROM Penalizaciones
                WHERE Estado = 'Activa' AND IdTabla = ?
                GROUP BY IdUsuario
            ) pen ON u.Id = pen.IdUsuario
            WHERE tm.IdTabla = ? AND tm.MostrarEnRanking = 1
            ORDER BY PuntosTotales DESC, Aciertos DESC, PorcentajeAcierto DESC, u.NombreUsuario ASC
        ", $params);
    }

    public function getUserPositionGlobal(int $idUsuario): int
    {
        $row = Database::fetchOne("
            SELECT COUNT(*) + 1 AS Posicion 
            FROM Usuarios 
            WHERE PuntosTotales > (SELECT PuntosTotales FROM Usuarios WHERE Id = ?)
        ", [$idUsuario]);
        return $row ? (int)$row['Posicion'] : 1;
    }

    public function getUserPositionByTable(int $idUsuario, int $idTabla): int
    {
        $row = Database::fetchOne("
            SELECT COUNT(*) + 1 AS Posicion 
            FROM Usuarios u
            INNER JOIN TablaMiembros tm ON u.Id = tm.IdUsuario AND tm.IdTabla = ? AND tm.MostrarEnRanking = 1
            WHERE u.PuntosTotales > (SELECT PuntosTotales FROM Usuarios WHERE Id = ?)
        ", [$idTabla, $idUsuario]);
        return $row ? (int)$row['Posicion'] : 1;
    }

    public function getFirstTableForUser(int $idUsuario): ?int
    {
        $row = Database::fetchOne("
            SELECT TOP 1 tp.Id 
            FROM TablasPuntuacion tp
            INNER JOIN TablaMiembros tm ON tp.Id = tm.IdTabla
            WHERE tm.IdUsuario = ? AND tp.IdCompeticion = ?
            ORDER BY tp.Nombre ASC
        ", [$idUsuario, Config::competicionActiva()]);
        return $row ? (int)$row['Id'] : null;
    }

    public function getFirstTableSystem(): ?int
    {
        $row = Database::fetchOne("SELECT TOP 1 Id FROM TablasPuntuacion WHERE IdCompeticion = ? ORDER BY Nombre ASC", [Config::competicionActiva()]);
        return $row ? (int)$row['Id'] : null;
    }
}
