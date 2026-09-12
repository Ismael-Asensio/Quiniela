<?php
require_once __DIR__ . '/../bootstrap.php';

$request = Request::capture();
$controller = new AuthController();
$controller->register($request);
