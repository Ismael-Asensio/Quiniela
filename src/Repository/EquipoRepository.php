<?php

// ═══════════════════════════════════════════════════════════════
// EquipoRepository — Acceso a datos de Equipos
// ═══════════════════════════════════════════════════════════════

class EquipoRepository
{
    public function findById(int $id): ?array
    {
        return Database::fetchOne("SELECT * FROM Equipos WHERE Id = ?", [$id]);
    }

    public function findByApiId(int $apiId): ?array
    {
        return Database::fetchOne("SELECT * FROM Equipos WHERE IdApiExterno = ?", [$apiId]);
    }

    public function findByIso(string $iso): ?array
    {
        return Database::fetchOne("SELECT * FROM Equipos WHERE CodigoISO = ?", [$iso]);
    }

    public function findByNombre(string $nombre): ?array
    {
        return Database::fetchOne("SELECT * FROM Equipos WHERE Nombre = ?", [$nombre]);
    }

    public function findAll(): array
    {
        return Database::fetchAll("SELECT * FROM Equipos ORDER BY Nombre ASC");
    }

    public function create(string $nombre, ?string $iso, ?string $banderaUrl, ?int $apiId): int
    {
        Database::execute(
            "INSERT INTO Equipos (Nombre, CodigoISO, BanderaURL, IdApiExterno) VALUES (?, ?, ?, ?)",
            [$nombre, $iso, $banderaUrl, $apiId]
        );

        $row = Database::fetchOne("SELECT Id FROM Equipos WHERE Nombre = ?", [$nombre]);
        return $row ? (int)$row['Id'] : 0;
    }

    public function update(int $id, string $nombre, ?string $iso, ?string $banderaUrl): int
    {
        return Database::execute(
            "UPDATE Equipos SET Nombre = ?, CodigoISO = ?, BanderaURL = ? WHERE Id = ?",
            [$nombre, $iso, $banderaUrl, $id]
        );
    }
}
