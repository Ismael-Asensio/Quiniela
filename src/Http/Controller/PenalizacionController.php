<?php

// ═══════════════════════════════════════════════════════════════
// PenalizacionController — Controlador de Penalizaciones (Admin)
// ═══════════════════════════════════════════════════════════════

class PenalizacionController
{
    private $penalizacionService;

    public function __construct()
    {
        $this->penalizacionService = new PenalizacionService();
    }

    public function handleRequest(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if ($request->isMethod('GET')) {
            $this->list($request);
        } elseif ($request->isMethod('POST')) {
            $action = $request->input('action');
            switch ($action) {
                case 'create':
                    $this->create($request);
                    break;
                case 'annul':
                    $this->annul($request);
                    break;
                case 'reactivate':
                    $this->reactivate($request);
                    break;
                case 'update':
                    $this->update($request);
                    break;
                default:
                    JsonResponse::error('Acción no válida', 400);
            }
        } else {
            JsonResponse::error('Método no permitido', 405);
        }
    }

    private function list(Request $request): void
    {
        $estado = $request->query('estado');
        $busqueda = $request->query('busqueda');

        $penalizaciones = $this->penalizacionService->listar($estado, $busqueda);
        
        $usuarioService = new UsuarioService();
        $usuarios = $usuarioService->listarUsuariosConRoles();
        
        $resumen = $this->penalizacionService->obtenerResumen();
        
        JsonResponse::send([
            'success' => true, 
            'penalizaciones' => $penalizaciones, 
            'usuarios' => $usuarios,
            'resumen' => $resumen
        ]);
    }

    private function create(Request $request): void
    {
        // Frontend sends "userIds" as array
        $userIds = $request->inputArray('userIds', []);
        $puntos = $request->inputInt('puntos');
        $motivo = $request->input('motivo');
        $idTabla = $request->inputInt('idTabla');
        $creadaPor = AuthMiddleware::getUserId();

        if ($idTabla <= 0) {
            JsonResponse::error('Se requiere idTabla', 400);
        }

        $result = $this->penalizacionService->crear($userIds, $puntos, $motivo, $creadaPor, $idTabla);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code']);
        }

        JsonResponse::success(null, $result['message']);
    }

    private function annul(Request $request): void
    {
        $id = $request->inputInt('id');
        $anuladaPor = AuthMiddleware::getUserId();

        $result = $this->penalizacionService->anular($id, $anuladaPor);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code']);
        }

        JsonResponse::success(null, $result['message']);
    }

    private function reactivate(Request $request): void
    {
        $id = $request->inputInt('id');
        $reactivadaPor = AuthMiddleware::getUserId();

        $result = $this->penalizacionService->reactivar($id, $reactivadaPor);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code']);
        }

        JsonResponse::success(null, $result['message']);
    }

    private function update(Request $request): void
    {
        $id = $request->inputInt('id');
        $puntos = $request->inputInt('puntos');
        $motivo = $request->input('motivo');
        $modificadaPor = AuthMiddleware::getUserId();

        $result = $this->penalizacionService->modificar($id, $puntos, $motivo, $modificadaPor);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code']);
        }

        JsonResponse::success(null, $result['message']);
    }
}
