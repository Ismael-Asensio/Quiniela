<?php

// ═══════════════════════════════════════════════════════════════
// Penalizacion — Entidad de Dominio (DTO)
// ═══════════════════════════════════════════════════════════════
// Representa una penalización con sus propiedades tipadas.
// Encapsula la lógica de hidratación desde un row de base de datos
// y la serialización a array para respuestas JSON.
// ═══════════════════════════════════════════════════════════════

class Penalizacion
{
    /** @var int */
    private $id;

    /** @var int */
    private $idUsuario;

    /** @var int */
    private $puntosRestados;

    /** @var string */
    private $motivo;

    /** @var string 'Activa'|'Anulada' */
    private $estado;

    /** @var string|null */
    private $fechaCreacion;

    /** @var string|null */
    private $fechaModificacion;

    /** @var string|null */
    private $nombreUsuario;

    /** @var string|null */
    private $nombreCompleto;

    /** @var string|null */
    private $creadaPorUsuario;

    /** @var string|null */
    private $modificadaPorUsuario;

    // ─── Estados válidos ───
    const ESTADO_ACTIVA  = 'Activa';
    const ESTADO_ANULADA = 'Anulada';

    const ESTADOS_VALIDOS = [self::ESTADO_ACTIVA, self::ESTADO_ANULADA];

    // ─── Límites de validación ───
    const MOTIVO_MAX_LENGTH = 500;

    /**
     * Constructor privado. Usar los métodos factory para instanciar.
     */
    private function __construct() {}

    /**
     * Hidrata una instancia desde un row asociativo de la base de datos.
     * Maneja la conversión de DateTime a string automáticamente.
     *
     * @param array $row Row de sqlsrv_fetch_array(SQLSRV_FETCH_ASSOC)
     * @return self
     */
    public static function fromDatabaseRow(array $row): self
    {
        $pen = new self();

        $pen->id                  = (int)($row['Id'] ?? 0);
        $pen->idUsuario           = (int)($row['IdUsuario'] ?? 0);
        $pen->puntosRestados      = (int)($row['PuntosRestados'] ?? 0);
        $pen->motivo              = (string)($row['Motivo'] ?? '');
        $pen->estado              = (string)($row['Estado'] ?? '');
        $pen->fechaCreacion       = self::formatDateTime($row['FechaCreacion'] ?? null);
        $pen->fechaModificacion   = self::formatDateTime($row['FechaModificacion'] ?? null);
        $pen->nombreUsuario       = $row['NombreUsuario'] ?? null;
        $pen->nombreCompleto      = $row['NombreCompleto'] ?? null;
        $pen->creadaPorUsuario    = $row['CreadaPorUsuario'] ?? null;
        $pen->modificadaPorUsuario = $row['ModificadaPorUsuario'] ?? null;

        return $pen;
    }

    /**
     * Serializa la entidad a un array asociativo listo para JSON.
     * Mantiene las mismas claves que el código legacy para compatibilidad con el frontend.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'Id'                   => $this->id,
            'IdUsuario'            => $this->idUsuario,
            'PuntosRestados'       => $this->puntosRestados,
            'Motivo'               => $this->motivo,
            'Estado'               => $this->estado,
            'FechaCreacion'        => $this->fechaCreacion,
            'FechaModificacion'    => $this->fechaModificacion,
            'NombreUsuario'        => $this->nombreUsuario,
            'NombreCompleto'       => $this->nombreCompleto,
            'CreadaPorUsuario'     => $this->creadaPorUsuario,
            'ModificadaPorUsuario' => $this->modificadaPorUsuario,
        ];
    }

    // ─── Getters ───

    public function getId(): int              { return $this->id; }
    public function getIdUsuario(): int       { return $this->idUsuario; }
    public function getPuntosRestados(): int   { return $this->puntosRestados; }
    public function getMotivo(): string        { return $this->motivo; }
    public function getEstado(): string        { return $this->estado; }

    public function isActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA;
    }

    // ─── Validación estática ───

    /**
     * Valida si un estado es válido.
     *
     * @param string $estado
     * @return bool
     */
    public static function isEstadoValido(string $estado): bool
    {
        return in_array($estado, self::ESTADOS_VALIDOS, true);
    }

    /**
     * Formatea un DateTime de sqlsrv a string Y-m-d H:i:s.
     * sqlsrv devuelve objetos DateTime nativos de PHP.
     *
     * @param mixed $value
     * @return string|null
     */
    private static function formatDateTime($value): ?string
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value) && !empty($value)) {
            return $value;
        }
        return null;
    }
}
