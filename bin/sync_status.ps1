# ===============================================================
# sync_status.ps1 - Estado de la tarea de sincronizacion
# ===============================================================
# Uso:
#   powershell -ExecutionPolicy Bypass -File bin\sync_status.ps1
#       Muestra el estado una vez y sale.
#
#   powershell -ExecutionPolicy Bypass -File bin\sync_status.ps1 -Vigilar
#       Se queda ejecutando indefinidamente. NO redibuja: escribe una
#       linea de latido cada 20 s y va volcando las pasadas nuevas segun
#       ocurren. Es el modo para dejar una consola abierta.
#
#   powershell -ExecutionPolicy Bypass -File bin\sync_status.ps1 -Panel
#       Panel que se redibuja entero en cada refresco (pantalla fija).
#
#   powershell -ExecutionPolicy Bypass -File bin\sync_status.ps1 -Seguir
#       Engancha el log crudo en vivo, sin latido.
#
# Ctrl+C para salir de cualquier modo continuo.
# ===============================================================

param(
    [int]$Lineas = 12,
    [switch]$Vigilar,
    [switch]$Panel,
    [switch]$Seguir,
    [int]$RefrescoSegundos = 20
)

$ErrorActionPreference = 'Stop'

$script:binDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$script:logDir   = Join-Path $script:binDir 'logs'
$script:taskName = 'SyncPartidos'
$script:taskPath = '\Quiniela\'

# El nombre del log cambia a medianoche: se recalcula en cada vuelta.
function Get-LogHoy {
    Join-Path $script:logDir ("sync_" + (Get-Date -Format "yyyy-MM-dd") + ".log")
}

function Get-InfoTarea {
    $tarea = Get-ScheduledTask -TaskName $script:taskName -TaskPath $script:taskPath -ErrorAction SilentlyContinue
    if (-not $tarea) { return $null }

    $info = Get-ScheduledTaskInfo -TaskName $script:taskName -TaskPath $script:taskPath

    return [pscustomobject]@{
        Estado    = $tarea.State
        Intervalo = $tarea.Triggers[0].Repetition.Interval
        UltimaEjec= $info.LastRunTime
        Resultado = [int]$info.LastTaskResult
        Proxima   = $info.NextRunTime
    }
}

function Get-TextoResultado {
    param([int]$rc)
    $codigos = @{
        0 = 'correcto'
        1 = 'error recuperable (ESPN caida) - reintenta en la siguiente pasada'
        2 = 'ya habia otra sincronizacion en curso'
        3 = 'error fatal (config/BD)'
    }
    if ($codigos.ContainsKey($rc)) { return $codigos[$rc] }
    return 'codigo no esperado'
}

function Get-ColorResultado {
    param([int]$rc)
    if     ($rc -eq 0) { return 'Green' }
    elseif ($rc -eq 3) { return 'Red' }
    else               { return 'Yellow' }
}

function Write-LineaLog {
    param([string]$Linea, [string]$Prefijo = '')
    if     ($Linea -match '\sERROR\s') { $c = 'Yellow' }
    elseif ($Linea -match '\sWARN\s')  { $c = 'DarkGray' }
    else                               { $c = 'Green' }
    Write-Host "$Prefijo$Linea" -ForegroundColor $c
}

function Show-Tarea {
    param([switch]$ConCuentaAtras)

    $t = Get-InfoTarea
    if (-not $t) {
        Write-Host "TAREA  $($script:taskPath)$($script:taskName) -> NO REGISTRADA" -ForegroundColor Red
        Write-Host "  Instalar: powershell -ExecutionPolicy Bypass -File bin\install_sync_task.ps1"
        return
    }

    if ($t.Estado -eq 'Ready' -or $t.Estado -eq 'Running') { $colorEstado = 'Green' } else { $colorEstado = 'Red' }

    Write-Host "TAREA  $($script:taskPath)$($script:taskName)" -ForegroundColor Cyan
    Write-Host "  Estado           : " -NoNewline
    Write-Host "$($t.Estado)" -ForegroundColor $colorEstado
    Write-Host "  Intervalo        : $($t.Intervalo)"
    Write-Host "  Ultima ejecucion : $($t.UltimaEjec)"
    Write-Host "  Ultimo resultado : " -NoNewline
    Write-Host "$($t.Resultado)  ($(Get-TextoResultado $t.Resultado))" -ForegroundColor (Get-ColorResultado $t.Resultado)

    if ($ConCuentaAtras -and $t.Proxima) {
        $restante = $t.Proxima - (Get-Date)
        if ($restante.TotalSeconds -lt 0) { $restante = New-TimeSpan -Seconds 0 }
        Write-Host ("  Proxima pasada   : $($t.Proxima)  (en {0:mm\:ss})" -f $restante) -ForegroundColor Cyan
    } else {
        Write-Host "  Proxima pasada   : $($t.Proxima)"
    }
}

function Show-Log {
    param([int]$Lineas)

    $logHoy = Get-LogHoy

    Write-Host ""
    if (-not (Test-Path $logHoy)) {
        Write-Host "LOG    todavia no hay pasadas hoy" -ForegroundColor DarkGray
        return
    }

    # OJO: no llamar a esta variable $lineas - PowerShell no distingue
    # mayusculas y chocaria con el parametro [int]$Lineas.
    $log = Get-Content $logHoy -Encoding UTF8

    $ok       = @($log | Where-Object { $_ -match '\sINFO\s+sync ok' })
    $errores  = @($log | Where-Object { $_ -match '\sERROR\s' })
    $bloqueos = @($log | Where-Object { $_ -match '\sWARN\s+bloqueado' })

    Write-Host "LOG    $(Split-Path -Leaf $logHoy)" -ForegroundColor Cyan
    Write-Host "  Sincronizaciones OK : $($ok.Count)" -ForegroundColor Green
    Write-Host "  Errores             : $($errores.Count)" -ForegroundColor Yellow
    Write-Host "  Bloqueos (normal)   : $($bloqueos.Count)" -ForegroundColor DarkGray

    # Aviso solo si lleva mucho rato sin una pasada buena: los errores
    # sueltos de ESPN son esperados y no significan nada.
    if ($ok.Count -gt 0 -and $ok[-1] -match '^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]') {
        $minutos = [int]((Get-Date) - [datetime]$Matches[1]).TotalMinutes
        if ($minutos -gt 30) {
            Write-Host "  AVISO: $minutos minutos sin una sincronizacion correcta." -ForegroundColor Red
        } else {
            Write-Host "  Ultima OK hace      : $minutos min" -ForegroundColor Green
        }
    }

    Write-Host ""
    Write-Host "ULTIMAS $Lineas LINEAS" -ForegroundColor Cyan
    $log | Select-Object -Last $Lineas | ForEach-Object { Write-LineaLog $_ }
}

# ═══════════════════════════════════════════════════════════════
# Modo -Vigilar: se queda ejecutando, sin redibujar
# ═══════════════════════════════════════════════════════════════

if ($Vigilar) {
    Write-Host ""
    Show-Tarea
    Show-Log -Lineas $Lineas
    Write-Host ""
    Write-Host "  Vigilando cada $RefrescoSegundos s. Ctrl+C para salir." -ForegroundColor DarkGray
    Write-Host "  Las pasadas nuevas apareceran marcadas con >>." -ForegroundColor DarkGray
    Write-Host ""

    # Estado del seguimiento del log entre vueltas
    $logActual = Get-LogHoy
    if (Test-Path $logActual) { $vistas = @(Get-Content $logActual -Encoding UTF8).Count } else { $vistas = 0 }
    $fallosTarea = 0
    $ultimoLatido = Get-Date

    while ($true) {
        Start-Sleep -Seconds $RefrescoSegundos

        # ─── Detectar suspension del equipo ───
        # Si entre dos latidos ha pasado mucho mas de lo que dormimos, el
        # equipo estuvo suspendido: ni el monitor ni la tarea corrieron.
        # Sin esto la suspension solo se ve como un salto de reloj.
        $ahoraDt = Get-Date
        $huecoSeg = [int]($ahoraDt - $ultimoLatido).TotalSeconds
        if ($huecoSeg -gt ($RefrescoSegundos * 3)) {
            $min = [math]::Round($huecoSeg / 60, 1)
            Write-Host ("  --- equipo suspendido ~$min min ($($ultimoLatido.ToString('HH:mm:ss')) -> $($ahoraDt.ToString('HH:mm:ss'))): sin sincronizacion ---") -ForegroundColor Yellow
        }
        $ultimoLatido = $ahoraDt

        # ─── Volcar las lineas nuevas del log ───
        $logHoy = Get-LogHoy
        if ($logHoy -ne $logActual) {
            # Cambio de dia: el log rota de archivo
            $logActual = $logHoy
            $vistas = 0
            Write-Host "  --- nuevo archivo de log: $(Split-Path -Leaf $logHoy) ---" -ForegroundColor DarkGray
        }

        if (Test-Path $logHoy) {
            $log = @(Get-Content $logHoy -Encoding UTF8)
            # Si encogio, el log fue purgado o reescrito: reiniciar el contador
            if ($log.Count -lt $vistas) { $vistas = 0 }
            if ($log.Count -gt $vistas) {
                $log[$vistas..($log.Count - 1)] | ForEach-Object { Write-LineaLog $_ '  >> ' }
                $vistas = $log.Count
            }
        }

        # ─── Latido ───
        $t = Get-InfoTarea
        $ahora = Get-Date -Format 'HH:mm:ss'

        # Justo al reanudar de suspension, el servicio del Programador
        # todavia no responde y Get-ScheduledTask devuelve null durante unos
        # segundos. No es que la tarea haya desaparecido: solo se avisa si
        # falla varias veces seguidas.
        if (-not $t) {
            $fallosTarea++
            if ($fallosTarea -ge 3) {
                Write-Host "  $ahora  TAREA NO REGISTRADA (${fallosTarea} consultas seguidas sin respuesta)" -ForegroundColor Red
            } else {
                Write-Host "  $ahora  (Programador aun no responde, reintentando)" -ForegroundColor DarkGray
            }
            continue
        }
        $fallosTarea = 0

        $restante = $t.Proxima - (Get-Date)
        if ($restante.TotalSeconds -lt 0) { $restante = New-TimeSpan -Seconds 0 }

        $latido = "  {0}  tarea={1}  ultimo={2}  proxima en {3:mm\:ss}" -f $ahora, $t.Estado, $t.Resultado, $restante

        if ($t.Estado -ne 'Ready' -and $t.Estado -ne 'Running') {
            Write-Host $latido -ForegroundColor Red
        } else {
            Write-Host $latido -ForegroundColor (Get-ColorResultado $t.Resultado)
        }
    }
}

# ═══════════════════════════════════════════════════════════════
# Modo -Panel: redibuja la pantalla entera en cada refresco
# ═══════════════════════════════════════════════════════════════

if ($Panel) {
    try {
        while ($true) {
            # Clear-Host lanza si no hay consola real (salida redirigida a
            # archivo o a otro proceso): en ese caso simplemente no limpia.
            try { Clear-Host } catch { }
            Write-Host ""
            Write-Host "  QUINIELA - VIGILANCIA DE SINCRONIZACION      $(Get-Date -Format 'HH:mm:ss')" -ForegroundColor White
            Write-Host "  ============================================================" -ForegroundColor DarkGray
            Write-Host ""
            Show-Tarea -ConCuentaAtras
            Show-Log -Lineas $Lineas
            Write-Host ""
            Write-Host "  Refresco cada $RefrescoSegundos s. Ctrl+C para salir." -ForegroundColor DarkGray
            Start-Sleep -Seconds $RefrescoSegundos
        }
    } finally {
        Write-Host ""
    }
    return
}

# ═══════════════════════════════════════════════════════════════
# Modo una sola vez
# ═══════════════════════════════════════════════════════════════

Write-Host ""
Show-Tarea
Show-Log -Lineas $Lineas
Write-Host ""

if ($Seguir) {
    Write-Host "Siguiendo el log crudo en vivo. Ctrl+C para salir." -ForegroundColor Cyan
    Write-Host "(el log solo escribe una linea cada 5 min; usa -Vigilar para ver latido)" -ForegroundColor DarkGray
    Write-Host ""
    Get-Content (Get-LogHoy) -Encoding UTF8 -Wait -Tail 0
}
