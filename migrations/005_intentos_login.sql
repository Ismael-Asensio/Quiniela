-- ═══════════════════════════════════════════════════════════════
-- Migración 005: Registro de intentos de login (rate limiting)
-- ═══════════════════════════════════════════════════════════════
-- Prerrequisito de PLAN_MIGRACION.md Fase 2.7. El código que lee/escribe
-- esta tabla (AuthService) todavía NO está activado a propósito: si se
-- activara antes de correr esta migración, el login completo de la app
-- se rompería con un error SQL en cada intento. Avisar quede confirmado
-- que esta migración ya corrió antes de pedir que se active el conteo.
-- ═══════════════════════════════════════════════════════════════

USE DBQUINIELA
GO

IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='IntentosLogin' AND xtype='U')
BEGIN
    CREATE TABLE IntentosLogin (
        Id INT IDENTITY(1,1) PRIMARY KEY,
        NombreUsuario VARCHAR(50) NOT NULL,
        DireccionIP VARCHAR(45) NOT NULL,
        Exitoso BIT NOT NULL,
        Fecha DATETIME DEFAULT GETDATE()
    )

    CREATE INDEX IX_IntentosLogin_Usuario_Fecha ON IntentosLogin(NombreUsuario, Fecha)
    CREATE INDEX IX_IntentosLogin_IP_Fecha ON IntentosLogin(DireccionIP, Fecha)

    PRINT '✅ Tabla IntentosLogin creada exitosamente.'
END
ELSE
BEGIN
    PRINT '⚠️ Tabla IntentosLogin ya existe, no se realizaron cambios.'
END
GO
