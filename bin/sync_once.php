<?php

// ═══════════════════════════════════════════════════════════════
// sync_once.php — Sincronización de un solo disparo
// ═══════════════════════════════════════════════════════════════
// Punto de entrada de la tarea programada de Windows. Hace exactamente
// la misma llamada que el botón "Sincronizar" del admin, sin sesión ni
// HTTP de por medio. Ver README.md § Sincronización automática.
//
// Uso:
//   php bin/sync_once.php              sincroniza
//   php bin/sync_once.php --dry-run    comprueba BD y config, no escribe nada
//
// Códigos de salida (los lee el Programador de tareas como "Último resultado"):
//   0  OK
//   1  error recuperable (ESPN caída, timeout) — se reintenta en la siguiente pasada
//   2  ya había otra sincronización en curso (no es un error)
//   3  error fatal (falta config/local.php, BD inalcanzable, disco lleno)
// ═══════════════════════════════════════════════════════════════

// Este archivo no debe ser alcanzable por web: vive bajo el DocumentRoot
// de AppServ y no lleva guard de autenticación.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

const EXIT_OK          = 0;
const EXIT_RECUPERABLE = 1;
const EXIT_BLOQUEADO   = 2;
const EXIT_FATAL       = 3;

$dryRun = in_array('--dry-run', $argv, true);

// Los error_log() de SyncService acaban aquí en vez de en stderr (que
// bajo el shim .vbs no lo lee nadie).
@ini_set('error_log', __DIR__ . '/logs/php_error.log');

try {
    $logger = new CliLogger(__DIR__ . '/logs');
    $logger->purgar();
} catch (\Throwable $e) {
    // Sin log no hay nada que registrar: solo queda el código de salida.
    exit(EXIT_FATAL);
}

try {
    $lock = ProcessLock::sync();

    if (!$lock->adquirir()) {
        $logger->warn('bloqueado: ya hay una sincronizacion en curso');
        exit(EXIT_BLOQUEADO);
    }

    if ($dryRun) {
        $competicion = Config::competicionActiva();

        $row = Database::fetchOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN Estado IN ('FT','AET','PEN') THEN 1 ELSE 0 END) AS finalizados
               FROM Partidos WHERE IdCompeticion = ?",
            [$competicion]
        );

        $logger->info(sprintf(
            'dry-run ok competicion=%d partidos=%d finalizados=%d; una pasada real llamaria a %s',
            $competicion,
            (int) ($row['total'] ?? 0),
            (int) ($row['finalizados'] ?? 0),
            Config::ESPN_API_SCOREBOARD
        ));

        exit(EXIT_OK);
    }

    $inicio = microtime(true);
    $syncService = new SyncService();
    $res = $syncService->sincronizarPartidos();
    $dur = round(microtime(true) - $inicio, 1);

    if (!$res['success']) {
        $logger->error("sync fallo: {$res['error']} dur={$dur}s");
        exit(EXIT_RECUPERABLE);
    }

    $s = $res['stats'];
    $logger->info(sprintf(
        'sync ok procesados=%d nuevos=%d actualizados=%d evaluados=%d dur=%ss',
        $s['procesados'],
        $s['nuevos'],
        $s['actualizados'],
        $s['evaluados'],
        $dur
    ));

    exit(EXIT_OK);

} catch (\Throwable $e) {
    $logger->error('fatal: ' . $e->getMessage());
    exit(EXIT_FATAL);
}
