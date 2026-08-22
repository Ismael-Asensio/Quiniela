-- ═══════════════════════════════════════════════════════════════
-- Migración 004: Agregar Competiciones (Soporte para múltiples copas)
-- ═══════════════════════════════════════════════════════════════

USE DBQUINIELA
GO

-- 1. Crear tabla de Competiciones
CREATE TABLE Competiciones (
    Id INT PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    Activa BIT DEFAULT 0,
    FechaInicio DATETIME NULL
)
GO

-- Insertar copas
INSERT INTO Competiciones (Id, Nombre, Activa, FechaInicio) VALUES (1, 'Mundial 2026', 0, '2026-06-11');
INSERT INTO Competiciones (Id, Nombre, Activa, FechaInicio) VALUES (2, 'Copa Centroamericana', 1, '2026-07-28');
GO

-- 2. Agregar columnas a tablas existentes
ALTER TABLE Partidos ADD IdCompeticion INT DEFAULT 1;
GO
UPDATE Partidos SET IdCompeticion = 1;
GO
ALTER TABLE Partidos ADD CONSTRAINT FK_Partidos_Competicion FOREIGN KEY (IdCompeticion) REFERENCES Competiciones(Id);
GO

ALTER TABLE TablasPuntuacion ADD IdCompeticion INT DEFAULT 1;
GO
UPDATE TablasPuntuacion SET IdCompeticion = 1;
GO
ALTER TABLE TablasPuntuacion ADD CONSTRAINT FK_Tablas_Competicion FOREIGN KEY (IdCompeticion) REFERENCES Competiciones(Id);
GO

ALTER TABLE ApuestasCampeon ADD IdCompeticion INT DEFAULT 1;
GO
UPDATE ApuestasCampeon SET IdCompeticion = 1;
GO
ALTER TABLE ApuestasCampeon ADD CONSTRAINT FK_ApuestasCampeon_Competicion FOREIGN KEY (IdCompeticion) REFERENCES Competiciones(Id);
GO

ALTER TABLE ApuestasPartidos ADD IdCompeticion INT DEFAULT 1;
GO
UPDATE ApuestasPartidos SET IdCompeticion = 1;
GO
ALTER TABLE ApuestasPartidos ADD CONSTRAINT FK_ApuestasPartidos_Competicion FOREIGN KEY (IdCompeticion) REFERENCES Competiciones(Id);
GO

ALTER TABLE Membresias ADD IdCompeticion INT DEFAULT 1;
GO
UPDATE Membresias SET IdCompeticion = 1;
GO
ALTER TABLE Membresias ADD CONSTRAINT FK_Membresias_Competicion FOREIGN KEY (IdCompeticion) REFERENCES Competiciones(Id);
GO

-- Para las penalizaciones, si están atadas a la tabla, entonces heredan la competición, no necesitan id directo, pero no afecta.
-- Para los equipos, pueden estar en varias copas, por lo que la relación está en Partidos.

PRINT 'Migración 004 aplicada con éxito.'
GO
