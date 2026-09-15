<?php

// ═══════════════════════════════════════════════════════════════
// ProcessLock — Candado de archivo entre procesos
// ═══════════════════════════════════════════════════════════════
// Impide que dos sincronizaciones corran a la vez: la tarea
// programada (bin/sync_once.php) y el botón "Sincronizar" del admin
// (SyncController::syncPartidos) comparten el mismo candado.
//
// Hace falta porque SyncService decide insertar con findByApiId()
// seguido de create(), sin bloqueo de fila en medio: dos pasadas
// simultáneas pueden llegar a insertar el mismo partido dos veces.
//
// El candado se libera solo cuando el proceso termina — el sistema
// operativo cierra el descriptor incluso ante un error fatal.
// ═══════════════════════════════════════════════════════════════

class ProcessLock
{
    /** @var string Ruta del archivo de candado */
    private $ruta;

    /** @var resource|null Descriptor abierto mientras se posee el candado */
    private $handle = null;

    public function __construct(string $ruta)
    {
        $this->ruta = $ruta;
    }

    /**
     * Candado compartido por todos los caminos de sincronización.
     * Centraliza la ruta para que la tarea programada y el controlador
     * no puedan quedar apuntando a archivos distintos.
     *
     * @return self
     */
    public static function sync(): self
    {
        return new self(__DIR__ . '/../../bin/logs/sync.lock');
    }

    /**
     * Intenta tomar el candado sin bloquear.
     *
     * @return bool True si se obtuvo; false si otro proceso lo tiene.
     * @throws \RuntimeException Si el archivo no se puede crear o abrir
     */
    public function adquirir(): bool
    {
        if ($this->handle !== null) {
            return true; // ya lo tiene este mismo proceso
        }

        $dir = dirname($this->ruta);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear el directorio del candado: $dir");
        }

        $handle = @fopen($this->ruta, 'c');
        if ($handle === false) {
            throw new \RuntimeException("No se pudo abrir el candado: {$this->ruta}");
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        // Rastro de quién lo tiene — sirve para diagnosticar un candado colgado
        ftruncate($handle, 0);
        fwrite($handle, getmypid() . ' ' . Config::now()->format('Y-m-d H:i:s') . PHP_EOL);
        fflush($handle);

        $this->handle = $handle;
        return true;
    }

    /**
     * Libera el candado explícitamente.
     *
     * No es obligatorio llamarlo: al terminar el proceso el sistema lo
     * libera solo. El archivo NO se borra a propósito — borrarlo mientras
     * otro proceso lo tiene abierto reintroduce la carrera que evita.
     *
     * @return void
     */
    public function liberar(): void
    {
        if ($this->handle === null) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }
}
