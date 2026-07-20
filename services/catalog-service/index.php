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
require_once __DIR__ . '/controllers/ProductController.php';

use Config\Database;
use Controllers\ProductController;

$dbConfig = new Database();
$db = $dbConfig->getConnection();
$controller = new ProductController($db);

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

if ($requestMethod === 'GET' && $path === '/products') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $sku = isset($_GET['sku']) ? trim((string)$_GET['sku']) : null;

    if ($id !== null) {
        $controller->getProductById($id);
    } elseif ($sku !== null && $sku !== '') {
        $controller->getProductBySku($sku);
    } else {
        $controller->listProducts();
    }
} elseif ($requestMethod === 'GET' && preg_match('#^/products/(\d+)$#', $path, $matches)) {
    $controller->getProductById((int)$matches[1]);
} elseif ($requestMethod === 'GET' && preg_match('#^/products/sku/([^/]+)$#', $path, $matches)) {
    $controller->getProductBySku(rawurldecode($matches[1]));
} elseif ($requestMethod === 'POST' && $path === '/products/deduct-stock') {
    $controller->deductStock();
} else {
    http_response_code(404);
    header('Content-Type: application/problem+json');
    echo json_encode([
        'type' => 'about:blank',
        'title' => 'No Encontrado',
        'status' => 404,
        'detail' => "La ruta '{$path}' con método {$requestMethod} no existe en el servicio de catálogo.",
        'instance' => $requestUri
    ]);
    exit;
}
