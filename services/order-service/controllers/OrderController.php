<?php
declare(strict_types=1);

namespace Controllers;

use PDO;
use Exception;

class OrderController {
    private PDO $db;
    private string $authServiceUrl;
    private string $catalogServiceUrl;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->authServiceUrl = getenv('AUTH_SERVICE_URL') ?: 'http://auth-service';
        $this->catalogServiceUrl = getenv('CATALOG_SERVICE_URL') ?: 'http://catalog-service';
    }

    public function createOrder(): void {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['id_usuario']) || !isset($input['direccion_envio']) || !isset($input['items'])) {
            $this->sendProblem(
                'Datos Incompletos',
                400,
                'Los campos id_usuario, direccion_envio y items son obligatorios.'
            );
        }

        $userId = (int)$input['id_usuario'];
        $shippingAddress = trim((string)$input['direccion_envio']);
        $cartItems = $input['items'];

        if (empty($shippingAddress)) {
            $this->sendProblem(
                'Dirección de Envío Vacía',
                400,
                'La dirección de envío es requerida.'
            );
        }

        if (!is_array($cartItems) || empty($cartItems)) {
            $this->sendProblem(
                'Carrito Vacío',
                400,
                'El pedido debe contener al menos un producto en items.'
            );
        }

        // 1. Verify user exists via Auth Service
        $authUrl = "{$this->authServiceUrl}/users?id={$userId}";
        $userCheck = $this->makeHttpRequest($authUrl, 'GET');

        if ($userCheck['status'] === 404) {
            $this->sendProblem(
                'Usuario No Registrado',
                404,
                "El usuario con ID {$userId} no existe en el sistema de autenticación."
            );
        } elseif ($userCheck['status'] !== 200) {
            $this->sendProblem(
                'Error de Comunicación Inter-Servicio',
                502,
                "No se pudo verificar el usuario en Auth Service. Status: {$userCheck['status']}"
            );
        }

        // 2. Verify all products and check stock via Catalog Service
        $verifiedItems = [];
        $orderSubtotal = 0.0;

        foreach ($cartItems as $item) {
            if (!isset($item['id_producto']) || !isset($item['cantidad'])) {
                $this->sendProblem(
                    'Formato de Item Inválido',
                    400,
                    'Cada ítem del carrito debe especificar id_producto y cantidad.'
                );
            }

            $productId = (int)$item['id_producto'];
            $qty = (int)$item['cantidad'];

            if ($qty <= 0) {
                $this->sendProblem(
                    'Cantidad Inválida',
                    400,
                    "La cantidad del producto ID {$productId} debe ser mayor a 0."
                );
            }

            // Fetch product detail from Catalog Service
            $catalogUrl = "{$this->catalogServiceUrl}/products?id={$productId}";
            $productCheck = $this->makeHttpRequest($catalogUrl, 'GET');

            if ($productCheck['status'] === 404) {
                $this->sendProblem(
                    'Producto No Encontrado',
                    404,
                    "El producto con ID {$productId} no existe en el catálogo."
                );
            } elseif ($productCheck['status'] !== 200) {
                $this->sendProblem(
                    'Error de Comunicación Inter-Servicio',
                    502,
                    "No se pudo verificar el producto en Catalog Service. Status: {$productCheck['status']}"
                );
            }

            $product = $productCheck['body'];

            if ((int)$product['activo'] !== 1) {
                $this->sendProblem(
                    'Producto Inactivo',
                    400,
                    "El producto '{$product['nombre_producto']}' (SKU: {$product['sku']}) ya no está activo."
                );
            }

            $availableStock = (int)$product['stock_disponible'];
            if ($availableStock < $qty) {
                $this->sendProblem(
                    'Stock Insuficiente',
                    409,
                    "El producto '{$product['nombre_producto']}' (SKU: {$product['sku']}) no tiene suficiente stock. Disponible: {$availableStock}, Solicitado: {$qty}."
                );
            }

            $price = (float)$product['precio'];
            $lineSubtotal = $price * $qty;
            $orderSubtotal += $lineSubtotal;

            $verifiedItems[] = [
                'id_producto' => $productId,
                'sku' => (string)$product['sku'],
                'cantidad' => $qty,
                'precio_unitario' => $price,
                'subtotal_linea' => $lineSubtotal
            ];
        }

        // Calculate taxes (16% IVA in Mexico) and total
        $taxRate = 0.16;
        $orderTax = round($orderSubtotal * $taxRate, 2);
        $orderTotal = round($orderSubtotal + $orderTax, 2);

        // 3. Begin order creation transaction
        $this->db->beginTransaction();

        try {
            // Insert order header
            $stmt = $this->db->prepare("
                INSERT INTO pedidos (id_usuario, subtotal, impuesto, total, direccion_envio, estado)
                VALUES (:id_usuario, :subtotal, :impuesto, :total, :direccion_envio, 'completado')
            ");
            $stmt->execute([
                'id_usuario' => $userId,
                'subtotal' => $orderSubtotal,
                'impuesto' => $orderTax,
                'total' => $orderTotal,
                'direccion_envio' => $shippingAddress
            ]);
            $orderId = (int)$this->db->lastInsertId();

            // Insert order details
            $detailStmt = $this->db->prepare("
                INSERT INTO detalle_pedidos (id_pedido, id_producto, sku_producto, cantidad, precio_unitario, subtotal_linea)
                VALUES (:id_pedido, :id_producto, :sku_producto, :cantidad, :precio_unitario, :subtotal_linea)
            ");

            $deductItems = [];
            foreach ($verifiedItems as $item) {
                $detailStmt->execute([
                    'id_pedido' => $orderId,
                    'id_producto' => $item['id_producto'],
                    'sku_producto' => $item['sku'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal_linea' => $item['subtotal_linea']
                ]);

                $deductItems[] = [
                    'id_producto' => $item['id_producto'],
                    'cantidad' => $item['cantidad']
                ];
            }

            // 4. Call Catalog Service to deduct stock (CORS/inter-service síncrono POST)
            $deductUrl = "{$this->catalogServiceUrl}/products/deduct-stock";
            $deductCheck = $this->makeHttpRequest($deductUrl, 'POST', ['items' => $deductItems]);

            if ($deductCheck['status'] !== 200) {
                $detail = $deductCheck['body']['detail'] ?? 'Error desconocido al actualizar stock en Catalog Service.';
                throw new Exception("Error al actualizar el inventario: {$detail}", 409);
            }

            // Commit order creation
            $this->db->commit();

            $this->sendResponse(201, [
                'status' => 'success',
                'mensaje' => 'Pedido creado y procesado con éxito.',
                'id_pedido' => $orderId,
                'pedido' => [
                    'id_pedido' => $orderId,
                    'id_usuario' => $userId,
                    'subtotal' => $orderSubtotal,
                    'impuesto' => $orderTax,
                    'total' => $orderTotal,
                    'direccion_envio' => $shippingAddress,
                    'estado' => 'completado',
                    'detalles' => $verifiedItems
                ]
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code <= 500) ? (int)$code : 500;
            $this->sendProblem(
                'Transacción de Pedido Fallida',
                $statusCode,
                'No se pudo completar la creación del pedido. Motivo: ' . $e->getMessage()
            );
        }
    }

    public function listOrders(): void {
        // Simple list endpoint for manual verification
        $stmt = $this->db->prepare("SELECT * FROM pedidos ORDER BY fecha_pedido DESC");
        $stmt->execute();
        $orders = $stmt->fetchAll();

        foreach ($orders as &$o) {
            $o['id_pedido'] = (int)$o['id_pedido'];
            $o['id_usuario'] = (int)$o['id_usuario'];
            $o['subtotal'] = (float)$o['subtotal'];
            $o['impuesto'] = (float)$o['impuesto'];
            $o['total'] = (float)$o['total'];

            // Fetch detail
            $detStmt = $this->db->prepare("SELECT * FROM detalle_pedidos WHERE id_pedido = :id");
            $detStmt->execute(['id' => $o['id_pedido']]);
            $o['detalles'] = $detStmt->fetchAll();

            foreach ($o['detalles'] as &$d) {
                $d['id_detalle'] = (int)$d['id_detalle'];
                $d['id_pedido'] = (int)$d['id_pedido'];
                $d['id_producto'] = (int)$d['id_producto'];
                $d['cantidad'] = (int)$d['cantidad'];
                $d['precio_unitario'] = (float)$d['precio_unitario'];
                $d['subtotal_linea'] = (float)$d['subtotal_linea'];
            }
        }

        $this->sendResponse(200, $orders);
    }

    private function makeHttpRequest(string $url, string $method = 'GET', ?array $body = null): array {
        $opts = [
            "http" => [
                "method" => $method,
                "header" => "Content-Type: application/json\r\nAccept: application/json\r\n",
                "ignore_errors" => true
            ]
        ];
        if ($body !== null) {
            $opts["http"]["content"] = json_encode($body);
        }
        $context = stream_context_create($opts);
        $response = file_get_contents($url, false, $context);

        $status = 500;
        if (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header)) {
            if (preg_match('#HTTP/[0-9\.]+\s+([0-9]+)#', $http_response_header[0], $matches)) {
                $status = (int)$matches[1];
            }
        }

        return [
            'status' => $status,
            'body' => $response !== false ? json_decode($response, true) : null
        ];
    }

    private function sendResponse(int $statusCode, array $data): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function sendProblem(string $title, int $status, string $detail, string $type = 'about:blank'): void {
        http_response_code($status);
        header('Content-Type: application/problem+json');
        echo json_encode([
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $_SERVER['REQUEST_URI'] ?? ''
        ]);
        exit;
    }
}
