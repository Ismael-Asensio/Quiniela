<?php

// ═══════════════════════════════════════════════════════════════
// MembresiaRepository — Acceso a datos de Membresías
// ═══════════════════════════════════════════════════════════════

class MembresiaRepository
{
    public function findById(int $id): ?array
    {
        return Database::fetchOne("SELECT * FROM Membresias WHERE Id = ?", [$id]);
    }

    public function findByUser(int $idUsuario): array
    {
        return Database::fetchAll(
            "SELECT * FROM Membresias WHERE IdUsuario = ? ORDER BY FechaPago DESC",
            [$idUsuario]
        );
    }

    public function create(int $idUsuario, float $montoPagado, string $estado = 'Activa'): int
    {
        Database::execute(
            "INSERT INTO Membresias (IdUsuario, MontoPagado, Estado) VALUES (?, ?, ?)",
            [$idUsuario, $montoPagado, $estado]
        );

        $row = Database::fetchOne("SELECT SCOPE_IDENTITY() AS Id");
        return $row ? (int)$row['Id'] : 0;
    }

    public function updateStatus(int $id, string $estado): int
    {
        return Database::execute(
            "UPDATE Membresias SET Estado = ? WHERE Id = ?",
            [$estado, $id]
        );
    }

    public function delete(int $id): int
    {
        return Database::execute("DELETE FROM Membresias WHERE Id = ?", [$id]);
    }

    /**
     * Resumen agregado de membresías de la competición (total, monto
     * recaudado, activas/inactivas). Para el dashboard de tesorería.
     */
    public function getResumen(int $competicionId): ?array
    {
        return Database::fetchOne(
            "SELECT
                COUNT(*) AS TotalMembresias,
                ISNULL(SUM(MontoPagado), 0) AS RecaudacionTotal,
                COUNT(CASE WHEN Estado = 'Activa' THEN 1 END) AS Activas,
                COUNT(CASE WHEN Estado != 'Activa' THEN 1 END) AS Inactivas
             FROM Membresias
             WHERE IdCompeticion = ?",
            [$competicionId]
        );
    }

    /**
     * Membresías de la competición con nombre de usuario, más recientes
     * primero. Para el dashboard de tesorería.
     */
    public function getRecientes(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT m.Id, u.NombreUsuario, u.NombreCompleto, m.MontoPagado, m.FechaPago, m.Estado
             FROM Membresias m
             INNER JOIN Usuarios u ON m.IdUsuario = u.Id
             WHERE m.IdCompeticion = ?
             ORDER BY m.FechaPago DESC",
            [$competicionId]
        );
    }

    /**
     * Cantidad y monto de membresías agrupadas por día de pago. Para
     * el gráfico del dashboard de tesorería.
     */
    public function getPorDia(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT
                CONVERT(VARCHAR(10), FechaPago, 120) AS Dia,
                COUNT(*) AS Cantidad,
                SUM(MontoPagado) AS MontoTotal
             FROM Membresias
             WHERE IdCompeticion = ?
             GROUP BY CONVERT(VARCHAR(10), FechaPago, 120)
             ORDER BY Dia ASC",
            [$competicionId]
        );
    }
}
