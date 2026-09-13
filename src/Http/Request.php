<?php

// ═══════════════════════════════════════════════════════════════
// Request — Wrapper de petición HTTP
// ═══════════════════════════════════════════════════════════════
// Abstrae el acceso a $_GET, $_POST, php://input.
// Sanitiza y tipea los inputs de forma centralizada.
// ═══════════════════════════════════════════════════════════════

class Request
{
    /** @var string */
    private $method;

    /** @var array Parámetros GET */
    private $query;

    /** @var array Cuerpo parseado (JSON o POST) */
    private $body;

    /**
     * @param string $method   HTTP method
     * @param array  $query    $_GET params
     * @param array  $body     Parsed body
     */
    public function __construct(string $method, array $query, array $body)
    {
        $this->method = strtoupper($method);
        $this->query  = $query;
        $this->body   = $body;
    }

    /**
     * Factory: crea un Request desde las superglobales de PHP.
     *
     * @return self
     */
    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $query  = $_GET;

        // Parsear cuerpo: intentar JSON, fallback a $_POST
        $rawBody = file_get_contents('php://input');
        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            $body = $_POST;
        }

        return new self($method, $query, $body);
    }

    /**
     * Obtiene el método HTTP.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Verifica si es un método específico.
     *
     * @param string $method
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    // ─── Query Parameters (GET) ───

    /**
     * Obtiene un parámetro GET como string.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public function query(string $key, string $default = ''): string
    {
        return isset($this->query[$key]) ? trim((string)$this->query[$key]) : $default;
    }

    /**
     * Obtiene un parámetro GET como entero.
     *
     * @param string $key
     * @param int    $default
     * @return int
     */
    public function queryInt(string $key, int $default = 0): int
    {
        return isset($this->query[$key]) ? intval($this->query[$key]) : $default;
    }

    // ─── Body Parameters (POST/JSON) ───

    /**
     * Obtiene un campo del body como string.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public function input(string $key, string $default = ''): string
    {
        return isset($this->body[$key]) ? trim((string)$this->body[$key]) : $default;
    }

    /**
     * Obtiene un campo del body como entero.
     *
     * @param string $key
     * @param int    $default
     * @return int
     */
    public function inputInt(string $key, int $default = 0): int
    {
        return isset($this->body[$key]) ? intval($this->body[$key]) : $default;
    }

    /**
     * Obtiene un campo del body como float.
     *
     * @param string $key
     * @param float  $default
     * @return float
     */
    public function inputFloat(string $key, float $default = 0.0): float
    {
        return isset($this->body[$key]) ? floatval($this->body[$key]) : $default;
    }

    /**
     * Obtiene un campo del body como array.
     *
     * @param string $key
     * @param array  $default
     * @return array
     */
    public function inputArray(string $key, array $default = []): array
    {
        if (isset($this->body[$key]) && is_array($this->body[$key])) {
            return $this->body[$key];
        }
        return $default;
    }

    /**
     * Verifica si un campo existe en el body.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->body[$key]);
    }

    /**
     * Retorna todo el body como array.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->body;
    }
}
