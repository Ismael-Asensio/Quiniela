<?php

// ═══════════════════════════════════════════════════════════════
// PrediccionRepository — Acceso a datos de Predicciones
// ═══════════════════════════════════════════════════════════════

class PrediccionRepository
{
    public function findByUserAndMatch(int $idUsuario, int $idPartido): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM Predicciones WHERE IdUsuario = ? AND IdPartido = ?",
            [$idUsuario, $idPartido]
        );
    }

    public function findByMatch(int $idPartido): array
    {
        return Database::fetchAll(
            "SELECT pr.*, u.NombreUsuario, u.NombreCompleto 
             FROM Predicciones pr
             INNER JOIN Usuarios u ON pr.IdUsuario = u.Id
             WHERE pr.IdPartido = ?",
            [$idPartido]
        );
    }

    public function findAllPaginated(int $limit, int $offset, string $searchUser = '', string $searchMatch = '', int $idCompeticion = 0): array
    {
        $params = [];
        $whereConditions = [];

        if ($idCompeticion > 0) {
            $whereConditions[] = "p.IdCompeticion = ?";
            $params[] = $idCompeticion;
        }

        if (!empty($searchUser)) {
            $wordsU = array_filter(explode(' ', $searchUser));
            if (count($wordsU) > 0) {
                $userConditions = [];
                foreach ($wordsU as $word) {
                    $userConditions[] = "CONCAT(u.NombreUsuario, ' ', u.NombreCompleto) COLLATE Latin1_General_CI_AI LIKE ?";
                    $params[] = "%" . trim($word) . "%";
                }
                $whereConditions[] = "(" . implode(" AND ", $userConditions) . ")";
            }
        }

        if (!empty($searchMatch)) {
            // Reemplazar 'vs' y guiones por espacios para buscar cada equipo de forma independiente
            $cleanSearch = str_replace(['vs', '-'], ' ', $searchMatch);
            $wordsM = array_filter(explode(' ', $cleanSearch));
            
            if (count($wordsM) > 0) {
                $matchConditions = [];
                foreach ($wordsM as $word) {
                    $matchConditions[] = "CONCAT(el.Nombre, ' ', ev.Nombre, ' ', p.Fase, ' ', p.Estado) COLLATE Latin1_General_CI_AI LIKE ?";
                    $params[] = "%" . trim($word) . "%";
                }
                // Todas las palabras de búsqueda deben estar en el texto (ej: "Mexico" Y "Polonia")
                $whereConditions[] = "(" . implode(" AND ", $matchConditions) . ")";
            }
        }

        $where = "";
        if (count($whereConditions) > 0) {
            $where = "WHERE " . implode(" AND ", $whereConditions);
        }

        $sqlParams = $params;
        $sqlParams[] = $offset;
        $sqlParams[] = $limit;

        $sql = "SELECT pr.*, u.NombreUsuario, u.NombreCompleto, el.Nombre AS NombreLocal, ev.Nombre AS NombreVisitante, p.Estado, p.FechaHoraCST, p.Fase,
                       el.BanderaURL AS BanderaLocal, ev.BanderaURL AS BanderaVisitante, p.GolesLocal AS RealLocal, p.GolesVisitante AS RealVisitante,
                       CONCAT(el.Nombre, ' vs ', ev.Nombre) AS Partido
                FROM Predicciones pr
                INNER JOIN Usuarios u ON pr.IdUsuario = u.Id
                INNER JOIN Partidos p ON pr.IdPartido = p.Id
                INNER JOIN Equipos el ON p.IdLocal = el.Id
                INNER JOIN Equipos ev ON p.IdVisitante = ev.Id
                $where
                ORDER BY pr.FechaPrediccion DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

        $records = Database::fetchAll($sql, $sqlParams);

        // Convertir DateTime a string para que el JSON frontend pueda leerlo correctamente
        foreach ($records as &$row) {
            if ($row['FechaPrediccion'] instanceof \DateTime) {
                $row['FechaPrediccion'] = $row['FechaPrediccion']->format('Y-m-d H:i:s');
            }
            if ($row['FechaHoraCST'] instanceof \DateTime) {
                $row['FechaHoraCST'] = $row['FechaHoraCST']->format('Y-m-d H:i:s');
            }
        }

        // Count total for pagination
        $countSql = "SELECT COUNT(*) as Total
                     FROM Predicciones pr
                     INNER JOIN Usuarios u ON pr.IdUsuario = u.Id
                     INNER JOIN Partidos p ON pr.IdPartido = p.Id
                     INNER JOIN Equipos el ON p.IdLocal = el.Id
                     INNER JOIN Equipos ev ON p.IdVisitante = ev.Id
                     $where";
        
        $totalRow = Database::fetchOne($countSql, $params);
        $totalRecords = $totalRow ? (int)$totalRow['Total'] : 0;

        return [
            'records' => $records,
            'total' => $totalRecords
        ];
    }

    public function findByUser(int $idUsuario): array
    {
        return Database::fetchAll(
            "SELECT * FROM Predicciones WHERE IdUsuario = ?",
            [$idUsuario]
        );
    }

    public function save(int $idUsuario, int $idPartido, int $golesLocal, int $golesVisitante): int
    {
        $existing = $this->findByUserAndMatch($idUsuario, $idPartido);

        if ($existing) {
            return Database::execute(
                "UPDATE Predicciones 
                 SET GolesLocal = ?, GolesVisitante = ?, FechaPrediccion = GETDATE() 
                 WHERE IdUsuario = ? AND IdPartido = ?",
                [$golesLocal, $golesVisitante, $idUsuario, $idPartido]
            );
        } else {
            return Database::execute(
                "INSERT INTO Predicciones (IdUsuario, IdPartido, GolesLocal, GolesVisitante) 
                 VALUES (?, ?, ?, ?)",
                [$idUsuario, $idPartido, $golesLocal, $golesVisitante]
            );
        }
    }

    public function updatePoints(int $idUsuario, int $idPartido, int $puntos): int
    {
        return Database::execute(
            "UPDATE Predicciones SET PuntosObtenidos = ? WHERE IdUsuario = ? AND IdPartido = ?",
            [$puntos, $idUsuario, $idPartido]
        );
    }

    public function updatePointsByMatch(int $idPartido, callable $calculator): int
    {
        // 1. Obtener resultado real
        $partido = Database::fetchOne("SELECT GolesLocal, GolesVisitante, MultiplicadorFase FROM Partidos WHERE Id = ?", [$idPartido]);
        if (!$partido) return 0;

        // Todo o nada: si el proceso muere a mitad del bucle (timeout,
        // corte de red con el SQL Server remoto), no debe quedar solo
        // una parte de las predicciones de este partido recalculadas.
        return Database::transaction(function () use ($idPartido, $partido, $calculator) {
            // 2. Obtener todas las predicciones para este partido
            $predicciones = $this->findByMatch($idPartido);
            $updated = 0;

            // 3. Recalcular y actualizar cada una
            foreach ($predicciones as $pred) {
                $puntos = $calculator(
                    $pred['GolesLocal'], $pred['GolesVisitante'],
                    $partido['GolesLocal'], $partido['GolesVisitante'],
                    $partido['MultiplicadorFase']
                );

                $this->updatePoints($pred['IdUsuario'], $idPartido, $puntos);
                $updated++;
            }

            return $updated;
        });
    }

    public function clearPointsByMatch(int $idPartido): int
    {
        return Database::execute("UPDATE Predicciones SET PuntosObtenidos = 0 WHERE IdPartido = ?", [$idPartido]);
    }
}
