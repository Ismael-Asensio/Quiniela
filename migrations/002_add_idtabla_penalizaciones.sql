-- ═══════════════════════════════════════════════════════════════
-- MIGRACIÓN: Agregar IdTabla a Penalizaciones
-- Ejecutar en SQL Server Management Studio sobre DBQUINIELA
-- ═══════════════════════════════════════════════════════════════

USE DBQUINIELA
GO

-- 1. Agregar columna IdTabla
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'Penalizaciones' AND COLUMN_NAME = 'IdTabla'
)
BEGIN
    ALTER TABLE Penalizaciones ADD IdTabla INT NULL
    PRINT '✅ Columna IdTabla agregada a Penalizaciones'
END
ELSE
BEGIN
    PRINT '⚠️ La columna IdTabla ya existe en Penalizaciones'
END
GO

-- 2. Actualizar penalizaciones existentes para que pertenezcan a la tabla 2 (como solicitó el usuario)
UPDATE Penalizaciones 
SET IdTabla = 2 
WHERE IdTabla IS NULL;
GO
PRINT '✅ Penalizaciones existentes asignadas a la tabla 2'
GO

-- 3. (Opcional pero recomendado) Agregar FK para asegurar integridad
IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_Penalizaciones_TablasPuntuacion'
)
BEGIN
    ALTER TABLE Penalizaciones 
    ADD CONSTRAINT FK_Penalizaciones_TablasPuntuacion FOREIGN KEY (IdTabla) REFERENCES TablasPuntuacion(Id)
    PRINT '✅ Clave foránea agregada: FK_Penalizaciones_TablasPuntuacion'
END
GO
