<?php
declare(strict_types=1);

namespace Controllers;

use PDO;
use Exception;

class AuthController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function register(): void {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['nombre_completo']) || !isset($input['correo_electronico']) || !isset($input['password'])) {
            $this->sendProblem(
                'Campos Faltantes',
                400,
                'Los campos nombre_completo, correo_electronico y password son obligatorios.'
            );
        }

        $nombre = trim((string)$input['nombre_completo']);
        $email = trim((string)$input['correo_electronico']);
        $password = (string)$input['password'];
        $rol = trim((string)($input['rol'] ?? 'cliente'));

        // Basic validation
        if (empty($nombre) || empty($email) || empty($password)) {
            $this->sendProblem(
                'Datos Inválidos',
                400,
                'Los campos obligatorios no pueden estar vacíos.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendProblem(
                'Formato de Correo Inválido',
                400,
                'El formato del correo electrónico proporcionado no es válido.',
                'about:blank',
                [['name' => 'correo_electronico', 'reason' => 'No es un correo válido']]
            );
        }

        if (strlen($password) < 6) {
            $this->sendProblem(
                'Contraseña Muy Corta',
                400,
                'La contraseña debe tener al menos 6 caracteres.',
                'about:blank',
                [['name' => 'password', 'reason' => 'Debe ser de al menos 6 caracteres']]
            );
        }

        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id_usuario FROM usuarios WHERE correo_electronico = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $this->sendProblem(
                'Usuario Ya Existe',
                409,
                "El correo electrónico '{$email}' ya se encuentra registrado."
            );
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Insert user
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (nombre_completo, correo_electronico, password_hash, rol)
            VALUES (:nombre, :email, :password_hash, :rol)
        ");

        try {
            $stmt->execute([
                'nombre' => $nombre,
                'email' => $email,
                'password_hash' => $passwordHash,
                'rol' => $rol
            ]);
            $idUsuario = (int)$this->db->lastInsertId();

            $this->sendResponse(201, [
                'id_usuario' => $idUsuario,
                'nombre_completo' => $nombre,
                'correo_electronico' => $email,
                'rol' => $rol,
                'fecha_creacion' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->sendProblem(
                'Error de Servidor',
                500,
                'Ocurrió un error al intentar registrar el usuario: ' . $e->getMessage()
            );
        }
    }

    public function login(): void {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['correo_electronico']) || !isset($input['password'])) {
            $this->sendProblem(
                'Campos Faltantes',
                400,
                'Los campos correo_electronico y password son obligatorios.'
            );
        }

        $email = trim((string)$input['correo_electronico']);
        $password = (string)$input['password'];

        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE correo_electronico = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->sendProblem(
                'Credenciales Incorrectas',
                401,
                'El correo electrónico o la contraseña son incorrectos.'
            );
        }

        // Generate simulated session token
        $token = bin2hex(random_bytes(16));

        // Update token in DB
        $stmt = $this->db->prepare("UPDATE usuarios SET token_sesion = :token WHERE id_usuario = :id");
        $stmt->execute([
            'token' => $token,
            'id' => $user['id_usuario']
        ]);

        $this->sendResponse(200, [
            'token_sesion' => $token,
            'usuario' => [
                'id_usuario' => (int)$user['id_usuario'],
                'nombre_completo' => $user['nombre_completo'],
                'correo_electronico' => $user['correo_electronico'],
                'rol' => $user['rol']
            ]
        ]);
    }

    public function getUser(int $id): void {
        $stmt = $this->db->prepare("SELECT id_usuario, nombre_completo, correo_electronico, rol, fecha_creacion FROM usuarios WHERE id_usuario = :id");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->sendProblem(
                'Usuario No Encontrado',
                404,
                "El usuario con ID {$id} no existe."
            );
        }

        $this->sendResponse(200, $user);
    }

    private function sendResponse(int $statusCode, array $data): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function sendProblem(string $title, int $status, string $detail, string $type = 'about:blank', array $invalidParams = []): void {
        http_response_code($status);
        header('Content-Type: application/problem+json');
        $response = [
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $_SERVER['REQUEST_URI'] ?? ''
        ];
        if (!empty($invalidParams)) {
            $response['invalid-params'] = $invalidParams;
        }
        echo json_encode($response);
        exit;
    }
}
