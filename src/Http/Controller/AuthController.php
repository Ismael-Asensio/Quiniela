<?php

// ═══════════════════════════════════════════════════════════════
// AuthController — Controlador de Autenticación
// ═══════════════════════════════════════════════════════════════

class AuthController
{
    private $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function login(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $username = $request->input('usuario');
        $password = $request->input('contrasena');

        $result = $this->authService->authenticate($username, $password);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success($result['user'], 'Login exitoso');
    }

    public function register(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $username = $request->input('usuario');
        $password = $request->input('contrasena');
        $fullName = $request->input('nombreCompleto');

        $result = $this->authService->register($username, $password, $fullName);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success(null, $result['message']);
    }

    public function logout(): void
    {
        $this->authService->logout();
        JsonResponse::success(null, 'Sesión cerrada correctamente');
    }
}
