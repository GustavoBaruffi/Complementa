<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/conexao.php';
echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo 'PDO MySQL: ' . (in_array('mysql', PDO::getAvailableDrivers(), true) ? 'disponível' : 'indisponível') . PHP_EOL;
try {
    $pdo = obterConexao();
    foreach (['aluno', 'organizador', 'categoria', 'evento'] as $tabela) {
        $consulta = $pdo->prepare("SELECT COUNT(*) FROM {$tabela}");
        $consulta->execute();
        echo $tabela . ': ' . $consulta->fetchColumn() . " registro(s)\n";
    }
    echo "Conexão e leitura: OK (nenhum dado alterado).\n";
        $esperadas = [
        'aluno' => ['id_aluno', 'nome', 'email', 'senha_hash', 'curso', 'matricula', 'id_instituicao', 'data_nascimento', 'cidade', 'meta_horas'],
        'organizador' => ['id_organizador', 'nome', 'email', 'senha_hash', 'tipo', 'cidade', 'telefone'],
        'evento' => ['id_evento', 'id_organizador', 'id_categoria', 'titulo', 'descricao', 'data_evento', 'horario_inicio', 'horario_termino', 'local_evento', 'carga_horaria', 'modalidade', 'cidade', 'link', 'status', 'validade_horas'],
    ];
    $compativel = true;
    foreach ($esperadas as $tabela => $campos) {
        $consulta = $pdo->prepare("SHOW COLUMNS FROM {$tabela}");
        $consulta->execute();
        $colunas = $consulta->fetchAll();
        $ausentes = array_diff($campos, array_column($colunas, 'Field'));
        if ($ausentes) {
            $compativel = false;
            echo "INCOMPATÍVEL: {$tabela} sem " . implode(', ', $ausentes) . ".\n";
        }
        foreach ($colunas as $coluna) {
            if ($coluna['Null'] === 'NO' && $coluna['Default'] === null
                && !str_contains($coluna['Extra'], 'auto_increment')
                && !in_array($coluna['Field'], $campos, true)) {
                $compativel = false;
                echo "INCOMPATÍVEL: {$tabela}.{$coluna['Field']} obrigatório não enviado pelo cadastro atual.\n";
            }
        }
    }
    if (!$compativel) {
        fwrite(STDERR, "A conexão funciona, mas o schema não corresponde à aplicação. Não recrie o banco; revise as diferenças antes de testar gravações.\n");
        exit(2);
    }
    echo "Colunas esperadas: OK. Gravações e restrições ainda precisam de testes próprios.\n";
} catch (PDOException $erro) {
    fwrite(STDERR, 'Conexão/leitura indisponível. Código: ' . $erro->getCode() . ". Confira o driver, DB_HOST, DB_PORT, DB_NAME, DB_USER e DB_PASSWORD.\n");
    exit(1);
}
