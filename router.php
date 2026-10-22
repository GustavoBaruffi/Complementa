<?php
declare(strict_types=1);
$caminho=rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^|/)(?:\.[^/]*|tools|tests|docs)(?:/|$)|\.(?:sql|md|ini|log|ps1|py|mjs)$~i',$caminho) || str_contains($caminho,'\\')) {
    http_response_code(404); exit('Não encontrado.');
}
return false;
