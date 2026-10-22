<?php
declare(strict_types=1);

function cadastrosEquipe(): array
{
    return [
        'instituicao'=>['id'=>'id_instituicao','titulo'=>'Instituições','campos'=>['nome'=>150,'sigla'=>20,'cidade'=>100,'email'=>150]],
        'categoria'=>['id'=>'id_categoria','titulo'=>'Categorias','campos'=>['nome'=>80,'descricao'=>500]],
        'organizador'=>['id'=>'id_organizador','titulo'=>'Organizadores','campos'=>['nome'=>150,'tipo'=>30,'email'=>150,'telefone'=>20,'cidade'=>100]],
    ];
}

function salvarCadastro(PDO $pdo,string $tabela,array $dados): void
{
    $config = cadastrosEquipe()[$tabela] ?? throw new DomainException('Cadastro inválido.');
    $id = inteiro($dados['id'] ?? 0,0);
    $valores = [];
    foreach ($config['campos'] as $campo=>$limite) {
        $valores[$campo] = texto($dados,$campo,$limite,!in_array($campo,['sigla','descricao','telefone'],true));
    }
    if (isset($valores['email']) && !filter_var($valores['email'],FILTER_VALIDATE_EMAIL)) throw new DomainException('Informe um e-mail válido.');
    if ($tabela==='organizador') {
        escolha($valores['tipo'],['Faculdade','Empresa','Prefeitura','ONG','Associação','Instituição','Outro']);
        if (!$id) {
            require_once __DIR__.'/organizadores.php';
            criarOrganizador($pdo,$valores+['senha'=>texto($dados,'senha',72)]);
            return;
        }
    }
    $pk = $config['id'];
    if ($id) {
        registro($pdo,"SELECT $pk FROM $tabela WHERE $pk=?",[$id]);
        $sets = implode(',',array_map(fn($c)=>"$c=?",array_keys($valores)));
        sql($pdo,"UPDATE $tabela SET $sets WHERE $pk=?",[...array_values($valores),$id]);
    } else {
        $campos = implode(',',array_keys($valores));
        $marcas = implode(',',array_fill(0,count($valores),'?'));
        sql($pdo,"INSERT INTO $tabela ($campos) VALUES ($marcas)",array_values($valores));
    }
}

function excluirCadastro(PDO $pdo,string $tabela,int $id): void
{
    $config = cadastrosEquipe()[$tabela] ?? throw new DomainException('Cadastro inválido.');
    $pk = $config['id'];
    transacao($pdo,function () use ($pdo,$tabela,$pk,$id) {
        registro($pdo,"SELECT $pk FROM $tabela WHERE $pk=? FOR UPDATE",[$id]);
        $dependencias=match($tabela) {
            'instituicao'=>[['aluno','id_instituicao'],['validacao_horas','id_instituicao'],['usuario_gestao','id_instituicao']],
            'categoria'=>[['evento','id_categoria']],
            'organizador'=>[['evento','id_organizador']],
        };
        foreach ($dependencias as [$t,$c]) if (sql($pdo,"SELECT 1 FROM $t WHERE $c=? LIMIT 1",[$id])->fetchColumn()) throw new DomainException('Não é possível excluir: existem registros vinculados a este cadastro.');
        sql($pdo,"DELETE FROM $tabela WHERE $pk=?",[$id]);
    });
}

function salvarPerfil(PDO $pdo,int $aluno,array $dados): string
{
    $v = [];
    foreach (['nome'=>100,'email'=>150,'matricula'=>20,'curso'=>100,'cidade'=>100,'data_nascimento'=>10] as $campo=>$max) $v[$campo]=texto($dados,$campo,$max);
    if (!filter_var($v['email'],FILTER_VALIDATE_EMAIL)) throw new DomainException('E-mail inválido.');
    $d = DateTimeImmutable::createFromFormat('!Y-m-d',$v['data_nascimento']);
    if (!$d || $d->format('Y-m-d')!==$v['data_nascimento'] || $d>new DateTimeImmutable('today') || (int)$d->format('Y')<1000) throw new DomainException('Data de nascimento inválida.');
    $v['meta_horas']=inteiro($dados['meta_horas'] ?? null,0,999);
    $senha = texto($dados,'senha_atual',72);
    $atual = registro($pdo,'SELECT senha_hash FROM aluno WHERE id_aluno=?',[$aluno]);
    if (!is_string($atual['senha_hash']) || !password_verify($senha,$atual['senha_hash'])) throw new DomainException('Senha atual incorreta.');
    $sets=implode(',',array_map(fn($c)=>"$c=?",array_keys($v)));
    sql($pdo,"UPDATE aluno SET $sets WHERE id_aluno=?",[...array_values($v),$aluno]);
    return $v['nome'];
}

function excluirEvento(PDO $pdo,int $organizador,int $id): void
{
    transacao($pdo,function () use ($pdo,$organizador,$id) {
        registro($pdo,'SELECT id_evento FROM evento WHERE id_evento=? AND id_organizador=? FOR UPDATE',[$id,$organizador]);
        foreach (['inscricao','avaliacao'] as $t) if (sql($pdo,"SELECT 1 FROM $t WHERE id_evento=? LIMIT 1",[$id])->fetchColumn()) throw new DomainException('Não é possível excluir: existem registros vinculados ao evento.');
        sql($pdo,'DELETE FROM evento WHERE id_evento=? AND id_organizador=?',[$id,$organizador]);
    });
}

function erroFluxo(Throwable $e): string
{
    if ($e instanceof DomainException || $e instanceof InvalidArgumentException) return $e->getMessage();
    if ($e instanceof PDOException) {
        return match ((int)($e->errorInfo[1] ?? 0)) {
            1062 => 'Este registro já existe. Não é permitido duplicá-lo.',
            1451 => 'Não é possível excluir: existem registros vinculados a este cadastro.',
            1452 => 'O cadastro relacionado não existe mais. Atualize a página.',
            default => falhaBanco($e),
        };
    }
    error_log('Complementa: falha interna no fluxo: '.get_class($e));
    http_response_code(500);
    return 'Não foi possível concluir a operação. Tente novamente.';
}
