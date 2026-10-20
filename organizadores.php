<?php
declare(strict_types=1);

// Serviço usado pelo comando da equipe; não publica cadastro na web.
function criarOrganizador(PDO $pdo, array $dados): int
{
    $tipos = ['Faculdade', 'Empresa', 'Prefeitura', 'ONG', 'Associação', 'Instituição', 'Outro'];
    foreach (['nome' => 150, 'cidade' => 100] as $campo => $limite) {
        if (!is_string($dados[$campo] ?? null) || trim($dados[$campo]) === ''
            || (preg_match_all('/./us', $dados[$campo]) ?: 0) > $limite) {
            throw new InvalidArgumentException("Informe {$campo} com até {$limite} caracteres.");
        }
    }
    if (!in_array($dados['tipo'] ?? '', $tipos, true)) {
        throw new InvalidArgumentException('Tipo de organizador inválido.');
    }
    if (!is_string($dados['email'] ?? null) || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)
        || strlen($dados['email']) > 150) {
        throw new InvalidArgumentException('E-mail inválido.');
    }
    $senha = $dados['senha'] ?? null;
    if (!is_string($senha) || strlen($senha) < 8 || strlen($senha) > 72 || str_contains($senha, "\0")) {
        throw new InvalidArgumentException('A senha deve ter de 8 a 72 bytes.');
    }
    $telefone = $dados['telefone'] ?? '';
    if (!is_string($telefone) || (preg_match_all('/./us', $telefone) ?: 0) > 20) {
        throw new InvalidArgumentException('Telefone deve ter até 20 caracteres.');
    }
    $consulta = $pdo->prepare('INSERT INTO organizador (nome,tipo,email,telefone,cidade,senha_hash)
        VALUES (:nome,:tipo,:email,:telefone,:cidade,:senha)');
    $consulta->execute([
        'nome' => trim($dados['nome']), 'tipo' => $dados['tipo'], 'email' => $dados['email'],
        'telefone' => trim($telefone) === '' ? null : trim($telefone), 'cidade' => trim($dados['cidade']),
        'senha' => password_hash($senha, PASSWORD_DEFAULT),
    ]);
    return (int) $pdo->lastInsertId();
}
