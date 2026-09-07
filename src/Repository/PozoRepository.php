<?php

// ═══════════════════════════════════════════════════════════════
// PozoRepository — Acceso a datos para el reparto de premios (pozo)
// ═══════════════════════════════════════════════════════════════

class PozoRepository
{
    /**
     * Monto y ganancia comprometida por partido y opción (Local/Empate/
     * Visitante), solo apuestas aprobadas de la competición dada.
     */
    public function findPozoPartidos(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT IdPartido, EquipoApostado, SUM(MontoApostado) as Monto, SUM(MontoApostado * CuotaFijada) as Ganancia
             FROM ApuestasPartidos
             WHERE Aprobado = 1 AND IdCompeticion = ?
             GROUP BY IdPartido, EquipoApostado",
            [$competicionId]
        );
    }

    /**
     * Monto y ganancia comprometida por equipo campeón, solo apuestas
     * aprobadas de la competición dada.
     */
    public function findPozoCampeon(int $competicionId): array
    {
        return Database::fetchAll(
            "SELECT IdEquipo, SUM(MontoApostado) as Monto, SUM(MontoApostado * CuotaFijada) as Ganancia
             FROM ApuestasCampeon
             WHERE Aprobado = 1 AND IdCompeticion = ?
             GROUP BY IdEquipo",
            [$competicionId]
        );
    }
}
