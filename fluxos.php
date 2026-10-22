<?php
declare(strict_types=1);

function sql(PDO $pdo, string $sql, array $valores = []): PDOStatement
{
    $q = $pdo->prepare($sql);
    $q->execute($valores);
    return $q;
}

function exigirPerfil(string ...$perfis): array
{
    $u = usuario();
    if (!$u) redirecionar('login.php');
    if (!in_array($u['perfil'], $perfis, true)) {
        http_response_code(403);
        cabecalho('Acesso restrito');
        echo '<main class="container section"><h1>Acesso restrito</h1></main>';
        rodape();
        exit;
    }
    return $u;
}

function texto(array $dados, string $campo, int $maximo, bool $obrigatorio = true): string
{
    $v = $dados[$campo] ?? '';
    if (!is_string($v)) throw new DomainException('Campo inválido: ' . $campo);
    $v = trim($v);
    if (($obrigatorio && $v === '') || !preg_match('//u', $v) || tamanho($v) > $maximo) {
        throw new DomainException('Confira o campo ' . $campo . ' (máximo ' . $maximo . ' caracteres).');
    }
    return $v;
}

function inteiro(mixed $valor, int $min = 1, int $max = PHP_INT_MAX): int
{
    $n = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($n === false) throw new DomainException('Informe um número inteiro válido.');
    return $n;
}

function horas(mixed $valor, float $min = 0.5): string
{
    if (!is_string($valor) && !is_numeric($valor)) throw new DomainException('Carga horária inválida.');
    $v = str_replace(',', '.', (string) $valor);
    if (!preg_match('/^\d{1,3}(?:\.\d)?$/D', $v) || (float) $v < $min || (float) $v > 999) {
        throw new DomainException('Informe horas de ' . $min . ' a 999, com até uma casa decimal.');
    }
    return $v;
}

function escolha(mixed $valor, array $permitidos): string
{
    if (!is_string($valor) || !in_array($valor, $permitidos, true)) throw new DomainException('Opção inválida.');
    return $valor;
}

function transacao(PDO $pdo, callable $acao): mixed
{
    $pdo->beginTransaction();
    try { $resultado = $acao(); $pdo->commit(); return $resultado; }
    catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function registro(PDO $pdo, string $consulta, array $parametros): array
{
    $r = sql($pdo, $consulta, $parametros)->fetch();
    if (!$r) throw new DomainException('Registro não encontrado ou acesso não permitido.');
    return $r;
}

function notificar(PDO $pdo, int $aluno, string $titulo, string $mensagem): void
{
    sql($pdo, 'INSERT INTO notificacao (id_aluno,titulo,mensagem,data_criacao,lida) VALUES (?,?,?,NOW(),0)', [$aluno,$titulo,$mensagem]);
}

function inscrever(PDO $pdo, int $aluno, int $evento): void
{
    transacao($pdo, function () use ($pdo,$aluno,$evento) {
        $e = registro($pdo, 'SELECT status, TIMESTAMP(data_evento,horario_inicio) > NOW() AS futuro FROM evento WHERE id_evento=? FOR UPDATE', [$evento]);
        if ($e['status'] !== 'Publicado' || !$e['futuro']) throw new DomainException('Este evento não recebe inscrições.');
        if (sql($pdo,'SELECT id_inscricao FROM inscricao WHERE id_aluno=? AND id_evento=?',[$aluno,$evento])->fetch()) {
            throw new DomainException('Você já possui inscrição neste evento, inclusive se cancelada.');
        }
        sql($pdo,"INSERT INTO inscricao (id_aluno,id_evento,data_inscricao,status,presenca) VALUES (?,?,NOW(),'Confirmada',0)",[$aluno,$evento]);
        notificar($pdo,$aluno,'Inscrição confirmada','Sua inscrição no evento foi confirmada.');
    });
}

function cancelarInscricao(PDO $pdo, int $aluno, int $id): void
{
    transacao($pdo, function () use ($pdo,$aluno,$id) {
        $r = registro($pdo,'SELECT i.status,i.presenca,TIMESTAMP(e.data_evento,e.horario_inicio)>NOW() AS futuro FROM inscricao i JOIN evento e ON e.id_evento=i.id_evento WHERE i.id_inscricao=? AND i.id_aluno=? FOR UPDATE',[$id,$aluno]);
        if (!$r['futuro'] || $r['presenca'] || !in_array($r['status'],['Pendente','Confirmada'],true)) throw new DomainException('Só é possível cancelar antes do início e sem presença registrada.');
        sql($pdo,"UPDATE inscricao SET status='Cancelada' WHERE id_inscricao=?",[$id]);
        notificar($pdo,$aluno,'Inscrição cancelada','O cancelamento da sua inscrição foi registrado.');
    });
}

function participante(PDO $pdo, int $organizador, array $dados): void
{
    $id = inteiro($dados['id'] ?? null);
    $status = escolha($dados['status'] ?? null,['Pendente','Confirmada','Cancelada','Concluída']);
    $presenca = inteiro($dados['presenca'] ?? null,0,1);
    transacao($pdo, function () use ($pdo,$organizador,$id,$status,$presenca) {
        $r = registro($pdo,'SELECT i.id_aluno,e.status AS evento_status,TIMESTAMP(e.data_evento,e.horario_inicio)<=NOW() AS iniciado FROM inscricao i JOIN evento e ON e.id_evento=i.id_evento WHERE i.id_inscricao=? AND e.id_organizador=? FOR UPDATE',[$id,$organizador]);
        if (($presenca || $status === 'Concluída') && (!$r['iniciado'] || in_array($r['evento_status'],['Rascunho','Cancelado'],true))) throw new DomainException('Presença só pode ser confirmada após o início de um evento válido.');
        if (($presenca && in_array($status,['Cancelada','Pendente'],true)) || ($status === 'Concluída' && !$presenca)) throw new DomainException('Presença e situação incompatíveis.');
        if (sql($pdo,'SELECT id_certificado FROM certificado WHERE id_inscricao=?',[$id])->fetch() && (!$presenca || $status === 'Cancelada')) throw new DomainException('Esta participação já possui certificado; preserve sua presença.');
        sql($pdo,'UPDATE inscricao SET status=?,presenca=? WHERE id_inscricao=?',[$status,$presenca,$id]);
        notificar($pdo,(int)$r['id_aluno'],'Participação atualizada','A presença ou situação da sua inscrição foi atualizada.');
    });
}

function validarArquivo(array $arquivo): array
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($arquivo['tmp_name'] ?? null) || !is_uploaded_file($arquivo['tmp_name'])) throw new DomainException('Selecione um arquivo válido de até 5 MB.');
    $tamanho = filesize($arquivo['tmp_name']);
    if ($tamanho === false || $tamanho < 1 || $tamanho > 5 * 1024 * 1024) throw new DomainException('O arquivo deve ter até 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
    $ext = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mime] ?? null;
    if (!$ext) throw new DomainException('Envie somente PDF, JPG ou PNG.');
    $conteudo = file_get_contents($arquivo['tmp_name']);
    if ($conteudo === false) throw new DomainException('Não foi possível ler o arquivo.');
    if (($ext === 'pdf' && !str_starts_with($conteudo,'%PDF-')) || ($ext !== 'pdf' && @getimagesize($arquivo['tmp_name']) === false)) throw new DomainException('Conteúdo de arquivo inválido.');
    return [$mime,$ext];
}

function enviarCertificado(PDO $pdo, int $aluno, array $dados, array $arquivo): void
{
    $id = inteiro($dados['id'] ?? null);
    $carga = horas($dados['horas'] ?? null);
    [$mime,$ext] = validarArquivo($arquivo);
    $diretorio = __DIR__ . '/uploads/certificados';
    if (!is_dir($diretorio) && !mkdir($diretorio, 0750, true) && !is_dir($diretorio)) {
        throw new RuntimeException('Não foi possível preparar o armazenamento do certificado.');
    }
    $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $ext;
    $caminhoRelativo = 'uploads/certificados/' . $nomeArquivo;
    $caminho = __DIR__ . '/' . $caminhoRelativo;
    if (!move_uploaded_file($arquivo['tmp_name'], $caminho)) {
        throw new RuntimeException('Não foi possível armazenar o certificado.');
    }
    try {
        transacao($pdo,function () use ($pdo,$aluno,$id,$carga,$mime,$ext,$caminhoRelativo) {
            $r = registro($pdo,'SELECT i.status,i.presenca,e.status AS evento_status,TIMESTAMP(e.data_evento,e.horario_termino)<=NOW() AS encerrado FROM inscricao i JOIN evento e ON e.id_evento=i.id_evento WHERE i.id_inscricao=? AND i.id_aluno=? FOR UPDATE',[$id,$aluno]);
            if (!$r['presenca'] || !$r['encerrado'] || $r['status']==='Cancelada' || in_array($r['evento_status'],['Rascunho','Cancelado'],true)) throw new DomainException('É necessário participar de um evento encerrado e ter presença confirmada.');
            sql($pdo,"INSERT INTO certificado (id_inscricao,arquivo,data_envio,carga_horaria_declarada,status) VALUES (?,?,NOW(),?,'Pendente')",[$id,'certificado.'.$ext,$carga]);
            $cert = (int)$pdo->lastInsertId();
            sql($pdo,'INSERT INTO certificado_conteudo (id_certificado,mime,caminho_arquivo) VALUES (?,?,?)',[$cert,$mime,$caminhoRelativo]);
            notificar($pdo,$aluno,'Certificado recebido','Seu certificado foi recebido. Solicite a validação das horas na área de certificados.');
        });
    } catch (Throwable $e) {
        if (is_file($caminho)) {
            unlink($caminho);
        }
        throw $e;
    }
}

function solicitarHoras(PDO $pdo,int $aluno,int $certificado,mixed $horas): void
{
    $carga = horas($horas);
    transacao($pdo,function () use ($pdo,$aluno,$certificado,$carga) {
        $r = registro($pdo,'SELECT c.carga_horaria_declarada,a.id_instituicao FROM certificado c JOIN inscricao i ON i.id_inscricao=c.id_inscricao JOIN aluno a ON a.id_aluno=i.id_aluno WHERE c.id_certificado=? AND a.id_aluno=? FOR UPDATE',[$certificado,$aluno]);
        if ((float)$carga > (float)$r['carga_horaria_declarada']) throw new DomainException('As horas solicitadas não podem superar as horas do certificado.');
        sql($pdo,"INSERT INTO validacao_horas (id_certificado,id_instituicao,horas_solicitadas,status) VALUES (?,?,?,'Pendente')",[$certificado,$r['id_instituicao'],$carga]);
        notificar($pdo,$aluno,'Validação solicitada','Sua solicitação foi enviada à instituição.');
    });
}

function analisarHoras(PDO $pdo,int $instituicao,array $dados): void
{
    $id = inteiro($dados['id'] ?? null);
    $status = escolha($dados['status'] ?? null,['Aprovada','Parcialmente aprovada','Recusada']);
    $aprovadas = horas($dados['horas'] ?? null,0);
    $obs = texto($dados,'observacao',1000,false);
    transacao($pdo,function () use ($pdo,$instituicao,$id,$status,$aprovadas,$obs) {
        $r = registro($pdo,'SELECT v.status,v.horas_solicitadas,v.id_certificado,i.id_aluno FROM validacao_horas v JOIN certificado c ON c.id_certificado=v.id_certificado JOIN inscricao i ON i.id_inscricao=c.id_inscricao WHERE v.id_validacao=? AND v.id_instituicao=? FOR UPDATE',[$id,$instituicao]);
        if ($r['status'] !== 'Pendente') throw new DomainException('Esta solicitação já foi analisada.');
        $h = (float)$aprovadas; $s = (float)$r['horas_solicitadas'];
        if (($status==='Aprovada' && $h!==$s) || ($status==='Parcialmente aprovada' && ($h<=0 || $h >= $s)) || ($status==='Recusada' && $h!==0.0)) throw new DomainException('As horas aprovadas não correspondem à decisão selecionada.');
        sql($pdo,'UPDATE validacao_horas SET status=?,horas_aprovadas=?,observacao=?,data_analise=NOW() WHERE id_validacao=?',[$status,$aprovadas,$obs ?: null,$id]);
        sql($pdo,'UPDATE certificado SET status=? WHERE id_certificado=?',[$status==='Recusada'?'Recusado':'Aprovado',$r['id_certificado']]);
        notificar($pdo,(int)$r['id_aluno'],'Análise concluída','Sua solicitação foi analisada: '.$status.'. Consulte suas horas.');
    });
}

function avaliar(PDO $pdo,int $aluno,array $dados): void
{
    $evento = inteiro($dados['id'] ?? null); $nota = inteiro($dados['nota'] ?? null,1,5); $comentario = texto($dados,'comentario',1000,false);
    transacao($pdo,function () use ($pdo,$aluno,$evento,$nota,$comentario) {
        $r = registro($pdo,'SELECT i.presenca,i.status,TIMESTAMP(e.data_evento,e.horario_termino)<=NOW() AS encerrado FROM inscricao i JOIN evento e ON e.id_evento=i.id_evento WHERE i.id_aluno=? AND i.id_evento=? FOR UPDATE',[$aluno,$evento]);
        if (!$r['presenca'] || !$r['encerrado'] || $r['status']==='Cancelada') throw new DomainException('Somente participantes com presença confirmada após o término podem avaliar.');
        sql($pdo,'INSERT INTO avaliacao (id_aluno,id_evento,nota,comentario,data_avaliacao) VALUES (?,?,?,?,NOW())',[$aluno,$evento,$nota,$comentario ?: null]);
    });
}
