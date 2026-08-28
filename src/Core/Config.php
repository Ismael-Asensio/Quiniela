<?php

// ═══════════════════════════════════════════════════════════════
// Config — Constantes del Sistema Quiniela 2026
// ═══════════════════════════════════════════════════════════════
// Extraído de config/database.php monolítico.
// Solo constantes y configuración. Sin lógica de negocio.
// ═══════════════════════════════════════════════════════════════

class Config
{
    // ─── Zona Horaria ───
    const TIMEZONE = 'America/Managua';

    // ─── Administradores del sistema ───
    const ADMINS = ['iasensio', 'cvande'];

    // ─── Competición Activa ───
    // 1 = Mundial 2026, 2 = Copa Centroamericana. Fallback si la lectura
    // de la tabla Competiciones falla; el valor real se lee de la BD
    // (columna Activa) vía competicionActiva() — ver Fase 6.1 del plan
    // de migración. No referenciar esta constante directamente fuera de
    // Config; usar Config::competicionActiva().
    private const COMPETICION_ACTIVA_FALLBACK = 2;

    // ─── Orígenes permitidos para CORS ───
    // La app es same-origin hoy (index.php y api/ sirven del mismo host;
    // todo el JS hace fetch() con rutas relativas, no hay ningún origen
    // cruzado en uso). Se deja vacío a propósito: un fetch same-origin no
    // necesita cabeceras CORS. Si algún día el frontend se sirve desde
    // otro dominio, agregarlo aquí explícitamente.
    const ALLOWED_ORIGINS = [];

    // ─── API ESPN Scoreboard (pública, no requiere credencial) ───
    const ESPN_API_SCOREBOARD = 'https://site.api.espn.com/apis/site/v2/sports/soccer/concacaf.central.american.cup/scoreboard';

    // ─── Credenciales y secretos ───
    // SIN valores reales en este archivo (versionado) desde la Fase 2.1
    // del plan de migración. Los valores reales viven exclusivamente en
    // config/local.php (no versionado, ver .gitignore). Copiar
    // config/local.example.php a config/local.php y completar ahí.
    // Estos defaults quedan vacíos a propósito: si config/local.php no
    // existe, la app debe fallar de forma explícita al conectar en vez
    // de usar credenciales obsoletas o silenciosamente incorrectas.
    private const DEFAULT_DB_SERVER   = '';
    private const DEFAULT_DB_NAME     = '';
    private const DEFAULT_DB_USER     = '';
    private const DEFAULT_DB_PASSWORD = '';
    private const DEFAULT_DB_CHARSET  = 'UTF-8';

    // API World Cup 2026 — desactivada
    private const DEFAULT_WORLDCUP_API_BASE  = 'https://worldcup26.ir';
    private const DEFAULT_WORLDCUP_API_TOKEN = '';

    private const DEFAULT_ODDS_API_KEY  = '';
    private const DEFAULT_ODDS_API_BASE = 'https://api.the-odds-api.com/v4';

    /** @var array|null Overrides cargados de config/local.php (null = aún no leído) */
    private static $overrides = null;

    /** @var int|null Competición activa leída de la BD, cacheada por request */
    private static $competicionActiva = null;

    /**
     * Lee config/local.php una sola vez (si existe) y cachea su contenido.
     * El archivo debe retornar un array asociativo; las claves ausentes
     * caen al valor por defecto embebido en esta clase.
     *
     * @return array
     */
    private static function overrides(): array
    {
        if (self::$overrides === null) {
            $path = __DIR__ . '/../../config/local.php';
            $data = file_exists($path) ? (require $path) : [];
            self::$overrides = is_array($data) ? $data : [];
        }
        return self::$overrides;
    }

    public static function dbServer(): string
    {
        return self::overrides()['DB_SERVER'] ?? self::DEFAULT_DB_SERVER;
    }

    public static function dbName(): string
    {
        return self::overrides()['DB_NAME'] ?? self::DEFAULT_DB_NAME;
    }

    public static function dbUser(): string
    {
        return self::overrides()['DB_USER'] ?? self::DEFAULT_DB_USER;
    }

    public static function dbPassword(): string
    {
        return self::overrides()['DB_PASSWORD'] ?? self::DEFAULT_DB_PASSWORD;
    }

    public static function dbCharset(): string
    {
        return self::overrides()['DB_CHARSET'] ?? self::DEFAULT_DB_CHARSET;
    }

    public static function worldcupApiBase(): string
    {
        return self::overrides()['WORLDCUP_API_BASE'] ?? self::DEFAULT_WORLDCUP_API_BASE;
    }

    public static function worldcupApiToken(): string
    {
        return self::overrides()['WORLDCUP_API_TOKEN'] ?? self::DEFAULT_WORLDCUP_API_TOKEN;
    }

    public static function oddsApiKey(): string
    {
        return self::overrides()['ODDS_API_KEY'] ?? self::DEFAULT_ODDS_API_KEY;
    }

    public static function oddsApiBase(): string
    {
        return self::overrides()['ODDS_API_BASE'] ?? self::DEFAULT_ODDS_API_BASE;
    }

    /**
     * Id de la competición marcada Activa = 1 en la tabla Competiciones,
     * cacheada por request. Si la consulta falla (BD no disponible aún,
     * tabla vacía) o no hay ninguna marcada activa, cae a
     * COMPETICION_ACTIVA_FALLBACK. No usar en el arranque del autoloader
     * ni antes de que exista una conexión — sólo en tiempo de request.
     */
    public static function competicionActiva(): int
    {
        if (self::$competicionActiva === null) {
            try {
                $row = Database::fetchOne("SELECT TOP 1 Id FROM Competiciones WHERE Activa = 1 ORDER BY Id ASC");
                self::$competicionActiva = $row ? (int)$row['Id'] : self::COMPETICION_ACTIVA_FALLBACK;
            } catch (\Throwable $e) {
                self::$competicionActiva = self::COMPETICION_ACTIVA_FALLBACK;
            }
        }
        return self::$competicionActiva;
    }

    // ─── Roles del sistema ───
    const ROL_ADMIN    = 1;
    const ROL_TESORERO = 2;
    const ROL_MIEMBRO  = 3;
    const ROL_USUARIO  = 4;

    // ─── Fases del torneo con multiplicadores ───
    const FASES = [
        'group'  => ['fase' => 'Grupos',        'multiplicador' => 1],
        'r32'    => ['fase' => 'Dieciseisavos', 'multiplicador' => 1],
        'r16'    => ['fase' => 'Octavos',       'multiplicador' => 1],
        'qf'     => ['fase' => 'Cuartos',       'multiplicador' => 1],
        'sf'     => ['fase' => 'Semifinales',   'multiplicador' => 1],
        'third'  => ['fase' => 'Tercer Lugar',  'multiplicador' => 1],
        'final'  => ['fase' => 'Final',         'multiplicador' => 1],
    ];

    // ─── Orden de fases del torneo (para tablas por ronda) ───
    const FASES_ORDEN = [
        'Grupos',
        'Dieciseisavos',
        'Octavos',
        'Cuartos',
        'Semifinales',
        'Tercer Lugar',
        'Final',
    ];

    // ─── Estados de partidos finalizados ───
    const ESTADOS_FINALIZADOS = ['FT', 'AET', 'PEN'];
    const ESTADOS_BLOQUEADOS  = ['FT', 'AET', 'PEN', 'CANC', 'ABD', 'AWD', 'WO'];
    const ESTADOS_EN_VIVO     = ['FT', 'AET', 'PEN', 'CANC', 'ABD', '1H', '2H', 'HT', 'ET'];

    // ─── Equipos apostables ───
    const EQUIPOS_APUESTA_VALIDOS = ['Local', 'Empate', 'Visitante'];

    /**
     * Verifica si un nombre de usuario es administrador.
     *
     * @param string $username
     * @return bool
     */
    public static function isAdmin(string $username): bool
    {
        return in_array($username, self::ADMINS, true);
    }

    /**
     * Obtiene la fecha/hora actual en la zona horaria de la app.
     *
     * @return \DateTime
     */
    public static function now(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone(self::TIMEZONE));
    }

    /**
     * Mapea un tipo de fase de la API a fase local y multiplicador.
     *
     * @param string $type
     * @return array ['fase' => string, 'multiplicador' => int]
     */
    public static function mapFase(string $type): array
    {
        $key = strtolower(trim($type));
        return self::FASES[$key] ?? ['fase' => 'Grupos', 'multiplicador' => 1];
    }

    /**
     * Retorna las fases del torneo desde una fase específica en adelante.
     * Ejemplo: getFasesDesde('Dieciseisavos') => ['Dieciseisavos','Octavos','Cuartos','Semifinales','Tercer Lugar','Final']
     *
     * @param string $faseInicio
     * @return array
     */
    public static function getFasesDesde(string $faseInicio): array
    {
        $index = array_search($faseInicio, self::FASES_ORDEN, true);
        if ($index === false) {
            return self::FASES_ORDEN; // Si no se encuentra, retornar todas
        }
        return array_slice(self::FASES_ORDEN, $index);
    }

    /**
     * Mapea el estado de la API a código corto local.
     *
     * @param string $finished
     * @param string $timeElapsed
     * @return string
     */
    public static function mapEstado(string $finished, string $timeElapsed): string
    {
        if (strtoupper($finished) === 'TRUE') {
            return 'FT';
        }

        $te = strtolower(trim($timeElapsed));

        switch ($te) {
            case 'notstarted':
                return 'NS';
            case 'live':
            case 'in progress':
                return '1H';
            case 'halftime':
                return 'HT';
            default:
                if (is_numeric($te)) {
                    return intval($te) <= 45 ? '1H' : '2H';
                }
                return 'NS';
        }
    }

    /**
     * Calcula puntos de una predicción.
     * Exacto = 3, Resultado correcto = 1, Fallo = 0.
     *
     * @param int      $predLocal
     * @param int      $predVisitante
     * @param int|null $realLocal
     * @param int|null $realVisitante
     * @param int      $multiplicador
     * @return int
     */
    public static function calcularPuntos(
        int $predLocal,
        int $predVisitante,
        ?int $realLocal,
        ?int $realVisitante,
        int $multiplicador = 1
    ): int {
        if ($realLocal === null || $realVisitante === null) {
            return 0;
        }

        // Acierto exacto
        if ($predLocal == $realLocal && $predVisitante == $realVisitante) {
            return 3 * $multiplicador;
        }

        // Resultado correcto
        $resultadoReal = $realLocal > $realVisitante ? 'L' : ($realLocal < $realVisitante ? 'V' : 'E');
        $resultadoPred = $predLocal > $predVisitante ? 'L' : ($predLocal < $predVisitante ? 'V' : 'E');

        if ($resultadoReal === $resultadoPred) {
            return 1 * $multiplicador;
        }

        return 0;
    }
}

// Inicializar timezone al cargar
date_default_timezone_set(Config::TIMEZONE);
