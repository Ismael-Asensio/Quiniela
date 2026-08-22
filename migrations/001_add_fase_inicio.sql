-- ═══════════════════════════════════════════════════════════════
-- MIGRACIÓN: Agregar FaseInicio a TablasPuntuacion
-- Ejecutar en SQL Server Management Studio sobre DBQUINIELA
-- ═══════════════════════════════════════════════════════════════

USE DBQUINIELA
GO

-- Agregar columna FaseInicio (default 'Grupos' para no afectar tablas existentes)
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'TablasPuntuacion' AND COLUMN_NAME = 'FaseInicio'
)
BEGIN
    ALTER TABLE TablasPuntuacion ADD FaseInicio VARCHAR(50) DEFAULT 'Grupos'
    PRINT '✅ Columna FaseInicio agregada a TablasPuntuacion'
END
ELSE
BEGIN
    PRINT '⚠️ La columna FaseInicio ya existe en TablasPuntuacion'
END
GO
