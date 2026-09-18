<?php

// ═══════════════════════════════════════════════════════════════
// CliLogger — Log de archivo para procesos CLI
// ═══════════════════════════════════════════════════════════════
// El Programador de tareas de Windows solo guarda un código de salida
// por ejecución. Sin un log propio, la sincronización automática es
// una caja negra: no hay forma de saber qué sincronizó, cuándo, ni por
// qué falló.
//
// Escribe una línea por evento en <dir>/sync_YYYY-MM-DD.log, y también
// a STDOUT cuando hay consola (ejecución manual).
// ═══════════════════════════════════════════════════════════════

class CliLogger
{
    /** @var int Días que se conservan los logs antes de purgarse */
    private const DIAS_RETENCION = 30;

    /** @var string Directorio donde viven los archivos de log */
    private $dir;

    /** @var string Prefijo del nombre de archivo */
    private $prefijo;

    /** @var bool Si además hay que escribir a STDOUT */
    private $aStdout;

    public function __construct(string $dir, string $prefijo = 'sync', ?bool $aStdout = null)
    {
        $this->dir = $dir;
        $this->prefijo = $prefijo;
        $this->aStdout = $aStdout === null ? (PHP_SAPI === 'cli' && defined('STDOUT')) : $aStdout;

        if (!is_dir($this->dir) && !@mkdir($this->dir, 0777, true) && !is_dir($this->dir)) {
            throw new \RuntimeException("No se pudo crear el directorio de logs: {$this->dir}");
        }
    }

    public function info(string $mensaje): void
    {
        $this->escribir('INFO', $mensaje);
    }

    public function warn(string $mensaje): void
    {
        $this->escribir('WARN', $mensaje);
    }

    public function error(string $mensaje): void
    {
        $this->escribir('ERROR', $mensaje);
    }

    /**
     * Ruta del archivo de log del día actual.
     *
     * @return string
     */
    public function archivoActual(): string
    {
        return $this->dir . DIRECTORY_SEPARATOR
            . $this->prefijo . '_' . Config::now()->format('Y-m-d') . '.log';
    }

    /**
     * Borra los logs con más de DIAS_RETENCION días de antigüedad.
     * Se llama al arrancar cada pasada: sin esto el directorio crece
     * sin límite en una tarea que corre cada pocos minutos.
     *
     * @return int Archivos borrados
     */
    public function purgar(): int
    {
        $limite = Config::now()->getTimestamp() - (self::DIAS_RETENCION * 86400);
        $borrados = 0;

        foreach (glob($this->dir . DIRECTORY_SEPARATOR . $this->prefijo . '_*.log') ?: [] as $archivo) {
            if (@filemtime($archivo) < $limite && @unlink($archivo)) {
                $borrados++;
            }
        }

        return $borrados;
    }

    /**
     * @param string $nivel   INFO | WARN | ERROR
     * @param string $mensaje
     * @return void
     */
    private function escribir(string $nivel, string $mensaje): void
    {
        $linea = '[' . Config::now()->format('Y-m-d H:i:s') . '] '
            . str_pad($nivel, 5) . ' ' . $mensaje . PHP_EOL;

        // LOCK_EX: la tarea programada y el admin pueden escribir a la vez
        @file_put_contents($this->archivoActual(), $linea, FILE_APPEND | LOCK_EX);

        if ($this->aStdout) {
            fwrite(STDOUT, $linea);
        }
    }
}
