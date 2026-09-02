<?php

// ═══════════════════════════════════════════════════════════════
// ApuestaRepository — Acceso a datos de Apuestas (Partidos y Campeón)
// ═══════════════════════════════════════════════════════════════

class ApuestaRepository
{
    // ─── Apuestas Partidos ───

    public function findMatchBetById(int $id): ?array
    {
        return Database::fetchOne("SELECT * FROM ApuestasPartidos WHERE Id = ?", [$id]);
    }

    /**
     * Apuestas de partido del usuario con detalle de equipos y resultado,
     * para el dashboard personal del Miembro.
     */
    public function findMatchBetsByUser(int $idUsuario, int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT ap.Id, ap.IdPartido, ap.MontoApostado, ap.CuotaFijada, ap.EquipoApostado,
                    ap.EstadoApuesta, ap.Ganancia, ap.Fecha, ap.Aprobado,
                    eL.Nombre AS NombreLocal, eV.Nombre AS NombreVisitante,
                    p.GolesLocal, p.GolesVisitante, p.Estado AS EstadoPartido, p.Fase
             FROM ApuestasPartidos ap
             INNER JOIN Partidos p ON ap.IdPartido = p.Id
             LEFT JOIN Equipos eL ON p.IdLocal = eL.Id
             LEFT JOIN Equipos eV ON p.IdVisitante = eV.Id
             WHERE ap.IdUsuario = ? AND ap.IdCompeticion = ?
             ORDER BY ap.Fecha DESC",
            [$idUsuario, $competicionId]
        );
    }

    /**
     * Resumen agregado de apuestas de partido del usuario (montos y
     * conteos por estado), para el dashboard personal del Miembro.
     */
    public function getResumenApuestasPartido(int $idUsuario, int $competicionId): ?array
    {
        return Database::fetchOne(
            "SELECT
                ISNULL(SUM(CASE WHEN EstadoApuesta != 'Denegada' THEN MontoApostado ELSE 0 END), 0) AS TotalApostado,
                ISNULL(SUM(CASE WHEN EstadoApuesta = 'Ganada' THEN Ganancia ELSE 0 END), 0) AS TotalGanado,
                ISNULL(SUM(CASE WHEN EstadoApuesta = 'Perdida' THEN MontoApostado ELSE 0 END), 0) AS TotalPerdido,
                COUNT(CASE WHEN EstadoApuesta != 'Denegada' THEN 1 END) AS TotalApuestas,
                COUNT(CASE WHEN EstadoApuesta = 'Pendiente' THEN 1 END) AS Pendientes,
                COUNT(CASE WHEN EstadoApuesta = 'Ganada' THEN 1 END) AS Ganadas,
                COUNT(CASE WHEN EstadoApuesta = 'Perdida' THEN 1 END) AS Perdidas
             FROM ApuestasPartidos WHERE IdUsuario = ? AND IdCompeticion = ?",
            [$idUsuario, $competicionId]
        );
    }

    public function createMatchBet(int $idUsuario, int $idPartido, float $monto, float $cuota, string $equipoApostado): int
    {
        Database::execute(
            "INSERT INTO ApuestasPartidos (IdUsuario, IdPartido, MontoApostado, CuotaFijada, EquipoApostado, EstadoApuesta, IdCompeticion) 
             VALUES (?, ?, ?, ?, ?, 'Pendiente', ?)",
            [$idUsuario, $idPartido, $monto, $cuota, $equipoApostado, Config::competicionActiva()]
        );
        $row = Database::fetchOne("SELECT SCOPE_IDENTITY() AS Id");
        return $row ? (int)$row['Id'] : 0;
    }

    /**
     * Transiciona el estado de una apuesta que esté Pendiente. Idempotente:
     * retorna 0 filas afectadas si ya no estaba Pendiente (doble clic,
     * dos tesoreros procesando la misma apuesta a la vez, etc.).
     */
    public function updateMatchBetStatus(int $id, string $estado, ?float $ganancia = null): int
    {
        if ($ganancia !== null) {
            return Database::execute(
                "UPDATE ApuestasPartidos SET EstadoApuesta = ?, Ganancia = ? WHERE Id = ? AND EstadoApuesta = 'Pendiente'",
                [$estado, $ganancia, $id]
            );
        } else {
            return Database::execute(
                "UPDATE ApuestasPartidos SET EstadoApuesta = ? WHERE Id = ? AND EstadoApuesta = 'Pendiente'",
                [$estado, $id]
            );
        }
    }

    /**
     * Aprueba una apuesta pendiente de aprobación. Idempotente: retorna
     * 0 filas afectadas si ya estaba aprobada.
     */
    public function approveMatchBet(int $id): int
    {
        return Database::execute(
            "UPDATE ApuestasPartidos SET Aprobado = 1 WHERE Id = ? AND Aprobado = 0",
            [$id]
        );
    }

    // ─── Apuestas Campeón ───

    public function findChampionBetById(int $id): ?array
    {
        return Database::fetchOne("SELECT * FROM ApuestasCampeon WHERE Id = ?", [$id]);
    }

    /**
     * Monto total apostado (no denegado) a un equipo específico como
     * campeón. Para el cálculo de cuota dinámica por "dinero de la
     * multitud" (crowd money).
     */
    public function getMontoApostadoPorEquipoCampeon(int $idEquipo): float
    {
        $row = Database::fetchOne(
            "SELECT ISNULL(SUM(MontoApostado), 0) AS MontoEquipo FROM ApuestasCampeon WHERE IdEquipo = ? AND EstadoApuesta != 'Denegada'",
            [$idEquipo]
        );
        return (float)($row['MontoEquipo'] ?? 0);
    }

    /**
     * Monto total apostado (no denegado) a todos los equipos como
     * campeón, sin filtrar por equipo. Para el cálculo de cuota
     * dinámica por "dinero de la multitud".
     */
    public function getMontoApostadoGlobalCampeon(): float
    {
        $row = Database::fetchOne(
            "SELECT ISNULL(SUM(MontoApostado), 0) AS MontoGlobal FROM ApuestasCampeon WHERE EstadoApuesta != 'Denegada'"
        );
        return (float)($row['MontoGlobal'] ?? 0);
    }

    /**
     * Apuestas al campeón del usuario con nombre y bandera del equipo,
     * para el dashboard personal del Miembro.
     */
    public function findChampionBetsByUser(int $idUsuario, int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT ac.Id, ac.IdEquipo, ac.MontoApostado, ac.CuotaFijada, ac.EstadoApuesta, ac.Fecha, ac.Aprobado,
                    e.Nombre AS NombreEquipo, e.BanderaURL
             FROM ApuestasCampeon ac
             INNER JOIN Equipos e ON ac.IdEquipo = e.Id
             WHERE ac.IdUsuario = ? AND ac.IdCompeticion = ?
             ORDER BY ac.Fecha DESC",
            [$idUsuario, $competicionId]
        );
    }

    public function createChampionBet(int $idUsuario, int $idEquipo, float $monto, float $cuota): int
    {
        Database::execute(
            "INSERT INTO ApuestasCampeon (IdUsuario, IdEquipo, MontoApostado, CuotaFijada, EstadoApuesta, IdCompeticion) 
             VALUES (?, ?, ?, ?, 'Pendiente', ?)",
            [$idUsuario, $idEquipo, $monto, $cuota, Config::competicionActiva()]
        );
        $row = Database::fetchOne("SELECT SCOPE_IDENTITY() AS Id");
        return $row ? (int)$row['Id'] : 0;
    }

    /**
     * Transiciona el estado de una apuesta al campeón que esté Pendiente.
     * Idempotente: 0 filas afectadas si ya no estaba Pendiente.
     */
    public function updateChampionBetStatus(int $id, string $estado): int
    {
        return Database::execute(
            "UPDATE ApuestasCampeon SET EstadoApuesta = ? WHERE Id = ? AND EstadoApuesta = 'Pendiente'",
            [$estado, $id]
        );
    }

    /**
     * Aprueba una apuesta al campeón pendiente. Idempotente: 0 filas
     * afectadas si ya estaba aprobada.
     */
    public function approveChampionBet(int $id): int
    {
        return Database::execute(
            "UPDATE ApuestasCampeon SET Aprobado = 1 WHERE Id = ? AND Aprobado = 0",
            [$id]
        );
    }

}
