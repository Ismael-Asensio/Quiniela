<?php

// ═══════════════════════════════════════════════════════════════
// ApuestaDashboardRepository — Consultas agregadas para dashboards
// de apuestas (gráficos, resúmenes financieros). Las consultas de
// predicciones/ranking siguen en DashboardRepository.
// ═══════════════════════════════════════════════════════════════

class ApuestaDashboardRepository
{
    /**
     * Apuestas al campeón aprobadas de todos los usuarios, con datos
     * de usuario y equipo. Usado por la vista "todas las apuestas" de
     * tesorería/admin.
     */
    public function findApprovedChampionBets(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT ac.Id, ac.IdEquipo, ac.MontoApostado, ac.CuotaFijada, ac.EstadoApuesta, ac.Fecha,
                    u.NombreUsuario, u.NombreCompleto,
                    e.Nombre AS NombreEquipo, e.BanderaURL
             FROM ApuestasCampeon ac
             INNER JOIN Usuarios u ON ac.IdUsuario = u.Id
             INNER JOIN Equipos e ON ac.IdEquipo = e.Id
             WHERE ac.Aprobado = 1 AND ac.IdCompeticion = ?
             ORDER BY ac.Fecha DESC",
            [$competicionId]
        );
    }

    /**
     * Apuestas de partido aprobadas de todos los usuarios, con datos
     * de usuario, equipos y resultado. Usado por la vista "todas las
     * apuestas" de tesorería/admin.
     */
    public function findApprovedMatchBets(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT ap.Id, ap.IdPartido, ap.MontoApostado, ap.CuotaFijada, ap.EquipoApostado,
                    ap.EstadoApuesta, ap.Ganancia, ap.Fecha,
                    u.NombreUsuario, u.NombreCompleto,
                    eL.Nombre AS NombreLocal, eV.Nombre AS NombreVisitante,
                    p.GolesLocal, p.GolesVisitante, p.Estado AS EstadoPartido, p.Fase
             FROM ApuestasPartidos ap
             INNER JOIN Usuarios u ON ap.IdUsuario = u.Id
             INNER JOIN Partidos p ON ap.IdPartido = p.Id
             LEFT JOIN Equipos eL ON p.IdLocal = eL.Id
             LEFT JOIN Equipos eV ON p.IdVisitante = eV.Id
             WHERE ap.Aprobado = 1 AND ap.IdCompeticion = ?
             ORDER BY ap.Fecha DESC",
            [$competicionId]
        );
    }

    /**
     * Monto total y cantidad de personas por equipo, solo apuestas
     * aprobadas de la competición dada. Usado para el gráfico de
     * apuestas al campeón.
     */
    public function getResumenApuestasCampeonPorEquipo(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT
                e.Nombre AS Equipo,
                SUM(ac.MontoApostado) AS MontoTotal,
                COUNT(ac.IdUsuario) AS CantidadPersonas
             FROM ApuestasCampeon ac
             INNER JOIN Equipos e ON ac.IdEquipo = e.Id
             WHERE ac.Aprobado = 1 AND ac.IdCompeticion = ?
             GROUP BY e.Nombre
             ORDER BY MontoTotal DESC",
            [$competicionId]
        );
    }

    /**
     * Filas crudas de apuestas de partido aprobadas, agrupadas por
     * partido y pronóstico (Local/Empate/Visitante). El ensamblado en
     * un objeto por partido lo hace el Service.
     */
    public function getResumenApuestasPartidoPorPartido(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT
                p.Id AS IdPartido,
                el.Nombre AS Local,
                ev.Nombre AS Visitante,
                ap.EquipoApostado AS Pronostico,
                SUM(ap.MontoApostado) AS MontoTotal,
                COUNT(ap.IdUsuario) AS CantidadPersonas
             FROM ApuestasPartidos ap
             INNER JOIN Partidos p ON ap.IdPartido = p.Id
             INNER JOIN Equipos el ON p.IdLocal = el.Id
             INNER JOIN Equipos ev ON p.IdVisitante = ev.Id
             WHERE ap.Aprobado = 1 AND ap.IdCompeticion = ?
             GROUP BY p.Id, el.Nombre, ev.Nombre, ap.EquipoApostado
             ORDER BY p.Id ASC",
            [$competicionId]
        );
    }

    /**
     * Cantidad de apuestas y monto por equipo, con bandera, solo
     * aprobadas. Para el dashboard de tesorería (distinto shape que
     * getResumenApuestasCampeonPorEquipo, que es para el gráfico de
     * miembro).
     */
    public function getCampeonPorEquipoConDetalle(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT e.Nombre, e.BanderaURL,
                    COUNT(ac.Id) AS TotalApuestas,
                    ISNULL(SUM(ac.MontoApostado), 0) AS MontoTotal
             FROM ApuestasCampeon ac
             INNER JOIN Equipos e ON ac.IdEquipo = e.Id
             WHERE ac.Aprobado = 1 AND ac.IdCompeticion = ?
             GROUP BY e.Nombre, e.BanderaURL
             ORDER BY MontoTotal DESC",
            [$competicionId]
        );
    }

    /**
     * Recaudación diaria combinando apuestas de partido y de campeón
     * aprobadas. Para el gráfico de recaudación del dashboard de
     * tesorería.
     */
    public function getRecaudacionPorDia(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT
                CONVERT(VARCHAR(10), Fecha, 120) AS Dia,
                COUNT(*) AS TotalApuestas,
                ISNULL(SUM(MontoApostado), 0) AS MontoTotal
             FROM (
                 SELECT MontoApostado, Fecha FROM ApuestasPartidos WHERE Aprobado = 1 AND IdCompeticion = ?
                 UNION ALL
                 SELECT MontoApostado, Fecha FROM ApuestasCampeon WHERE Aprobado = 1 AND IdCompeticion = ?
             ) AS Todas
             GROUP BY CONVERT(VARCHAR(10), Fecha, 120)
             ORDER BY Dia ASC",
            [$competicionId, $competicionId]
        );
    }

    /**
     * Resumen agregado de apuestas de partido aprobadas (monto,
     * ganancias pagadas, conteos por estado). Para el dashboard de
     * tesorería.
     */
    public function getResumenApuestasPartidoAprobadas(int $competicionId): ?array
    {
        return Database::fetchOne(
            "SELECT
                COUNT(*) AS TotalApuestas,
                ISNULL(SUM(MontoApostado), 0) AS MontoTotal,
                ISNULL(SUM(CASE WHEN EstadoApuesta = 'Ganada' THEN Ganancia ELSE 0 END), 0) AS GananciasPagadas,
                COUNT(CASE WHEN EstadoApuesta = 'Pendiente' THEN 1 END) AS Pendientes,
                COUNT(CASE WHEN EstadoApuesta = 'Ganada' THEN 1 END) AS Ganadas,
                COUNT(CASE WHEN EstadoApuesta = 'Perdida' THEN 1 END) AS Perdidas
             FROM ApuestasPartidos
             WHERE Aprobado = 1 AND IdCompeticion = ?",
            [$competicionId]
        );
    }

    /**
     * Apuestas de partido pendientes de aprobación, con datos de
     * usuario, equipos y el número de orden de esta apuesta entre
     * todas las del mismo usuario sobre el mismo partido. Para la
     * cola de aprobación de tesorería.
     */
    public function findPendingMatchBets(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT ap.Id, ap.IdUsuario, ap.IdPartido, ap.MontoApostado, ap.CuotaFijada, ap.EquipoApostado, ap.Fecha,
                    u.NombreUsuario, u.NombreCompleto,
                    eL.Nombre AS NombreLocal, eV.Nombre AS NombreVisitante,
                    eL.BanderaURL AS BanderaLocal, eV.BanderaURL AS BanderaVisitante,
                    p.Fase,
                    (SELECT COUNT(*) FROM ApuestasPartidos ap2 WHERE ap2.IdUsuario = ap.IdUsuario AND ap2.IdPartido = ap.IdPartido AND ap2.Id <= ap.Id) AS NumeroApuesta
             FROM ApuestasPartidos ap
             INNER JOIN Usuarios u ON ap.IdUsuario = u.Id
             INNER JOIN Partidos p ON ap.IdPartido = p.Id
             LEFT JOIN Equipos eL ON p.IdLocal = eL.Id
             LEFT JOIN Equipos eV ON p.IdVisitante = eV.Id
             WHERE ap.Aprobado = 0 AND ap.EstadoApuesta = 'Pendiente' AND ap.IdCompeticion = ?
             ORDER BY ap.Fecha ASC",
            [$competicionId]
        );
    }

    /**
     * Apuestas al campeón pendientes de aprobación, con datos de
     * usuario, equipo y número de orden. Para la cola de aprobación
     * de tesorería.
     */
    public function findPendingChampionBets(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT ac.Id, ac.IdUsuario, ac.IdEquipo, ac.MontoApostado, ac.CuotaFijada, ac.Fecha,
                    u.NombreUsuario, u.NombreCompleto,
                    e.Nombre AS NombreEquipo, e.BanderaURL,
                    (SELECT COUNT(*) FROM ApuestasCampeon ac2 WHERE ac2.IdUsuario = ac.IdUsuario AND ac2.IdEquipo = ac.IdEquipo AND ac2.Id <= ac.Id) AS NumeroApuesta
             FROM ApuestasCampeon ac
             INNER JOIN Usuarios u ON ac.IdUsuario = u.Id
             INNER JOIN Equipos e ON ac.IdEquipo = e.Id
             WHERE ac.Aprobado = 0 AND ac.EstadoApuesta = 'Pendiente' AND ac.IdCompeticion = ?
             ORDER BY ac.Fecha ASC",
            [$competicionId]
        );
    }

    /**
     * Todas las apuestas de partido no denegadas de los usuarios y
     * partidos dados, para calcular el desglose (historial de apuestas
     * del mismo usuario sobre el mismo partido) de varias apuestas
     * pendientes en una sola consulta en vez de una por apuesta.
     */
    public function findDesgloseMatchBets(array $idsUsuario, array $idsPartido): array
    {
        if (empty($idsUsuario) || empty($idsPartido)) {
            return [];
        }
        $idsUsuario = array_values(array_unique($idsUsuario));
        $idsPartido = array_values(array_unique($idsPartido));
        $phUsuario = implode(',', array_fill(0, count($idsUsuario), '?'));
        $phPartido = implode(',', array_fill(0, count($idsPartido), '?'));

        return Database::fetchAll(
            "SELECT Id, IdUsuario, IdPartido, MontoApostado, CuotaFijada
             FROM ApuestasPartidos
             WHERE IdUsuario IN ($phUsuario) AND IdPartido IN ($phPartido) AND EstadoApuesta != 'Denegada'
             ORDER BY IdUsuario, IdPartido, Id ASC",
            array_merge($idsUsuario, $idsPartido)
        );
    }

    /**
     * Igual que findDesgloseMatchBets() pero para apuestas al campeón.
     */
    public function findDesgloseChampionBets(array $idsUsuario, array $idsEquipo): array
    {
        if (empty($idsUsuario) || empty($idsEquipo)) {
            return [];
        }
        $idsUsuario = array_values(array_unique($idsUsuario));
        $idsEquipo = array_values(array_unique($idsEquipo));
        $phUsuario = implode(',', array_fill(0, count($idsUsuario), '?'));
        $phEquipo = implode(',', array_fill(0, count($idsEquipo), '?'));

        return Database::fetchAll(
            "SELECT Id, IdUsuario, IdEquipo, MontoApostado, CuotaFijada
             FROM ApuestasCampeon
             WHERE IdUsuario IN ($phUsuario) AND IdEquipo IN ($phEquipo) AND EstadoApuesta != 'Denegada'
             ORDER BY IdUsuario, IdEquipo, Id ASC",
            array_merge($idsUsuario, $idsEquipo)
        );
    }
}
