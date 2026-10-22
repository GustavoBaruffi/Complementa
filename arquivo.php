<?php
declare(strict_types=1);
require_once __DIR__.'/app.php';
require_once __DIR__.'/gestao_dados.php';
$u=exigirPerfil('aluno','instituicao');
if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); header('Allow: GET'); exit; }
try {
    $id=inteiro($_GET['id'] ?? null);
    $pdo=obterConexao();
    if ($u['perfil']==='aluno') {
        registro($pdo,'SELECT c.id_certificado FROM certificado c JOIN inscricao i ON i.id_inscricao=c.id_inscricao WHERE c.id_certificado=? AND i.id_aluno=?',[$id,$u['id']]);
    } else {
        registro($pdo,"SELECT v.id_certificado FROM validacao_horas v JOIN usuario_gestao g ON g.id_instituicao=v.id_instituicao WHERE v.id_certificado=? AND g.id_usuario=? AND g.perfil='instituicao'",[$id,$u['id']]);
    }
    $r=registro($pdo,'SELECT mime,caminho_arquivo FROM certificado_conteudo WHERE id_certificado=?',[$id]);
    $ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$r['mime']] ?? throw new DomainException('Arquivo indisponível.');
    if (!is_string($r['caminho_arquivo']) || !str_starts_with($r['caminho_arquivo'], 'uploads/certificados/')) {
        throw new DomainException('Arquivo indisponível.');
    }
    $caminho = realpath(__DIR__ . '/' . $r['caminho_arquivo']);
    $diretorio = realpath(__DIR__ . '/uploads/certificados');
    if ($caminho === false || $diretorio === false || !is_file($caminho) || !str_starts_with($caminho, $diretorio . DIRECTORY_SEPARATOR)) {
        throw new DomainException('Arquivo indisponível.');
    }
    $tamanho = filesize($caminho);
    if ($tamanho === false) {
        throw new DomainException('Arquivo indisponível.');
    }
    header('Content-Type: '.$r['mime']);
    header('Content-Disposition: attachment; filename="certificado-'.$id.'.'.$ext.'"');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: sandbox; default-src 'none'");
    header('Content-Length: '.$tamanho);
    readfile($caminho);
} catch (DomainException $e) { http_response_code(404); echo 'Arquivo não encontrado ou acesso não permitido.'; }
catch (Throwable $e) { echo escapar(erroFluxo($e)); }
