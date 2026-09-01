<?php

// ═══════════════════════════════════════════════════════════════
// CompeticionRepository — Acceso a datos de Competiciones
// ═══════════════════════════════════════════════════════════════

class CompeticionRepository
{
    public function findAll(): array
    {
        return Database::fetchAll(
            "SELECT Id, Nombre, Activa, CONVERT(VARCHAR(10), FechaInicio, 120) AS FechaInicio FROM Competiciones ORDER BY Id ASC"
        );
    }
}
