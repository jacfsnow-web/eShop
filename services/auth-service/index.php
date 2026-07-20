<?php
declare(strict_types=1);

// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, PATCH, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autoload config and controller
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/controllers/AuthController.php';

use Config\Database;
use Controllers\AuthController;

$dbConfig = new Database();
$db = $dbConfig->getConnection();
$controller = new AuthController($db);

// Route requests
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Normalize path relative to the script's directory (handles subdirectories like in XAMPP)
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$basePath = str_replace('\\', '/', $basePath);
if ($basePath !== '/' && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
$path = '/' . trim($path, '/');

if ($requestMethod === 'POST' && $path === '/register') {
    $controller->register();
} elseif ($requestMethod === 'POST' && $path === '/login') {
    $controller->login();
} elseif ($requestMethod === 'GET' && $path === '/users') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        header('Content-Type: application/problem+json');
        echo json_encode([
            'type' => 'about:blank',
            'title' => 'Parámetro Faltante',
            'status' => 400,
            'detail' => 'Se requiere el parámetro id en la query string (ej: ?id=1).',
            'instance' => $requestUri
        ]);
        exit;
    }
    $controller->getUser($id);
} elseif ($requestMethod === 'GET' && preg_match('#^/users/(\d+)$#', $path, $matches)) {
    $controller->getUser((int)$matches[1]);
} else {
    http_response_code(404);
    header('Content-Type: application/problem+json');
    echo json_encode([
        'type' => 'about:blank',
        'title' => 'No Encontrado',
        'status' => 404,
        'detail' => "La ruta '{$path}' con método {$requestMethod} no existe en el servicio de autenticación.",
        'instance' => $requestUri
    ]);
    exit;
}
