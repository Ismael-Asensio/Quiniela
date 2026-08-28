<?php

// ═══════════════════════════════════════════════════════════════
// Database — Singleton de Conexión sqlsrv
// ═══════════════════════════════════════════════════════════════
// Encapsula la conexión a SQL Server.
// Garantiza una sola conexión por request (Singleton).
// ═══════════════════════════════════════════════════════════════

class Database
{
    /** @var resource|null Conexión activa */
    private static $connection = null;

    /**
     * Obtiene la conexión a SQL Server.
     * Si no existe, la crea. Si ya existe, la reutiliza.
     *
     * @return resource Recurso de conexión sqlsrv
     * @throws \RuntimeException Si la conexión falla
     */
    public static function getConnection()
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $options = [
            'Database'     => Config::dbName(),
            'UID'          => Config::dbUser(),
            'PWD'          => Config::dbPassword(),
            'CharacterSet' => Config::dbCharset(),
        ];

        $conn = sqlsrv_connect(Config::dbServer(), $options);

        if ($conn === false) {
            throw new \RuntimeException(
                'Database connection failed: ' . json_encode(sqlsrv_errors())
            );
        }

        self::$connection = $conn;
        return self::$connection;
    }

    /**
     * Cierra la conexión activa.
     *
     * @return void
     */
    public static function close(): void
    {
        if (self::$connection !== null) {
            sqlsrv_close(self::$connection);
            self::$connection = null;
        }
    }

    /**
     * Ejecuta una consulta preparada y retorna el statement.
     * Centraliza el manejo de errores de sqlsrv_query.
     *
     * @param string $sql    SQL con placeholders ?
     * @param array  $params Parámetros posicionales
     * @return resource Statement
     * @throws \RuntimeException Si la query falla
     */
    public static function query(string $sql, array $params = [])
    {
        $conn = self::getConnection();
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new \RuntimeException(
                'Query failed: ' . json_encode(sqlsrv_errors()) . ' | SQL: ' . substr($sql, 0, 200)
            );
        }

        return $stmt;
    }

    /**
     * Ejecuta una consulta y retorna todos los rows como array asociativo.
     *
     * @param string $sql
     * @param array  $params
     * @return array
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        $results = [];

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $results[] = $row;
        }

        return $results;
    }

    /**
     * Ejecuta una consulta y retorna el primer row o null.
     *
     * @param string $sql
     * @param array  $params
     * @return array|null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Ejecuta una consulta de escritura (INSERT, UPDATE, DELETE).
     * Retorna el número de filas afectadas.
     *
     * @param string $sql
     * @param array  $params
     * @return int Filas afectadas
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::query($sql, $params);
        $rowsAffected = sqlsrv_rows_affected($stmt);

        return $rowsAffected !== false ? $rowsAffected : 0;
    }

    /**
     * Inicia una transacción explícita.
     *
     * @return void
     * @throws \RuntimeException Si no se pudo iniciar
     */
    public static function beginTransaction(): void
    {
        $conn = self::getConnection();
        if (sqlsrv_begin_transaction($conn) === false) {
            throw new \RuntimeException(
                'No se pudo iniciar la transacción: ' . json_encode(sqlsrv_errors())
            );
        }
    }

    /**
     * Confirma la transacción activa.
     *
     * @return void
     * @throws \RuntimeException Si el commit falla
     */
    public static function commit(): void
    {
        if (sqlsrv_commit(self::getConnection()) === false) {
            throw new \RuntimeException(
                'No se pudo confirmar la transacción: ' . json_encode(sqlsrv_errors())
            );
        }
    }

    /**
     * Revierte la transacción activa.
     *
     * @return void
     */
    public static function rollback(): void
    {
        // No lanzar si el rollback falla: normalmente se llama desde un catch
        // y no queremos enmascarar la excepción original.
        @sqlsrv_rollback(self::getConnection());
    }

    /**
     * Ejecuta $fn dentro de una transacción. Hace commit si $fn retorna
     * normalmente y rollback si lanza cualquier excepción, propagándola
     * después. Uso recomendado para toda operación multi-tabla que
     * involucre dinero (apuestas, membresías, recálculo de puntos).
     *
     * @template T
     * @param callable():T $fn
     * @return T
     * @throws \Throwable Lo que sea que lance $fn
     */
    public static function transaction(callable $fn)
    {
        self::beginTransaction();

        try {
            $result = $fn();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    /**
     * Previene la instanciación directa (Singleton estático).
     */
    private function __construct() {}
}
