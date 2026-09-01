<?php

// ═══════════════════════════════════════════════════════════════
// UsuarioRepository — Acceso a datos de Usuarios
// ═══════════════════════════════════════════════════════════════

class UsuarioRepository
{
    public function findById(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT Id, NombreUsuario, Contrasena, NombreCompleto, PuntosTotales, ISNULL(IdRol, 4) AS IdRol FROM Usuarios WHERE Id = ?",
            [$id]
        );
    }

    public function findByUsername(string $username): ?array
    {
        return Database::fetchOne(
            "SELECT Id, NombreUsuario, Contrasena, NombreCompleto, PuntosTotales, ISNULL(IdRol, 4) AS IdRol FROM Usuarios WHERE NombreUsuario = ?",
            [$username]
        );
    }

    public function exists(int $id): bool
    {
        $row = Database::fetchOne("SELECT Id FROM Usuarios WHERE Id = ?", [$id]);
        return $row !== null;
    }

    public function usernameExists(string $username): bool
    {
        $row = Database::fetchOne("SELECT Id FROM Usuarios WHERE NombreUsuario = ?", [$username]);
        return $row !== null;
    }

    public function findAll(): array
    {
        return Database::fetchAll("SELECT Id, NombreUsuario, NombreCompleto FROM Usuarios ORDER BY NombreUsuario ASC");
    }

    public function findAllWithRoles(int $competicionId = null): array
    {
        $compId = $competicionId ?? Config::competicionActiva();
        return Database::fetchAll(
            "SELECT u.Id, u.NombreUsuario, u.NombreCompleto, u.PuntosTotales, 
                    CASE 
                        WHEN ISNULL(u.IdRol, 4) IN (1, 2) THEN u.IdRol 
                        WHEN (SELECT SUM(MontoPagado) FROM Membresias m WHERE m.IdUsuario = u.Id AND m.Estado = 'Activa' AND m.IdCompeticion = ?) > 0 THEN 3
                        ELSE 4
                    END AS IdRol,
                    CASE 
                        WHEN ISNULL(u.IdRol, 4) = 1 THEN 'Admin'
                        WHEN ISNULL(u.IdRol, 4) = 2 THEN 'Tesorero'
                        WHEN (SELECT SUM(MontoPagado) FROM Membresias m WHERE m.IdUsuario = u.Id AND m.Estado = 'Activa' AND m.IdCompeticion = ?) > 0 THEN 'Miembro'
                        ELSE 'Usuario'
                    END AS NombreRol,
                    u.FechaCreacion,
                    ISNULL((SELECT SUM(MontoPagado) FROM Membresias m WHERE m.IdUsuario = u.Id AND m.Estado = 'Activa' AND m.IdCompeticion = ?), 0) AS MontoPagado
             FROM Usuarios u
             ORDER BY u.NombreUsuario ASC",
             [$compId, $compId, $compId]
        );
    }

    public function create(string $username, string $password, string $fullName): ?array
    {
        Database::execute(
            "INSERT INTO Usuarios (NombreUsuario, Contrasena, NombreCompleto) VALUES (?, ?, ?)",
            [$username, $password, $fullName]
        );

        return $this->findByUsername($username);
    }

    public function delete(int $id): int
    {
        // Todo o nada: si alguna dependencia falla a mitad de camino (p.ej.
        // una FK no contemplada aquí), no debe quedar el usuario a medio
        // borrar en unas tablas y presente en otras.
        return Database::transaction(function () use ($id) {
            Database::execute("DELETE FROM Predicciones WHERE IdUsuario = ?", [$id]);
            Database::execute("DELETE FROM TablaMiembros WHERE IdUsuario = ?", [$id]);
            Database::execute("DELETE FROM Penalizaciones WHERE IdUsuario = ?", [$id]);
            Database::execute("DELETE FROM ApuestasCampeon WHERE IdUsuario = ?", [$id]);
            Database::execute("DELETE FROM ApuestasPartidos WHERE IdUsuario = ?", [$id]);
            Database::execute("DELETE FROM Membresias WHERE IdUsuario = ?", [$id]);

            return Database::execute("DELETE FROM Usuarios WHERE Id = ?", [$id]);
        });
    }

    public function updateRole(int $userId, int $roleId): int
    {
        return Database::execute("UPDATE Usuarios SET IdRol = ? WHERE Id = ?", [$roleId, $userId]);
    }

    /**
     * Actualiza nombre/rol y, si corresponde, la membresía del usuario
     * PARA ESTA COMPETICIÓN ÚNICAMENTE. Antes de esta corrección, el
     * DELETE no filtraba por IdCompeticion y borraba el historial de
     * membresías de TODAS las competiciones del usuario, y el INSERT no
     * indicaba IdCompeticion (caía al default de la migración 004, que
     * es la competición 1 — inactiva). Solo se registra un recibo nuevo
     * cuando el rol destino es Miembro, igual que hacía convertToMember.
     */
    public function updateUserAndMembership(int $userId, string $fullName, int $roleId, ?float $montoPagado, int $competicionId): int
    {
        // Atómico: si el INSERT del nuevo recibo falla, el rol y la
        // membresía anterior (ya borrada) deben revertirse también —
        // antes el usuario podía quedar con rol nuevo y SIN membresía.
        return Database::transaction(function () use ($userId, $fullName, $roleId, $montoPagado, $competicionId) {
            Database::execute(
                "UPDATE Usuarios SET NombreCompleto = ?, IdRol = ? WHERE Id = ?",
                [$fullName, $roleId, $userId]
            );

            if ($roleId === Config::ROL_MIEMBRO && $montoPagado !== null && $montoPagado > 0) {
                // Eliminar las membresias de esta competicion para que el
                // nuevo monto sea el total absoluto sin dejar rastro
                Database::execute(
                    "DELETE FROM Membresias WHERE IdUsuario = ? AND IdCompeticion = ?",
                    [$userId, $competicionId]
                );
                Database::execute(
                    "INSERT INTO Membresias (IdUsuario, MontoPagado, Estado, IdCompeticion) VALUES (?, ?, 'Activa', ?)",
                    [$userId, $montoPagado, $competicionId]
                );
            }

            return 1;
        });
    }

    public function getRole(int $userId): int
    {
        $row = Database::fetchOne("SELECT ISNULL(IdRol, ?) AS IdRol FROM Usuarios WHERE Id = ?", [Config::ROL_USUARIO, $userId]);
        return $row ? (int)$row['IdRol'] : Config::ROL_USUARIO;
    }

    /**
     * Recalcula PuntosTotales = SUM(Predicciones) - SUM(Penalizaciones activas).
     */
    public function recalcularPuntos(int $idUsuario): bool
    {
        return Database::execute(
            "UPDATE Usuarios SET PuntosTotales = (
                SELECT COALESCE(SUM(pr.PuntosObtenidos), 0) FROM Predicciones pr
                INNER JOIN Partidos p ON pr.IdPartido = p.Id
                WHERE pr.IdUsuario = ? AND p.IdCompeticion = ?
            ) - (
                SELECT COALESCE(SUM(pen.PuntosRestados), 0) FROM Penalizaciones pen
                INNER JOIN TablasPuntuacion tp ON pen.IdTabla = tp.Id
                WHERE pen.IdUsuario = ? AND pen.Estado = 'Activa' AND tp.IdCompeticion = ?
            ) WHERE Id = ?",
            [$idUsuario, Config::competicionActiva(), $idUsuario, Config::competicionActiva(), $idUsuario]
        ) >= 0;
    }

    /**
     * Recalcula PuntosTotales para TODOS los usuarios.
     */
    public function recalcularPuntosTodos(): int
    {
        return Database::execute(
            "UPDATE Usuarios SET PuntosTotales = (
                SELECT COALESCE(SUM(pr.PuntosObtenidos), 0) FROM Predicciones pr
                INNER JOIN Partidos p ON pr.IdPartido = p.Id
                WHERE pr.IdUsuario = Usuarios.Id AND p.IdCompeticion = ?
            ) - (
                SELECT COALESCE(SUM(pen.PuntosRestados), 0) FROM Penalizaciones pen
                INNER JOIN TablasPuntuacion tp ON pen.IdTabla = tp.Id
                WHERE pen.IdUsuario = Usuarios.Id AND pen.Estado = 'Activa' AND tp.IdCompeticion = ?
            )",
            [Config::competicionActiva(), Config::competicionActiva()]
        );
    }
}
