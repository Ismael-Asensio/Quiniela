-- ═══════════════════════════════════════════════════════════════
-- backup_db.sql — Backup completo de DBQUINIELA (Fase 0.2)
-- ═══════════════════════════════════════════════════════════════
-- Ejecutar en SSMS conectado a 172.16.8.175, o con sqlcmd:
--   sqlcmd -S 172.16.8.175 -U sa -i bin\backup_db.sql
--
-- IMPORTANTE: SQL Server siempre escribe el archivo .bak en el disco
-- de la máquina que corre el motor (172.16.8.175), sin importar desde
-- dónde ejecutes este script. Para llevarlo a tu PC local, cópialo
-- desde esa ruta usando el acceso que ya tengas a ese servidor
-- (RDP, unidad compartida \\172.16.8.175\..., etc.) — es un solo
-- archivo, no hace falta automatizar la transferencia.
--
-- La ruta de abajo asume la carpeta de backups por defecto de una
-- instalación estándar de SQL Server 2019 (MSSQL15). Si tu instalación
-- usa otra ruta o instancia, ajusta @ruta antes de ejecutar; puedes
-- confirmar la ruta real con:
--   SELECT SERVERPROPERTY('InstanceDefaultBackupPath');
-- ═══════════════════════════════════════════════════════════════

DECLARE @ruta NVARCHAR(500);
DECLARE @fecha NVARCHAR(20) = FORMAT(GETDATE(), 'yyyy-MM-dd_HHmmss');

-- Usa la carpeta de backup por defecto configurada en la instancia si
-- existe; si no, cae a una ruta fija razonable.
SET @ruta = CAST(SERVERPROPERTY('InstanceDefaultBackupPath') AS NVARCHAR(500));
IF @ruta IS NULL OR @ruta = ''
    SET @ruta = 'C:\Backups\';

DECLARE @archivo NVARCHAR(600) = @ruta + 'DBQUINIELA_pre_migracion_' + @fecha + '.bak';

PRINT 'Guardando backup en: ' + @archivo;

BACKUP DATABASE DBQUINIELA
TO DISK = @archivo
WITH COMPRESSION, INIT, CHECKSUM,
     NAME = 'DBQUINIELA - Backup completo pre-migracion';

PRINT '✅ Backup completado: ' + @archivo;
PRINT 'Copia este archivo a tu PC local usando el acceso que tengas a 172.16.8.175.';
GO

-- Verificación rápida: confirma que el backup es restaurable y ver su tamaño
RESTORE VERIFYONLY FROM DISK = 'REEMPLAZAR_CON_LA_RUTA_IMPRESA_ARRIBA';
GO
