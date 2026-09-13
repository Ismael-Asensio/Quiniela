<?php

// ═══════════════════════════════════════════════════════════════
// JsonResponse — Helper de Respuestas HTTP Estandarizadas
// ═══════════════════════════════════════════════════════════════
// Centraliza la generación de respuestas JSON con códigos HTTP
// correctos. Aplica el Principio de Responsabilidad Única:
// ninguna otra clase debe manipular headers ni hacer echo.
// ═══════════════════════════════════════════════════════════════

class JsonResponse
{
    /**
     * Envía una respuesta JSON exitosa y termina la ejecución.
     * Permite flexibilidad de parámetros para facilitar la refactorización.
     *
     * @param mixed  $data    Datos a incluir en la respuesta (puede ser null)
     * @param string $message Mensaje opcional de éxito
     * @param int    $code    Código HTTP (default: 200)
     * @return void
     */
    public static function success($data = [], string $message = '', int $code = 200): void
    {
        http_response_code($code);
        
        $payload = ['success' => true];
        
        if ($message !== '') {
            $payload['message'] = $message;
        }
        
        if (is_array($data)) {
            $payload = array_merge($payload, $data);
        } elseif ($data !== null) {
            $payload['data'] = $data;
        }
        
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Envía una respuesta JSON genérica sin envolverla forzosamente.
     *
     * @param array $data Datos crudos
     * @param int   $code Código HTTP
     * @return void
     */
    public static function send(array $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Envía una respuesta JSON de error y termina la ejecución.
     *
     * @param string $message  Mensaje de error legible
     * @param int    $code     Código HTTP (default: 400)
     * @param array  $extra    Campos adicionales (ej: 'errores' => [...])
     * @return void
     */
    public static function error(string $message, int $code = 400, array $extra = []): void
    {
        http_response_code($code);
        echo json_encode(array_merge(['success' => false, 'error' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Envía una respuesta JSON de error interno del servidor.
     * Oculta detalles técnicos en producción.
     *
     * @param string $message   Mensaje público
     * @param mixed  $details   Detalles técnicos (solo para logging futuro)
     * @return void
     */
    public static function serverError(string $message, $details = null): void
    {
        http_response_code(500);
        $response = ['success' => false, 'error' => $message];
        // En desarrollo se pueden incluir detalles; en producción, ocultarlos
        if ($details !== null) {
            $response['details'] = $details;
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Envía una respuesta 405 Method Not Allowed.
     *
     * @return void
     */
    public static function methodNotAllowed(): void
    {
        self::error('Método no permitido', 405);
    }

    /**
     * Envía una respuesta 403 Forbidden.
     *
     * @param string $message
     * @return void
     */
    public static function forbidden(string $message = 'No autorizado'): void
    {
        self::error($message, 403);
    }
}
