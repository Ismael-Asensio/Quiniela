-- ═══════════════════════════════════════════════════════════════
-- DBQUINIELA — Base de Datos para Quiniela Mundialista 2026 (V2)
-- Motor: SQL Server
-- Ejecutar en SQL Server Management Studio
-- ═══════════════════════════════════════════════════════════════

CREATE DATABASE DBQUINIELA
GO
USE DBQUINIELA
GO

-- ══════════════════════════════════════
-- 1. TABLA DE ROLES
-- ══════════════════════════════════════
CREATE TABLE Roles (
    Id INT PRIMARY KEY,
    Nombre VARCHAR(50) NOT NULL
)
GO

INSERT INTO Roles (Id, Nombre) VALUES (1, 'Admin')
INSERT INTO Roles (Id, Nombre) VALUES (2, 'Tesorero')
INSERT INTO Roles (Id, Nombre) VALUES (3, 'Miembro')
INSERT INTO Roles (Id, Nombre) VALUES (4, 'Usuario Regular')
GO

-- ══════════════════════════════════════
-- 2. TABLA DE USUARIOS
-- ══════════════════════════════════════
CREATE TABLE Usuarios (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    NombreUsuario VARCHAR(50) NOT NULL UNIQUE,
    Contrasena VARCHAR(255) NOT NULL,
    NombreCompleto VARCHAR(100) NULL,
    PuntosTotales INT DEFAULT 0,
    FechaCreacion DATETIME DEFAULT GETDATE(),
    IdRol INT DEFAULT 4,
    CONSTRAINT FK_Usuarios_Roles FOREIGN KEY (IdRol) REFERENCES Roles(Id)
)
GO

-- ══════════════════════════════════════
-- 3. TABLA DE EQUIPOS
-- ══════════════════════════════════════
CREATE TABLE Equipos (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    CodigoISO VARCHAR(5) NULL,
    BanderaURL VARCHAR(500) NULL,
    IdApiExterno INT NULL UNIQUE
)
GO

-- ══════════════════════════════════════
-- 4. TABLA DE PARTIDOS
-- ══════════════════════════════════════
CREATE TABLE Partidos (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    IdApiExterno INT NULL UNIQUE,
    IdLocal INT NULL,
    IdVisitante INT NULL,
    FechaHoraCST DATETIME NOT NULL,
    GolesLocal INT NULL,
    GolesVisitante INT NULL,
    Estado VARCHAR(30) DEFAULT 'NS',
    Fase VARCHAR(50) DEFAULT 'Grupos',
    MultiplicadorFase INT DEFAULT 1,
    Jornada VARCHAR(100) NULL,
    Sede VARCHAR(200) NULL,
    CuotaLocal DECIMAL(5,2) NULL,
    CuotaEmpate DECIMAL(5,2) NULL,
    CuotaVisitante DECIMAL(5,2) NULL,
    UltimaActualizacionCuotas DATETIME NULL,
    FOREIGN KEY (IdLocal) REFERENCES Equipos(Id),
    FOREIGN KEY (IdVisitante) REFERENCES Equipos(Id)
)
GO

-- ══════════════════════════════════════
-- 5. TABLA DE PREDICCIONES
-- ══════════════════════════════════════
CREATE TABLE Predicciones (
    IdUsuario INT NOT NULL,
    IdPartido INT NOT NULL,
    GolesLocal INT NOT NULL DEFAULT 0,
    GolesVisitante INT NOT NULL DEFAULT 0,
    PuntosObtenidos INT DEFAULT 0,
    FechaPrediccion DATETIME DEFAULT GETDATE(),
    PRIMARY KEY (IdUsuario, IdPartido),
    FOREIGN KEY (IdUsuario) REFERENCES Usuarios(Id),
    FOREIGN KEY (IdPartido) REFERENCES Partidos(Id)
)
GO

-- ══════════════════════════════════════
-- 6. TABLAS DE PUNTUACIÓN (GRUPOS)
-- ══════════════════════════════════════
CREATE TABLE TablasPuntuacion (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    Descripcion VARCHAR(255) NULL,
    CreadaPor INT NOT NULL,
    FaseInicio VARCHAR(50) DEFAULT 'Grupos', -- Fase desde la cual se cuentan puntos (Grupos = todas)
    FechaCreacion DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (CreadaPor) REFERENCES Usuarios(Id)
)
GO

CREATE TABLE TablaMiembros (
    IdTabla INT NOT NULL,
    IdUsuario INT NOT NULL,
    FechaIngreso DATETIME DEFAULT GETDATE(),
    PuedeVer BIT DEFAULT 1,
    PuedeExportar BIT DEFAULT 0,
    MostrarEnRanking BIT DEFAULT 1,
    PRIMARY KEY (IdTabla, IdUsuario),
    FOREIGN KEY (IdTabla) REFERENCES TablasPuntuacion(Id),
    FOREIGN KEY (IdUsuario) REFERENCES Usuarios(Id)
)
GO

-- ══════════════════════════════════════
-- 7. TABLAS DE APUESTAS Y MEMBRESÍAS
-- ══════════════════════════════════════
CREATE TABLE Membresias (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    IdUsuario INT NOT NULL,
    MontoPagado DECIMAL(10,2) NOT NULL,
    FechaPago DATETIME DEFAULT GETDATE(),
    Estado VARCHAR(20) DEFAULT 'Activa',
    FOREIGN KEY (IdUsuario) REFERENCES Usuarios(Id)
)
GO

CREATE TABLE ApuestasCampeon (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    IdUsuario INT NOT NULL,
    IdEquipo INT NOT NULL,
    MontoApostado DECIMAL(10,2) NOT NULL,
    CuotaFijada DECIMAL(5,2) NOT NULL,
    EstadoApuesta VARCHAR(20) DEFAULT 'Pendiente',
    Fecha DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (IdUsuario) REFERENCES Usuarios(Id),
    FOREIGN KEY (IdEquipo) REFERENCES Equipos(Id)
)
GO

CREATE TABLE ApuestasPartidos (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    IdUsuario INT NOT NULL,
    IdPartido INT NOT NULL,
    MontoApostado DECIMAL(10,2) NOT NULL,
    CuotaFijada DECIMAL(5,2) NOT NULL,
    EquipoApostado VARCHAR(10) NOT NULL,  -- 'Local', 'Empate', 'Visitante'
    EstadoApuesta VARCHAR(20) DEFAULT 'Pendiente',
    Ganancia DECIMAL(10,2) NULL,
    Fecha DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (IdUsuario) REFERENCES Usuarios(Id),
    FOREIGN KEY (IdPartido) REFERENCES Partidos(Id)
)
GO

-- ══════════════════════════════════════
-- 8. ÍNDICES DE RENDIMIENTO
-- ══════════════════════════════════════
CREATE INDEX IX_Partidos_FechaHoraCST ON Partidos(FechaHoraCST)
GO
CREATE INDEX IX_Partidos_Estado ON Partidos(Estado)
GO
CREATE INDEX IX_Partidos_Fase ON Partidos(Fase)
GO
CREATE INDEX IX_Predicciones_IdPartido ON Predicciones(IdPartido)
GO
CREATE INDEX IX_Predicciones_IdUsuario ON Predicciones(IdUsuario)
GO
CREATE INDEX IX_TablaMiembros_IdUsuario ON TablaMiembros(IdUsuario)
GO
CREATE INDEX IX_TablaMiembros_IdTabla ON TablaMiembros(IdTabla)
GO
CREATE INDEX IX_Membresias_IdUsuario ON Membresias(IdUsuario)
GO
CREATE INDEX IX_ApuestasCampeon_IdUsuario ON ApuestasCampeon(IdUsuario)
GO
CREATE INDEX IX_ApuestasCampeon_IdEquipo ON ApuestasCampeon(IdEquipo)
GO
CREATE INDEX IX_ApuestasPartidos_IdUsuario ON ApuestasPartidos(IdUsuario)
GO
CREATE INDEX IX_ApuestasPartidos_IdPartido ON ApuestasPartidos(IdPartido)
GO
CREATE INDEX IX_ApuestasPartidos_Estado ON ApuestasPartidos(EstadoApuesta)
GO
CREATE INDEX IX_Usuarios_IdRol ON Usuarios(IdRol)
GO

-- ══════════════════════════════════════
-- 9. VISTA DE RANKING
-- ══════════════════════════════════════
CREATE VIEW vw_Ranking AS
SELECT 
    u.Id,
    u.NombreUsuario,
    u.NombreCompleto,
    u.PuntosTotales,
    COUNT(pr.IdPartido) AS PrediccionesRealizadas,
    SUM(CASE WHEN p.Estado IN ('FT','AET','PEN') THEN 1 ELSE 0 END) AS PartidosEvaluados,
    SUM(CASE WHEN pr.PuntosObtenidos > 0 THEN 1 ELSE 0 END) AS Aciertos,
    SUM(CASE WHEN p.Estado IN ('FT','AET','PEN') 
             AND pr.GolesLocal = p.GolesLocal 
             AND pr.GolesVisitante = p.GolesVisitante THEN 1 ELSE 0 END) AS AciertosExactos,
    CASE 
        WHEN SUM(CASE WHEN p.Estado IN ('FT','AET','PEN') THEN 1 ELSE 0 END) > 0 
        THEN CAST(ROUND(
            (CAST(SUM(CASE WHEN p.Estado IN ('FT','AET','PEN') THEN pr.PuntosObtenidos ELSE 0 END) AS FLOAT) 
            / (SUM(CASE WHEN p.Estado IN ('FT','AET','PEN') THEN 1 ELSE 0 END) * 3)) * 100, 1
        ) AS DECIMAL(5,1))
        ELSE 0 
    END AS PorcentajeAcierto
FROM Usuarios u
LEFT JOIN Predicciones pr ON u.Id = pr.IdUsuario
LEFT JOIN Partidos p ON pr.IdPartido = p.Id
GROUP BY u.Id, u.NombreUsuario, u.NombreCompleto, u.PuntosTotales
GO

-- ══════════════════════════════════════
-- 10. DATOS INICIALES — USUARIOS ADMIN
-- ══════════════════════════════════════
INSERT INTO Usuarios (NombreUsuario, Contrasena, NombreCompleto, IdRol)
VALUES ('admin', 'admin', 'Administrador', 1)
GO

INSERT INTO Usuarios (NombreUsuario, Contrasena, NombreCompleto, IdRol)
VALUES ('cvande', 'cvande', 'Admin CVande', 1)
GO

INSERT INTO Usuarios (NombreUsuario, Contrasena, NombreCompleto, IdRol)
VALUES ('iasensio', 'iasensio', 'Admin iasensio', 1)
GO

-- ══════════════════════════════════════
-- 11. REFERENCIA DE FASES Y MULTIPLICADORES
-- ══════════════════════════════════════
-- Esta tabla de referencia se usa como documentación.
-- Los valores se insertan automáticamente desde syncPartidos.php
--
-- | Fase de la API (round)      | Fase Local      | Multiplicador |
-- |-----------------------------|-----------------|---------------|
-- | Group Stage - X             | Grupos          | 1             |
-- | Round of 32                 | Dieciseisavos   | 2             |
-- | Round of 16                 | Octavos         | 2             |
-- | Quarter-finals              | Cuartos         | 3             |
-- | Semi-finals                 | Semifinales     | 4             |
-- | 3rd Place Final             | Tercer Lugar    | 5             |
-- | Final                       | Final           | 5             |
-- ══════════════════════════════════════

PRINT '✅ Base de datos DBQUINIELA V2 creada exitosamente con soporte para apuestas.'
GO
