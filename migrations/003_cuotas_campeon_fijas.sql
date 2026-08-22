-- ═══════════════════════════════════════════════════════════════
-- Migración 003: Cuotas fijas para Campeón del Mundo
-- Permite al admin fijar cuotas manualmente y excluir equipos
-- ═══════════════════════════════════════════════════════════════

USE DBQUINIELA
GO

IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='CuotasCampeonFijas' AND xtype='U')
BEGIN
    CREATE TABLE CuotasCampeonFijas (
        Id INT IDENTITY(1,1) PRIMARY KEY,
        IdEquipo INT NOT NULL UNIQUE,
        CuotaFija DECIMAL(8,2) NOT NULL,
        Excluido BIT DEFAULT 0,
        FechaActualizacion DATETIME DEFAULT GETDATE(),
        ActualizadoPor INT NULL,
        FOREIGN KEY (IdEquipo) REFERENCES Equipos(Id),
        FOREIGN KEY (ActualizadoPor) REFERENCES Usuarios(Id)
    )

    CREATE INDEX IX_CuotasCampeonFijas_IdEquipo ON CuotasCampeonFijas(IdEquipo)
    CREATE INDEX IX_CuotasCampeonFijas_Excluido ON CuotasCampeonFijas(Excluido)

    PRINT '✅ Tabla CuotasCampeonFijas creada exitosamente.'
END
ELSE
BEGIN
    PRINT '⚠️ Tabla CuotasCampeonFijas ya existe, no se realizaron cambios.'
END
GO
