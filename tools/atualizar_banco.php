<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../conexao.php';

try {
    $pdo = obterConexao();
    if (!$pdo->query("SHOW COLUMNS FROM validacao_horas LIKE 'data_recebimento'")->fetch()) {
        // Não atribuir aos registros antigos uma data de recebimento inventada.
        $pdo->exec('ALTER TABLE validacao_horas ADD COLUMN data_recebimento DATETIME NULL AFTER status');
    }
    echo "Banco atualizado. Os novos envios registrarão a data e a hora de recebimento.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Não foi possível atualizar o banco: ".$e->getMessage()."\n");
    exit(1);
}
