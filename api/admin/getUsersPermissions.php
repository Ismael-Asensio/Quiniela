<?php
require_once __DIR__ . '/../bootstrap.php';

$request = Request::capture();
$competicionId = isset($_GET['competicion']) && $_GET['competicion'] !== '' ? (int)$_GET['competicion'] : Config::competicionActiva();

$controller = new UsuarioController();
$controller->getUsersPermissions($request, $competicionId);
