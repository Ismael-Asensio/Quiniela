<?php

// ═══════════════════════════════════════════════════════════════
// PenalizacionRepository — Acceso a datos de Penalizaciones
// ═══════════════════════════════════════════════════════════════

class PenalizacionRepository
{
    public function findAll(?string $estado = null, ?string $busqueda = null): array
    {
        $sql = "SELECT p.*, u.NombreUsuario, u.NombreCompleto,
                       admin.NombreUsuario AS CreadaPorUsuario,
                       modAdmin.NombreUsuario AS ModificadaPorUsuario,
                       tp.Nombre AS NombreTabla
                FROM Penalizaciones p
                INNER JOIN Usuarios u ON p.IdUsuario = u.Id
                LEFT JOIN TablasPuntuacion tp ON p.IdTabla = tp.Id
                LEFT JOIN Usuarios admin ON p.CreadaPor = admin.Id
                LEFT JOIN Usuarios modAdmin ON p.ModificadaPor = modAdmin.Id";

        $params = [];
        $where = [];

        if ($estado && $estado !== 'Todas') {
            $where[] = "p.Estado = ?";
            $params[] = $estado;
        }

        if ($busqueda) {
            $where[] = "(u.NombreUsuario LIKE ? OR u.NombreCompleto LIKE ? OR p.Motivo LIKE ?)";
            $busquedaParam = "%{$busqueda}%";
            $params[] = $busquedaParam;
            $params[] = $busquedaParam;
            $params[] = $busquedaParam;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY p.FechaCreacion DESC";

        $results = Database::fetchAll($sql, $params);
        
        // Convertir DateTime a string para JSON
        foreach ($results as &$row) {
            if ($row['FechaCreacion'] instanceof \DateTime) {
                $row['FechaCreacion'] = $row['FechaCreacion']->format('Y-m-d H:i:s');
            }
            if (!empty($row['FechaModificacion']) && $row['FechaModificacion'] instanceof \DateTime) {
                $row['FechaModificacion'] = $row['FechaModificacion']->format('Y-m-d H:i:s');
            }
        }
        
        return $results;
    }

    public function findById(int $id): ?array
    {
        return Database::fetchOne("SELECT * FROM Penalizaciones WHERE Id = ?", [$id]);
    }

    public function create(int $idUsuario, int $puntos, string $motivo, int $creadaPor, int $idTabla = 2): int
    {
        $row = Database::fetchOne(
            "INSERT INTO Penalizaciones (IdUsuario, PuntosRestados, Motivo, Estado, CreadaPor, IdTabla) 
             OUTPUT INSERTED.Id
             VALUES (?, ?, ?, 'Activa', ?, ?)",
            [$idUsuario, $puntos, $motivo, $creadaPor, $idTabla]
        );

        return $row ? (int)$row['Id'] : 0;
    }

    public function updateStatus(int $id, string $estado, int $modificadaPor): int
    {
        return Database::execute(
            "UPDATE Penalizaciones SET Estado = ?, FechaModificacion = GETDATE(), ModificadaPor = ? WHERE Id = ?",
            [$estado, $modificadaPor, $id]
        );
    }

    public function update(int $id, int $puntos, string $motivo, int $modificadaPor): int
    {
        return Database::execute(
            "UPDATE Penalizaciones SET PuntosRestados = ?, Motivo = ?, FechaModificacion = GETDATE(), ModificadaPor = ? WHERE Id = ?",
            [$puntos, $motivo, $modificadaPor, $id]
        );
    }
}
