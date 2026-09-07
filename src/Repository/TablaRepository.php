<?php

// ═══════════════════════════════════════════════════════════════
// TablaRepository — Acceso a datos de Tablas de Puntuación
// ═══════════════════════════════════════════════════════════════

class TablaRepository
{
    public function findAll(): array
    {
        return Database::fetchAll(
            "SELECT tp.Id, tp.Nombre, tp.Descripcion, tp.CreadaPor, u.NombreUsuario AS CreadaPorUsuario,
                    tp.FechaCreacion, ISNULL(tp.FaseInicio, 'Grupos') AS FaseInicio,
                    (SELECT COUNT(*) FROM TablaMiembros WHERE IdTabla = tp.Id) AS TotalMiembros
             FROM TablasPuntuacion tp
             LEFT JOIN Usuarios u ON tp.CreadaPor = u.Id
             WHERE tp.IdCompeticion = ?
             ORDER BY tp.FechaCreacion DESC",
            [Config::competicionActiva()]
        );
    }

    /**
     * Todas las tablas de TODAS las competiciones con su conteo de
     * miembros, sin filtrar por competición activa. Para la vista de
     * administración de permisos (a diferencia de findAll(), que sí
     * filtra por la competición activa).
     */
    public function findAllWithMemberCount(): array
    {
        return Database::fetchAll(
            "SELECT t.*, (SELECT COUNT(*) FROM TablaMiembros WHERE IdTabla = t.Id) AS TotalMiembros
             FROM TablasPuntuacion t"
        );
    }

    /**
     * Todas las filas de TablaMiembros, sin filtrar. Para la vista de
     * administración de permisos.
     */
    public function findAllMembresias(): array
    {
        return Database::fetchAll("SELECT * FROM TablaMiembros");
    }

    /**
     * Verifica que una tabla exista y pertenezca a la competición
     * activa. Usado para validar $idTabla en operaciones que antes
     * asumían un valor hardcodeado (ver PenalizacionService::crear()).
     */
    public function existsInCompeticionActiva(int $idTabla): bool
    {
        $row = Database::fetchOne(
            "SELECT Id FROM TablasPuntuacion WHERE Id = ? AND IdCompeticion = ?",
            [$idTabla, Config::competicionActiva()]
        );
        return $row !== null;
    }

    public function findById(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT Id, Nombre, Descripcion, CreadaPor, FechaCreacion, ISNULL(FaseInicio, 'Grupos') AS FaseInicio FROM TablasPuntuacion WHERE Id = ?",
            [$id]
        );
    }

    public function findByName(string $name): ?array
    {
        return Database::fetchOne("SELECT * FROM TablasPuntuacion WHERE Nombre = ?", [$name]);
    }

    public function findByUser(int $idUsuario): array
    {
        return Database::fetchAll(
            "SELECT tp.Id, tp.Nombre, tp.Descripcion, tp.CreadaPor, ISNULL(tp.FaseInicio, 'Grupos') AS FaseInicio,
                    tm.FechaIngreso, tm.PuedeVer, tm.PuedeExportar, tm.MostrarEnRanking
             FROM TablasPuntuacion tp
             INNER JOIN TablaMiembros tm ON tp.Id = tm.IdTabla
             WHERE tm.IdUsuario = ? AND tp.IdCompeticion = ?
             ORDER BY tp.Nombre ASC",
            [$idUsuario, Config::competicionActiva()]
        );
    }

    public function create(string $nombre, string $descripcion, int $creadaPor, string $faseInicio = 'Grupos'): int
    {
        Database::execute(
            "INSERT INTO TablasPuntuacion (Nombre, Descripcion, CreadaPor, FaseInicio, IdCompeticion) VALUES (?, ?, ?, ?, ?)",
            [$nombre, $descripcion, $creadaPor, $faseInicio, Config::competicionActiva()]
        );

        $row = Database::fetchOne("SELECT Id FROM TablasPuntuacion WHERE Nombre = ?", [$nombre]);
        return $row ? (int)$row['Id'] : 0;
    }

    // ─── Membresía de Tablas ───

    public function isMember(int $idTabla, int $idUsuario): bool
    {
        $row = Database::fetchOne(
            "SELECT IdTabla FROM TablaMiembros WHERE IdTabla = ? AND IdUsuario = ?",
            [$idTabla, $idUsuario]
        );
        return $row !== null;
    }

    public function addMember(int $idTabla, int $idUsuario): int
    {
        if ($this->isMember($idTabla, $idUsuario)) {
            return 0; // Ya es miembro
        }

        return Database::execute(
            "INSERT INTO TablaMiembros (IdTabla, IdUsuario) VALUES (?, ?)",
            [$idTabla, $idUsuario]
        );
    }

    public function removeMember(int $idTabla, int $idUsuario): int
    {
        return Database::execute(
            "DELETE FROM TablaMiembros WHERE IdTabla = ? AND IdUsuario = ?",
            [$idTabla, $idUsuario]
        );
    }

    /**
     * Crea o actualiza un permiso puntual de un usuario sobre una tabla.
     * $field debe ser exactamente 'PuedeVer', 'PuedeExportar' o
     * 'MostrarEnRanking' (validado por el Service antes de llamar aquí);
     * el switch evita interpolar el nombre de columna directo en el SQL.
     */
    public function upsertPermiso(int $idTabla, int $idUsuario, string $field, int $value): void
    {
        if ($this->isMember($idTabla, $idUsuario)) {
            switch ($field) {
                case 'PuedeVer':
                    Database::execute("UPDATE TablaMiembros SET PuedeVer = ? WHERE IdTabla = ? AND IdUsuario = ?", [$value, $idTabla, $idUsuario]);
                    break;
                case 'PuedeExportar':
                    Database::execute("UPDATE TablaMiembros SET PuedeExportar = ? WHERE IdTabla = ? AND IdUsuario = ?", [$value, $idTabla, $idUsuario]);
                    break;
                case 'MostrarEnRanking':
                    Database::execute("UPDATE TablaMiembros SET MostrarEnRanking = ? WHERE IdTabla = ? AND IdUsuario = ?", [$value, $idTabla, $idUsuario]);
                    break;
            }
        } else {
            // No existe todavía: insertar con 0 en todos los campos
            // excepto el que se está fijando.
            $puedeVer = ($field === 'PuedeVer') ? $value : 0;
            $puedeExportar = ($field === 'PuedeExportar') ? $value : 0;
            $mostrarEnRanking = ($field === 'MostrarEnRanking') ? $value : 0;

            Database::execute(
                "INSERT INTO TablaMiembros (IdTabla, IdUsuario, PuedeVer, PuedeExportar, MostrarEnRanking) VALUES (?, ?, ?, ?, ?)",
                [$idTabla, $idUsuario, $puedeVer, $puedeExportar, $mostrarEnRanking]
            );
        }
    }

    public function getMembers(int $idTabla): array
    {
        return Database::fetchAll(
            "SELECT u.Id, u.NombreUsuario, u.NombreCompleto, 
                    CASE WHEN tm.IdTabla IS NOT NULL THEN 1 ELSE 0 END AS EsMiembro
             FROM Usuarios u
             LEFT JOIN TablaMiembros tm ON u.Id = tm.IdUsuario AND tm.IdTabla = ?
             ORDER BY u.NombreUsuario ASC",
            [$idTabla]
        );
    }
}
