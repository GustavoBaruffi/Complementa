<?php
declare(strict_types=1);

// Executável somente pelo terminal da equipe, nunca como endpoint web.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/conexao.php';
require_once dirname(__DIR__) . '/organizadores.php';

function perguntar(string $rotulo): string
{
    echo $rotulo . ': ';
    return trim((string) fgets(STDIN));
}

echo "Cadastro de organizador pela equipe. A senha será visível neste terminal.\n";
$nome = perguntar('Nome');
$email = perguntar('E-mail');
$senha = perguntar('Senha (8 a 72 bytes)');
$tipo = perguntar('Tipo (Faculdade, Empresa, Prefeitura, ONG, Associação, Instituição ou Outro)');
$cidade = perguntar('Cidade');
$telefone = perguntar('Telefone (opcional)');
try {
    $id = criarOrganizador(obterConexao(), compact('nome', 'email', 'senha', 'tipo', 'cidade', 'telefone'));
    echo "Organizador cadastrado. ID: {$id}\n";
} catch (InvalidArgumentException $erro) {
    fwrite(STDERR, $erro->getMessage() . " Nenhum cadastro realizado.\n");
    exit(1);
} catch (PDOException $erro) {
    $mensagem = ($erro->errorInfo[1] ?? null) === 1062
        ? 'Este e-mail já possui cadastro de organizador.'
        : 'Falha no banco. Confira a conexão e os relacionamentos.';
    fwrite(STDERR, $mensagem . "\n");
    exit(1);
}
