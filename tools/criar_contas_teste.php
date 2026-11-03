<?php
declare(strict_types=1);

// Contas demonstrativas locais. As credenciais são públicas e estão no README.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../fluxos.php';

$senha = 'Complementa123!';
try {
    $pdo = obterConexao();
    transacao($pdo, function () use ($pdo, $senha) {
        $emailInstituicao = 'contato.teste@complementa.test';
        $instituicao = sql($pdo, 'SELECT id_instituicao FROM instituicao WHERE email=?', [$emailInstituicao])->fetchColumn();
        if (!$instituicao) {
            sql($pdo, 'INSERT INTO instituicao (nome,sigla,cidade,email) VALUES (?,?,?,?)',
                ['Instituição de Teste', 'TESTE', 'Curitiba', $emailInstituicao]);
            $instituicao = (int)$pdo->lastInsertId();
        }

        $contas = [
            ['aluno', 'aluno.teste@complementa.test',
                'INSERT INTO aluno (nome,email,senha_hash,matricula,id_instituicao,data_nascimento,curso,cidade,meta_horas) VALUES (?,?,?,?,?,?,?,?,?)',
                ['Aluno de Teste', 'aluno.teste@complementa.test', password_hash($senha, PASSWORD_DEFAULT),
                    'TESTE001', $instituicao, '2000-01-01', 'Engenharia de Software', 'Curitiba', 60]],
            ['organizador', 'organizador.teste@complementa.test',
                'INSERT INTO organizador (nome,email,senha_hash,tipo,cidade) VALUES (?,?,?,?,?)',
                ['Organizador de Teste', 'organizador.teste@complementa.test', password_hash($senha, PASSWORD_DEFAULT),
                    'Instituição', 'Curitiba']],
            ['usuario_gestao', 'instituicao.teste@complementa.test',
                'INSERT INTO usuario_gestao (nome,email,senha_hash,perfil,id_instituicao) VALUES (?,?,?,?,?)',
                ['Analista de Teste', 'instituicao.teste@complementa.test', password_hash($senha, PASSWORD_DEFAULT),
                    'instituicao', $instituicao]],
        ];

        foreach ($contas as [$tabela, $email, $insercao, $valores]) {
            // Os nomes de tabela vêm exclusivamente da lista fixa acima.
            $existente = sql($pdo, "SELECT * FROM {$tabela} WHERE email=?", [$email])->fetch();
            if ($existente) {
                if (!is_string($existente['senha_hash']) || !password_verify($senha, $existente['senha_hash'])
                    || ($tabela !== 'organizador' && (int)$existente['id_instituicao'] !== (int)$instituicao)
                    || ($tabela === 'usuario_gestao' && $existente['perfil'] !== 'instituicao')) {
                    throw new DomainException("A conta {$email} já existe com dados diferentes. Nenhum cadastro foi alterado.");
                }
                continue;
            }
            sql($pdo, $insercao, $valores);
        }
    });
    echo "Contas de teste prontas (as contas existentes foram preservadas).\n";
    echo "Aluno: aluno.teste@complementa.test\n";
    echo "Organizador: organizador.teste@complementa.test\n";
    echo "Instituição: instituicao.teste@complementa.test\n";
    echo "Senha das três contas: {$senha}\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e instanceof DomainException ? $e->getMessage() . "\n"
        : "Não foi possível criar as contas. Confira a conexão, a importação de banco.sql e se os e-mails ou a matrícula TESTE001 já estão em uso.\n");
    exit(1);
}
