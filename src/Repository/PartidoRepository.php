<?php

// ═══════════════════════════════════════════════════════════════
// PartidoRepository — Acceso a datos de Partidos
// ═══════════════════════════════════════════════════════════════

class PartidoRepository
{
    public function findById(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM Partidos WHERE Id = ?",
            [$id]
        );
    }

    /**
     * Igual que findById() pero toma un bloqueo de escritura sobre la fila
     * (UPDLOCK, HOLDLOCK). Debe llamarse dentro de una transacción activa.
     * Uso: revalidar que un partido sigue "apostable" justo antes de
     * insertar una apuesta, cerrando la ventana entre leer el estado y
     * escribir la apuesta (ver ApuestaService::apostarPartido).
     */
    public function findByIdForUpdate(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM Partidos WITH (UPDLOCK, HOLDLOCK) WHERE Id = ?",
            [$id]
        );
    }

    public function findByApiId(int $apiId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM Partidos WHERE IdApiExterno = ?",
            [$apiId]
        );
    }

    public function findByLocalVisitante(int $idLocal, int $idVisitante): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM Partidos WHERE IdLocal = ? AND IdVisitante = ? AND IdCompeticion = ?",
            [$idLocal, $idVisitante, Config::competicionActiva()]
        );
    }

    public function updateApiId(int $id, int $apiId): void
    {
        Database::execute("UPDATE Partidos SET IdApiExterno = ? WHERE Id = ?", [$apiId, $id]);
    }

    public function findByFechaHoraCST(string $fechaCST): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM Partidos WHERE FechaHoraCST = ? AND IdCompeticion = ?",
            [$fechaCST, Config::competicionActiva()]
        );
    }

    public function updateTeamsAndApi(int $id, int $idLocal, int $idVisitante, int $apiId): void
    {
        Database::execute(
            "UPDATE Partidos SET IdLocal = ?, IdVisitante = ?, IdApiExterno = ? WHERE Id = ?",
            [$idLocal, $idVisitante, $apiId, $id]
        );
    }

    public function findAllWithTeams(?string $fecha = null, ?string $fase = null, ?string $estado = null): array
    {
        $sql = "SELECT 
                    p.*,
                    eL.Id AS IdEquipoLocal, eL.Nombre AS NombreLocal, eL.CodigoISO AS ISOLocal, eL.BanderaURL AS BanderaLocal,
                    eV.Id AS IdEquipoVisitante, eV.Nombre AS NombreVisitante, eV.CodigoISO AS ISOVisitante, eV.BanderaURL AS BanderaVisitante
                FROM Partidos p
                LEFT JOIN Equipos eL ON p.IdLocal = eL.Id
                LEFT JOIN Equipos eV ON p.IdVisitante = eV.Id";

        $params = [Config::competicionActiva()];
        $where = ["p.IdCompeticion = ?"];

        if ($fecha) {
            $where[] = "CONVERT(DATE, p.FechaHoraCST) = ?";
            $params[] = $fecha;
        }

        if ($fase) {
            $where[] = "p.Fase = ?";
            $params[] = $fase;
        }

        if ($estado) {
            $where[] = "p.Estado = ?";
            $params[] = $estado;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY p.FechaHoraCST ASC, p.Id ASC";

        return Database::fetchAll($sql, $params);
    }

    public function create(array $data): int
    {
        Database::execute(
            "INSERT INTO Partidos (IdApiExterno, IdLocal, IdVisitante, FechaHoraCST, GolesLocal, GolesVisitante, Estado, Fase, MultiplicadorFase, Jornada, Sede, IdCompeticion) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['IdApiExterno'] ?? null,
                $data['IdLocal'] ?? null,
                $data['IdVisitante'] ?? null,
                $data['FechaHoraCST'],
                $data['GolesLocal'] ?? null,
                $data['GolesVisitante'] ?? null,
                $data['Estado'] ?? 'NS',
                $data['Fase'] ?? 'Grupos',
                $data['MultiplicadorFase'] ?? 1,
                $data['Jornada'] ?? null,
                $data['Sede'] ?? null,
                Config::competicionActiva()
            ]
        );

        $row = Database::fetchOne("SELECT Id FROM Partidos WHERE IdApiExterno = ? OR FechaHoraCST = ? ORDER BY Id DESC", [$data['IdApiExterno'] ?? null, $data['FechaHoraCST']]);
        return $row ? (int)$row['Id'] : 0;
    }

    public function updateMatchData(int $id, string $fechaCST, ?int $golesLocal, ?int $golesVisitante, string $estado): int
    {
        return Database::execute(
            "UPDATE Partidos SET FechaHoraCST = ?, GolesLocal = ?, GolesVisitante = ?, Estado = ? WHERE Id = ?",
            [$fechaCST, $golesLocal, $golesVisitante, $estado, $id]
        );
    }

    public function updateOdds(int $id, float $cuotaLocal, float $cuotaEmpate, float $cuotaVisitante): int
    {
        return Database::execute(
            "UPDATE Partidos SET CuotaLocal = ?, CuotaEmpate = ?, CuotaVisitante = ?, UltimaActualizacionCuotas = GETDATE() WHERE Id = ?",
            [$cuotaLocal, $cuotaEmpate, $cuotaVisitante, $id]
        );
    }

    /**
     * Listado minimo de partidos (con nombres de equipo) para el selector
     * de la vista de predicciones de administrador.
     */
    public function findAllForAdminOptions(): array
    {
        return Database::fetchAll(
            "SELECT p.Id, p.Fase, p.FechaHoraCST, p.Estado,
                    eL.Nombre AS NombreLocal, eV.Nombre AS NombreVisitante
             FROM Partidos p
             LEFT JOIN Equipos eL ON p.IdLocal = eL.Id
             LEFT JOIN Equipos eV ON p.IdVisitante = eV.Id
             WHERE p.IdCompeticion = ?
             ORDER BY p.FechaHoraCST ASC",
             [Config::competicionActiva()]
        );
    }

    /**
     * Partidos finalizados en fases eliminatorias con marcador definido,
     * de la competición dada. Para calcular equipos eliminados del
     * torneo (ver PartidoService::getEquiposEliminados()).
     */
    public function findFinalizadosEliminatoriosConGoles(int $competicionId): array
    {
        $fases = ['Dieciseisavos', 'Octavos', 'Cuartos', 'Semifinales', 'Tercer Lugar', 'Final'];
        $placeholders = implode(',', array_fill(0, count($fases), '?'));

        return Database::fetchAll(
            "SELECT IdLocal, IdVisitante, GolesLocal, GolesVisitante
             FROM Partidos
             WHERE Estado IN ('FT', 'AET', 'PEN')
               AND Fase IN ($placeholders)
               AND GolesLocal IS NOT NULL AND GolesVisitante IS NOT NULL
               AND IdCompeticion = ?",
            array_merge($fases, [$competicionId])
        );
    }

    /**
     * Partidos jugados (FT) vs. totales, SIN filtrar por competición
     * (mismo comportamiento que el getChampionOdds() legacy — ver
     * Fase 6 del plan de migración para el arreglo de coherencia).
     * Usado para estimar el "avance del torneo" en el cálculo de
     * cuotas al campeón.
     */
    public function getProgresoTorneo(): ?array
    {
        return Database::fetchOne(
            "SELECT COUNT(*) AS Jugados, (SELECT COUNT(*) FROM Partidos) AS Totales FROM Partidos WHERE Estado = 'FT'"
        );
    }

    public function getFasesUnicas(): array
    {
        return Database::fetchAll(
            "SELECT DISTINCT Fase, 
                CASE Fase 
                    WHEN 'Grupos' THEN 1 
                    WHEN 'Dieciseisavos' THEN 2 
                    WHEN 'Octavos' THEN 3 
                    WHEN 'Cuartos' THEN 4 
                    WHEN 'Semifinales' THEN 5 
                    WHEN 'Tercer Lugar' THEN 6 
                    WHEN 'Final' THEN 7 
                    ELSE 99
                END AS Orden 
            FROM Partidos 
            WHERE IdCompeticion = ?
            ORDER BY Orden",
            [Config::competicionActiva()]
        );
    }

    public function getFechasUnicas(): array
    {
        return Database::fetchAll(
            "SELECT DISTINCT CONVERT(VARCHAR(10), FechaHoraCST, 120) AS Fecha FROM Partidos WHERE IdCompeticion = ? ORDER BY Fecha",
            [Config::competicionActiva()]
        );
    }
}
