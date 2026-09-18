# ═══════════════════════════════════════════════════════════════
# install_sync_task.ps1 — Registra la tarea de sincronizacion
# ═══════════════════════════════════════════════════════════════
# Crea la tarea "Quiniela\SyncPartidos" en el Programador de tareas
# de Windows, que ejecuta bin/sync_once.php cada N minutos.
#
# Uso:
#   powershell -ExecutionPolicy Bypass -File bin\install_sync_task.ps1
#   powershell -ExecutionPolicy Bypass -File bin\install_sync_task.ps1 -IntervaloMinutos 15
#
# NO requiere permisos de administrador ni guardar contrasena: la tarea
# se registra para el usuario interactivo actual y corre con la sesion
# de Windows iniciada. Para que corra tambien con la sesion cerrada hay
# que cambiarla a mano en taskschd.msc (ver README.md).
# ═══════════════════════════════════════════════════════════════

param(
    [int]$IntervaloMinutos = 5,
    [string]$Php = "C:\AppServ\php7\php.exe"
)

$ErrorActionPreference = 'Stop'

$binDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$vbs      = Join-Path $binDir 'sync_once.vbs'
$phpFile  = Join-Path $binDir 'sync_once.php'
$taskName = 'SyncPartidos'
$taskPath = '\Quiniela\'

# ─── Comprobaciones previas ───

if (-not (Test-Path $vbs))     { throw "No se encuentra el lanzador: $vbs" }
if (-not (Test-Path $phpFile)) { throw "No se encuentra el script: $phpFile" }
if (-not (Test-Path $Php))     { throw "No se encuentra PHP: $Php  (pasalo con -Php <ruta>)" }

if ($IntervaloMinutos -lt 1 -or $IntervaloMinutos -gt 1440) {
    throw "IntervaloMinutos debe estar entre 1 y 1440."
}

$configLocal = Join-Path (Split-Path -Parent $binDir) 'config\local.php'
if (-not (Test-Path $configLocal)) {
    throw "Falta config/local.php - sin credenciales la tarea no podra conectar a la BD."
}

# ─── Definicion de la tarea ───

$action = New-ScheduledTaskAction -Execute 'wscript.exe' `
                                  -Argument "`"$vbs`"" `
                                  -WorkingDirectory $binDir

# -Once + -RepetitionInterval sin -RepetitionDuration = repeticion indefinida
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
                                    -RepetitionInterval (New-TimeSpan -Minutes $IntervaloMinutos)

$settings = New-ScheduledTaskSettingsSet `
    -MultipleInstances IgnoreNew `
    -StartWhenAvailable `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -DontStopOnIdleEnd `
    -WakeToRun `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 10)

# -WakeToRun despierta el equipo suspendido para sincronizar. Requiere que
# el plan de energia permita temporizadores de reactivacion; si no, la
# tarea simplemente espera a que el equipo vuelva (-StartWhenAvailable).
# Con el equipo APAGADO no hay nada que hacer: ninguna tarea puede encenderlo.

$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" `
                                        -LogonType Interactive `
                                        -RunLevel Limited

Register-ScheduledTask -TaskName $taskName `
                       -TaskPath $taskPath `
                       -Action $action `
                       -Trigger $trigger `
                       -Settings $settings `
                       -Principal $principal `
                       -Description "Quiniela: sincroniza partidos y puntos con ESPN cada $IntervaloMinutos minutos." `
                       -Force | Out-Null

Write-Host ""
Write-Host "Tarea registrada: $taskPath$taskName" -ForegroundColor Green
Write-Host "  Intervalo : cada $IntervaloMinutos minutos"
Write-Host "  Ejecuta   : wscript.exe `"$vbs`""
Write-Host "  Usuario   : $env:USERDOMAIN\$env:USERNAME (solo con la sesion iniciada, sin contrasena guardada)"
Write-Host "  Logs      : $(Join-Path $binDir 'logs')"

# ─── Aviso sobre temporizadores de reactivacion ───
# -WakeToRun no sirve de nada si el plan de energia los tiene bloqueados.
# 0 = Deshabilitar, 1 = Habilitar, 2 = Solo temporizadores importantes
# (el 2 tambien bloquea las tareas programadas normales).

$rtc = powercfg /query SCHEME_CURRENT SUB_SLEEP RTCWAKE 2>$null
$ac = ($rtc | Select-String -Pattern 'Current AC Power Setting Index:\s*(0x[0-9a-f]+)').Matches.Groups[1].Value
$dc = ($rtc | Select-String -Pattern 'Current DC Power Setting Index:\s*(0x[0-9a-f]+)').Matches.Groups[1].Value

Write-Host ""
Write-Host "  Despertar el equipo suspendido:" -ForegroundColor Cyan
foreach ($m in @(@{n='Enchufado  '; v=$ac; s='AC'}, @{n='Con bateria'; v=$dc; s='DC'})) {
    if ([int]$m.v -eq 1) {
        Write-Host "    $($m.n) : SI (temporizadores de reactivacion habilitados)" -ForegroundColor Green
    } else {
        if ([int]$m.v -eq 2) { $motivo = 'solo temporizadores importantes' } else { $motivo = 'deshabilitados' }
        Write-Host "    $($m.n) : NO - $motivo" -ForegroundColor Yellow
        Write-Host "      Habilitar: powercfg /set$($m.s.ToLower())valueindex SCHEME_CURRENT SUB_SLEEP RTCWAKE 1" -ForegroundColor DarkGray
    }
}
Write-Host "    Con el equipo apagado no hay sincronizacion: nada puede encenderlo." -ForegroundColor DarkGray
Write-Host ""
Write-Host "Probar ahora:  schtasks /run /TN `"Quiniela\SyncPartidos`"" -ForegroundColor Cyan
Write-Host "Desinstalar :  powershell -ExecutionPolicy Bypass -File bin\uninstall_sync_task.ps1" -ForegroundColor Cyan
Write-Host ""
