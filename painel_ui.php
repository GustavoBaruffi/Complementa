<?php
declare(strict_types=1);

function campoForm(string $nome,string $rotulo,mixed $valor='',string $tipo='text',int $max=150,bool $obrigatorio=true): void
{
    echo '<label>'.escapar($rotulo);
    if ($tipo==='textarea') {
        echo '<textarea name="'.escapar($nome).'" maxlength="'.$max.'"'.($obrigatorio?' required':'').'>'.escapar((string)$valor).'</textarea>';
    } else {
        echo '<input name="'.escapar($nome).'" type="'.escapar($tipo).'" maxlength="'.$max.'"'.($obrigatorio?' required':'').' value="'.escapar((string)$valor).'"';
        if ($tipo==='number') echo ' step="0.1" min="0" max="999"';
        if ($tipo==='file') echo ' accept="application/pdf,image/jpeg,image/png" data-certificate-input';
        echo '>';
    }
    echo '</label>';
    if ($tipo==='file') echo '<div class="certificate-preview" data-certificate-preview hidden></div>';
}

function selecionar(string $nome,string $rotulo,array $opcoes,mixed $atual=''): void
{
    echo '<label>'.escapar($rotulo).'<select name="'.escapar($nome).'" required>';
    foreach ($opcoes as $valor=>$texto) echo '<option value="'.escapar((string)$valor).'"'.((string)$valor===(string)$atual?' selected':'').'>'.escapar($texto).'</option>';
    echo '</select></label>';
}

function formulario(string $acao,int $id=0,bool $arquivo=false): void
{
    echo '<form method="post" class="form-stack"'.($arquivo?' enctype="multipart/form-data"':'').'>';
    campoCsrf();
    echo '<input type="hidden" name="acao" value="'.escapar($acao).'"><input type="hidden" name="id" value="'.$id.'">';
}

function botao(string $texto): void { echo '<button class="button" type="submit">'.escapar($texto).'</button></form>'; }

function opcaoEnvioInstituicao(): void
{
    echo '<label class="check-label"><input type="checkbox" name="enviar_instituicao" value="sim"> Enviar também à instituição agora</label>';
    echo '<small>O certificado aparecerá no painel da instituição vinculada ao aluno, com toda a carga horária informada para análise. Se não marcar, o aluno poderá conferir e encaminhar depois.</small>';
}

function inicioCard(string $titulo,string $classe=''): void { echo '<article class="detail-card workflow-card '.escapar($classe).'"><h2>'.escapar($titulo).'</h2>'; }
function dado(string $rotulo,mixed $valor): void {
    $texto = escapar((string)($valor ?? 'Não informado'));
    if (in_array($rotulo, ['Situação', 'Situação da análise'], true) && in_array($valor, ['Aprovada','Recusada','Recusado'], true)) {
        $texto = '<span class="'.($valor==='Aprovada'?'status-approved':'status-rejected').'">'.$texto.'</span>';
    }
    echo '<p><strong>'.escapar($rotulo).':</strong> '.$texto.'</p>';
}
function vazio(array $lista): void { if (!$lista) echo '<p class="empty-state">Nenhum registro encontrado.</p>'; }

function tabelaParticipantes(array $lista, array $filtros): void
{
    ?>
    <div class="participants-table-scroll" role="region" aria-label="Lista de participantes" tabindex="0">
        <table class="participants-table">
            <caption class="sr-only">Participantes inscritos nos seus eventos</caption>
            <thead><tr><th scope="col">Nome</th><th scope="col">Evento</th><th scope="col">Instituição</th><th scope="col">Situação</th><th scope="col">Papel</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $r): ?>
                <tr>
                    <td><a href="meus_eventos.php?<?= escapar(http_build_query(array_replace($filtros,['inscricao'=>(int)$r['id_inscricao']]))) ?>#participacao-<?= (int)$r['id_inscricao'] ?>"><?= escapar($r['nome']) ?></a></td>
                    <td><a href="evento.php?id=<?= (int)$r['id_evento'] ?>"><?= escapar($r['titulo']) ?></a></td>
                    <td><?= escapar($r['instituicao']) ?></td>
                    <td><?= escapar($r['status']) ?></td>
                    <td>Aluno</td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lista): ?><tr><td colspan="5" class="participants-empty">Nenhum participante encontrado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function dataRecebimento(?string $data): string
{
    return $data ? date('d/m/Y H:i', strtotime($data)).' (Brasília)' : 'Não registrado';
}

function listaAnalises(array $lista, int $selecionada): void
{
    $grupos = [];
    foreach ($lista as $r) $grupos[(int)$r['id_evento']][] = $r;
    ?>
    <p>Clique em um evento para ver os alunos. Selecione um nome para conferir o certificado e analisar as horas.</p>
    <?php foreach ($grupos as $idEvento=>$alunos):
        $pendentes = count(array_filter($alunos, fn($r) => $r['status']==='Pendente'));
        $aberto = in_array($selecionada, array_map(fn($r) => (int)$r['id_validacao'], $alunos), true);
    ?>
        <details class="participant-group" id="evento-<?= $idEvento ?>" <?= $aberto?'open':'' ?>>
            <summary class="participant-group-heading">
                <h2><?= escapar($alunos[0]['titulo']) ?></h2>
                <span><?= count($alunos) ?> <?= count($alunos)===1?'solicitação':'solicitações' ?> · <?= $pendentes ?> <?= $pendentes===1?'pendente':'pendentes' ?></span>
            </summary>
            <div class="participant-group-body">
                <div class="participants-table-scroll" role="region" aria-label="Solicitações de horas do evento" tabindex="0">
                    <table class="participants-table">
                        <caption class="sr-only">Solicitações de <?= escapar($alunos[0]['titulo']) ?></caption>
                        <thead><tr><th scope="col">Aluno</th><th scope="col">Situação</th><th scope="col">Recebido em</th><th scope="col">Horas solicitadas</th><th scope="col">Horas aprovadas</th></tr></thead>
                        <tbody>
                        <?php foreach ($alunos as $r): ?>
                            <tr>
                                <td><a href="painel.php?pagina=analises&amp;analise=<?= (int)$r['id_validacao'] ?>#analise-detalhes" <?= (int)$r['id_validacao']===$selecionada?'aria-current="true"':'' ?>><?= escapar($r['nome']) ?></a></td>
                                <td><span <?= match ($r['status']) { 'Aprovada'=>'class="status-approved"', 'Recusada','Recusado'=>'class="status-rejected"', default=>'' } ?>><?= escapar($r['status']) ?></span></td>
                                <td><?= escapar(dataRecebimento($r['data_recebimento'])) ?></td>
                                <td><?= escapar(number_format((float)$r['horas_solicitadas'],1,',','.')) ?></td>
                                <td><?= $r['status']==='Pendente'?'—':escapar(number_format((float)$r['horas_aprovadas'],1,',','.')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    <?php endforeach;
    if ($selecionada && !in_array($selecionada, array_map(fn($r) => (int)$r['id_validacao'], $lista), true)) {
        echo '<p class="empty-state">Solicitação não encontrada ou acesso não permitido.</p>';
    }
}

function menuPainel(string $perfil): array
{
    return match ($perfil) {
        'aluno'=>['inscricoes'=>'Minhas inscrições','certificados'=>'Certificados','horas'=>'Minhas horas','notificacoes'=>'Notificações','perfil'=>'Meu perfil'],
        'instituicao'=>['analises'=>'Analisar horas'],
        'equipe'=>['instituicao'=>'Instituições','categoria'=>'Categorias','organizador'=>'Organizadores'],
        default=>[],
    };
}
