# ═══════════════════════════════════════════════════════════════
# uninstall_sync_task.ps1 — Elimina la tarea de sincronizacion
# ═══════════════════════════════════════════════════════════════
# Uso:
#   powershell -ExecutionPolicy Bypass -File bin\uninstall_sync_task.ps1
#
# No borra los logs de bin/logs/ — se conservan a proposito.
# ═══════════════════════════════════════════════════════════════

$ErrorActionPreference = 'Stop'

$taskName = 'SyncPartidos'
$taskPath = '\Quiniela\'

$tarea = Get-ScheduledTask -TaskName $taskName -TaskPath $taskPath -ErrorAction SilentlyContinue

if (-not $tarea) {
    Write-Host "La tarea $taskPath$taskName no existe. Nada que hacer." -ForegroundColor Yellow
    exit 0
}

Unregister-ScheduledTask -TaskName $taskName -TaskPath $taskPath -Confirm:$false

Write-Host "Tarea eliminada: $taskPath$taskName" -ForegroundColor Green
Write-Host "Los logs se conservan en bin\logs\" -ForegroundColor DarkGray
