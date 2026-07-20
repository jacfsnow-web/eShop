<?php
declare(strict_types=1);

namespace Controllers;

use PDO;
use Exception;

class ProductController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function listProducts(): void {
        $stmt = $this->db->prepare("
            SELECT p.*, c.nombre AS nombre_categoria 
            FROM productos p
            LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
            WHERE p.activo = 1
        ");
        $stmt->execute();
        $products = $stmt->fetchAll();

        foreach ($products as &$p) {
            $p['id_producto'] = (int)$p['id_producto'];
            $p['id_categoria'] = (int)$p['id_categoria'];
            $p['precio'] = (float)$p['precio'];
            $p['stock_disponible'] = (int)$p['stock_disponible'];
            $p['activo'] = (int)$p['activo'];
        }

        $this->sendResponse(200, $products);
    }

    public function getProductById(int $id): void {
        $stmt = $this->db->prepare("
            SELECT p.*, c.nombre AS nombre_categoria 
            FROM productos p
            LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
            WHERE p.id_producto = :id
        ");
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();

        if (!$product) {
            $this->sendProblem(
                'Producto No Encontrado',
                404,
                "El producto con ID {$id} no existe."
            );
        }

        $product['id_producto'] = (int)$product['id_producto'];
        $product['id_categoria'] = (int)$product['id_categoria'];
        $product['precio'] = (float)$product['precio'];
        $product['stock_disponible'] = (int)$product['stock_disponible'];
        $product['activo'] = (int)$product['activo'];

        $this->sendResponse(200, $product);
    }

    public function getProductBySku(string $sku): void {
        $stmt = $this->db->prepare("
            SELECT p.*, c.nombre AS nombre_categoria 
            FROM productos p
            LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
            WHERE p.sku = :sku
        ");
        $stmt->execute(['sku' => $sku]);
        $product = $stmt->fetch();

        if (!$product) {
            $this->sendProblem(
                'Producto No Encontrado',
                404,
                "El producto con SKU '{$sku}' no existe."
            );
        }

        $product['id_producto'] = (int)$product['id_producto'];
        $product['id_categoria'] = (int)$product['id_categoria'];
        $product['precio'] = (float)$product['precio'];
        $product['stock_disponible'] = (int)$product['stock_disponible'];
        $product['activo'] = (int)$product['activo'];

        $this->sendResponse(200, $product);
    }

    public function deductStock(): void {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['items']) || !is_array($input['items'])) {
            $this->sendProblem(
                'Formato Inválido',
                400,
                "El cuerpo de la petición debe contener un arreglo de 'items'."
            );
        }

        $this->db->beginTransaction();

        try {
            foreach ($input['items'] as $item) {
                if (!isset($item['id_producto']) || !isset($item['cantidad'])) {
                    throw new Exception("Cada ítem debe tener 'id_producto' y 'cantidad'.", 400);
                }

                $productId = (int)$item['id_producto'];
                $qty = (int)$item['cantidad'];

                if ($qty <= 0) {
                    throw new Exception("La cantidad para el producto ID {$productId} debe ser mayor a 0.", 400);
                }

                // Check stock using SELECT FOR UPDATE to lock row and avoid race conditions
                $stmt = $this->db->prepare("
                    SELECT stock_disponible, sku, activo 
                    FROM productos 
                    WHERE id_producto = :id 
                    FOR UPDATE
                ");
                $stmt->execute(['id' => $productId]);
                $prod = $stmt->fetch();

                if (!$prod) {
                    throw new Exception("El producto con ID {$productId} no existe.", 404);
                }

                if ((int)$prod['activo'] !== 1) {
                    throw new Exception("El producto con SKU '{$prod['sku']}' no está activo.", 400);
                }

                $available = (int)$prod['stock_disponible'];
                if ($available < $qty) {
                    throw new Exception("Stock insuficiente para el producto '{$prod['sku']}' (Disponible: {$available}, Solicitado: {$qty}).", 409);
                }

                // Update stock
                $updateStmt = $this->db->prepare("
                    UPDATE productos 
                    SET stock_disponible = stock_disponible - :qty 
                    WHERE id_producto = :id
                ");
                $updateStmt->execute(['qty' => $qty, 'id' => $productId]);
            }

            $this->db->commit();
            $this->sendResponse(200, [
                'status' => 'success',
                'mensaje' => 'Stock descontado con éxito.'
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code <= 500) ? (int)$code : 500;
            $this->sendProblem(
                'Conflicto al Descontar Stock',
                $statusCode,
                $e->getMessage()
            );
        }
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
