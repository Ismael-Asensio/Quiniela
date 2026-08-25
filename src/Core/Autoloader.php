<?php

// ═══════════════════════════════════════════════════════════════
// Autoloader PSR-4 Manual — Quiniela 2026
// ═══════════════════════════════════════════════════════════════
// Carga automática de clases basada en convención de carpetas.
// Mapea el namespace lógico a la estructura src/.
// ═══════════════════════════════════════════════════════════════

class Autoloader
{
    /** @var string Directorio base donde vive src/ */
    private static $baseDir;

    /** @var bool Si ya fue registrado */
    private static $registered = false;

    /**
     * Registra el autoloader con spl_autoload_register.
     * Solo se registra una vez (idempotente).
     *
     * @param string|null $baseDir Directorio base (default: dirname de src/)
     * @return void
     */
    public static function register(?string $baseDir = null): void
    {
        if (self::$registered) {
            return;
        }

        self::$baseDir = $baseDir ?: dirname(__DIR__);
        spl_autoload_register([self::class, 'loadClass']);
        self::$registered = true;
    }

    /**
     * Intenta cargar una clase buscando en la estructura de carpetas.
     * Convención: NombreClase → src/{Carpeta}/NombreClase.php
     *
     * Orden de búsqueda:
     *   1. src/Core/
     *   2. src/Domain/
     *   3. src/Repository/
     *   4. src/Service/
     *   5. src/Http/
     *   6. src/Http/Controller/
     *   7. src/Http/Middleware/
     *
     * @param string $className
     * @return void
     */
    public static function loadClass(string $className): void
    {
        $directories = [
            'Core',
            'Domain',
            'Repository',
            'Service',
            'Http',
            'Http/Controller',
            'Http/Middleware',
        ];

        foreach ($directories as $dir) {
            $file = self::$baseDir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $className . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
}
