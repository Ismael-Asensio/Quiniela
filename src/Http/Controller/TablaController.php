<?php

// ═══════════════════════════════════════════════════════════════
// TablaController — Controlador de Tablas de Puntuación
// ═══════════════════════════════════════════════════════════════

class TablaController
{
    private $tablaService;

    public function __construct()
    {
        $this->tablaService = new TablaService();
    }

    public function handleRequest(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if ($request->isMethod('GET')) {
            $this->list();
        } elseif ($request->isMethod('POST')) {
            $action = $request->input('action');
            switch ($action) {
                case 'create':
                    $this->create($request);
                    break;
                case 'addMember':
                    $this->addMember($request);
                    break;
                case 'removeMember':
                    $this->removeMember($request);
                    break;
                case 'getMembers':
                    $this->getMembers($request);
                    break;
                default:
                    JsonResponse::error('Acción no válida. Acciones: create, addMember, removeMember, getMembers', 400);
            }
        } else {
            JsonResponse::error('Método no permitido', 405);
        }
    }

    /**
     * Entry point propio (api/admin/updateUserPermission.php), no pasa
     * por el dispatcher de acciones de handleRequest().
     */
    public function updatePermission(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $userId = $request->inputInt('userId');
        $idTabla = $request->inputInt('idTabla');
        $field = $request->input('field');
        $value = $request->inputInt('value');

        if (!$userId || !$idTabla) {
            JsonResponse::error('Se requiere userId, idTabla y un field válido', 400);
        }

        $result = $this->tablaService->actualizarPermiso($idTabla, $userId, $field, $value);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success(null, $result['message']);
    }

    private function list(): void
    {
        $tablas = $this->tablaService->listar();
        JsonResponse::send(['success' => true, 'tablas' => $tablas]);
    }

    private function create(Request $request): void
    {
        $nombre = $request->input('nombre');
        $descripcion = $request->input('descripcion');
        $faseInicio = $request->input('faseInicio') ?: 'Grupos';
        $creadaPor = AuthMiddleware::getUserId();

        $result = $this->tablaService->crear($nombre, $descripcion, $creadaPor, $faseInicio);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::send($result);
    }

    private function addMember(Request $request): void
    {
        $idTabla = $request->inputInt('idTabla');
        $idUsuario = $request->inputInt('idUsuario');

        if (!$idTabla || !$idUsuario) {
            JsonResponse::error('Se requiere idTabla e idUsuario', 400);
        }

        $result = $this->tablaService->agregarMiembro($idTabla, $idUsuario);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success(null, $result['message']);
    }

    private function removeMember(Request $request): void
    {
        $idTabla = $request->inputInt('idTabla');
        $idUsuario = $request->inputInt('idUsuario');

        if (!$idTabla || !$idUsuario) {
            JsonResponse::error('Se requiere idTabla e idUsuario', 400);
        }

        $result = $this->tablaService->quitarMiembro($idTabla, $idUsuario);

        JsonResponse::success(null, $result['message']);
    }

    private function getMembers(Request $request): void
    {
        $idTabla = $request->inputInt('idTabla');

        if (!$idTabla) {
            JsonResponse::error('Se requiere idTabla', 400);
        }

        $result = $this->tablaService->obtenerMiembros($idTabla);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::send($result);
    }
}
