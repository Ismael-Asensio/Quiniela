<?php
require_once __DIR__ . '/../bootstrap.php';

$request = Request::capture();
$controller = new PartidoController();
$controller->getPartidos($request);
