<?php

// ═══════════════════════════════════════════════════════════════
// CuotaCampeonRepository — Acceso a datos de Cuotas Fijas Campeón
// ═══════════════════════════════════════════════════════════════

class CuotaCampeonRepository
{
    /**
     * Obtener todas las cuotas fijas con datos de equipo
     */
    public function findAll(): array
    {
        return Database::fetchAll(
            "SELECT cf.Id, cf.IdEquipo, cf.CuotaFija, cf.Excluido, cf.FechaActualizacion, cf.ActualizadoPor,
                    e.Nombre AS NombreEquipo, e.BanderaURL, e.CodigoISO
             FROM CuotasCampeonFijas cf
             INNER JOIN Equipos e ON cf.IdEquipo = e.Id
             ORDER BY e.Nombre ASC"
        );
    }

    /**
     * Buscar cuota fija por IdEquipo
     */
    public function findByEquipo(int $idEquipo): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM CuotasCampeonFijas WHERE IdEquipo = ?",
            [$idEquipo]
        );
    }

    /**
     * Insertar o actualizar cuota fija de un equipo (UPSERT)
     */
    public function upsert(int $idEquipo, float $cuotaFija, bool $excluido, ?int $actualizadoPor = null): void
    {
        $existing = $this->findByEquipo($idEquipo);
        $excluidoBit = $excluido ? 1 : 0;

        if ($existing) {
            Database::execute(
                "UPDATE CuotasCampeonFijas 
                 SET CuotaFija = ?, Excluido = ?, FechaActualizacion = GETDATE(), ActualizadoPor = ?
                 WHERE IdEquipo = ?",
                [$cuotaFija, $excluidoBit, $actualizadoPor, $idEquipo]
            );
        } else {
            Database::execute(
                "INSERT INTO CuotasCampeonFijas (IdEquipo, CuotaFija, Excluido, ActualizadoPor)
                 VALUES (?, ?, ?, ?)",
                [$idEquipo, $cuotaFija, $excluidoBit, $actualizadoPor]
            );
        }
    }

    /**
     * Eliminar cuota fija de un equipo (volver a cálculo dinámico)
     */
    public function delete(int $idEquipo): int
    {
        return Database::execute(
            "DELETE FROM CuotasCampeonFijas WHERE IdEquipo = ?",
            [$idEquipo]
        );
    }

    /**
     * Obtener IDs de equipos marcados como excluidos
     */
    public function getExcluidoIds(): array
    {
        $rows = Database::fetchAll(
            "SELECT IdEquipo FROM CuotasCampeonFijas WHERE Excluido = 1"
        );
        return array_map(function($r) { return (int)$r['IdEquipo']; }, $rows);
    }

    /**
     * Obtener mapa de cuotas fijas: IdEquipo => CuotaFija
     */
    public function getCuotasFijasMap(): array
    {
        $rows = Database::fetchAll(
            "SELECT IdEquipo, CuotaFija FROM CuotasCampeonFijas WHERE Excluido = 0"
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['IdEquipo']] = (float)$row['CuotaFija'];
        }
        return $map;
    }
}
