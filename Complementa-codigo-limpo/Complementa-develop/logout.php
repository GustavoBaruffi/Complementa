<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    cabecalho('Método não permitido');
    echo '<main class="container section"><p>Use o botão Sair no menu para encerrar sua sessão.</p></main>';
    rodape();
    exit;
}
verificarCsrf();
$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000, 'path' => $cookie['path'],
    'domain' => $cookie['domain'], 'secure' => $cookie['secure'],
    'httponly' => $cookie['httponly'], 'samesite' => $cookie['samesite'],
]);
session_destroy();
redirecionar('index.php');
