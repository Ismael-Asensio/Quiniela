<?php
require_once __DIR__ . '/../bootstrap.php';

$request = Request::capture();
$controller = new PrediccionController();
$controller->getAdminPredicciones($request);
