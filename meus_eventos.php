<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/gestao_dados.php';
require_once __DIR__ . '/painel_ui.php';
$organizador = exigirOrganizador();
$eventos = [];
$participantes = [];
$eventoAberto = 0;
$inscricaoSelecionada = 0;
$selecionado = null;
$lista = [];
$busca = '';
$situacao = '';
$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') verificarCsrf();
elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET, POST'); exit; }
try {
    $pdo = obterConexao();
    $eventoAberto = inteiro($_GET['evento'] ?? 0, 0);
    $inscricaoSelecionada = inteiro($_GET['inscricao'] ?? 0, 0);
    $busca = texto($_GET, 'q', 150, false);
    $situacao = escolha($_GET['situacao'] ?? '', ['', 'Pendente','Confirmada','Cancelada','Concluída']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = escolha(campo('acao'), ['participante','enviar_certificado_organizador']);
        $inscricao = registro($pdo, 'SELECT i.id_evento FROM inscricao i JOIN evento e ON e.id_evento=i.id_evento WHERE i.id_inscricao=? AND e.id_organizador=?', [inteiro($_POST['id'] ?? null),$organizador['id']]);
        $eventoAberto = (int)$inscricao['id_evento'];
        $inscricaoSelecionada = inteiro($_POST['id'] ?? null);
        if ($acao === 'participante') {
            participante($pdo, $organizador['id'], $_POST);
            $_SESSION['aviso'] = 'Participação atualizada.';
        } else {
            enviarCertificado($pdo, $organizador['id'], $_POST, $_FILES['arquivo'] ?? [], true);
            $_SESSION['aviso'] = campo('enviar_instituicao') === 'sim'
                ? 'Certificado disponível para o aluno e enviado à instituição para análise.'
                : 'Certificado disponível para o aluno encaminhar à instituição.';
        }
        redirecionar('meus_eventos.php?'.http_build_query(['evento'=>$eventoAberto,'q'=>$busca,'situacao'=>$situacao,'inscricao'=>$inscricaoSelecionada]).'#participacao-'.$inscricaoSelecionada);
    }
} catch (Throwable $erro) {
    $erros[] = erroFluxo($erro);
}
try {
    if (isset($pdo)) {
        $eventos = listarEventos($pdo, $organizador['id']);
        $todos = sql($pdo, 'SELECT i.id_evento,i.id_inscricao,i.status,i.presenca,a.nome,t.nome AS instituicao,e.titulo,e.carga_horaria,e.status AS evento_status,TIMESTAMP(e.data_evento,e.horario_termino)<=NOW() AS encerrado,c.id_certificado,v.id_validacao FROM inscricao i JOIN aluno a ON a.id_aluno=i.id_aluno JOIN evento e ON e.id_evento=i.id_evento LEFT JOIN certificado c ON c.id_inscricao=i.id_inscricao LEFT JOIN validacao_horas v ON v.id_certificado=c.id_certificado JOIN instituicao t ON t.id_instituicao=COALESCE(v.id_instituicao,a.id_instituicao) WHERE e.id_organizador=? ORDER BY a.nome,i.id_inscricao', [$organizador['id']])->fetchAll();
        foreach ($todos as $r) {
            if ((int)$r['id_inscricao'] === $inscricaoSelecionada) $selecionado = $r;
            if ($eventoAberto && (int)$r['id_evento'] !== $eventoAberto) continue;
            if ($situacao !== '' && $r['status'] !== $situacao) continue;
            if ($busca !== '' && !preg_match('/'.preg_quote($busca,'/').'/iu', $r['nome'].' '.$r['titulo'].' '.$r['instituicao'])) continue;
            $lista[] = $r;
            $participantes[$r['id_evento']][] = $r;
        }
        if ($inscricaoSelecionada && !$selecionado) throw new DomainException('Participante não encontrado ou acesso não permitido.');
    }
} catch (Throwable $erro) {
    $erros[] = erroFluxo($erro);
}
cabecalho('Meus eventos');
$filtros = ['q'=>$busca, 'evento'=>$eventoAberto, 'situacao'=>$situacao];
?>
<main class="container section participants-page">
    <div class="section-heading">
        <div><p class="eyebrow">Área do organizador</p><h1>Meus eventos</h1></div>
        <a class="button button-small" href="novo_evento.php">Cadastrar evento</a>
    </div>
    <?php mostrarErros($erros); ?>
    <p>Clique em um evento para abrir ou recolher a lista de participantes.</p>
    <form method="get" class="participants-filters" role="search" aria-label="Pesquisar participantes">
        <label><span class="sr-only">Pesquisar pessoas, eventos ou instituições</span><input type="search" name="q" maxlength="150" value="<?= escapar($busca) ?>" placeholder="Pesquisar pessoas"></label>
        <label><span class="sr-only">Filtrar por evento</span><select name="evento"><option value="0">Todos os eventos</option>
            <?php foreach ($eventos as $evento): ?><option value="<?= (int)$evento['id_evento'] ?>" <?= $eventoAberto===(int)$evento['id_evento']?'selected':'' ?>><?= escapar($evento['titulo']) ?></option><?php endforeach; ?>
        </select></label>
        <label><span class="sr-only">Filtrar por situação</span><select name="situacao">
            <?php foreach ([''=>'Todas as situações','Pendente'=>'Pendente','Confirmada'=>'Confirmada','Cancelada'=>'Cancelada','Concluída'=>'Concluída'] as $valor=>$rotulo): ?><option value="<?= escapar($valor) ?>" <?= $situacao===$valor?'selected':'' ?>><?= escapar($rotulo) ?></option><?php endforeach; ?>
        </select></label>
        <button type="submit" class="participants-filter-button">Filtrar</button>
        <?php if ($busca!=='' || $eventoAberto || $situacao!==''): ?><a href="meus_eventos.php">Limpar filtros</a><?php endif; ?>
    </form>
    <p class="participants-count" role="status"><?= count($lista) ?> <?= count($lista)===1?'inscrição encontrada':'inscrições encontradas' ?>. Selecione um participante para gerenciar presença e certificado.</p>
    <?php if (!$eventos && !$erros): ?><p class="empty-state">Você ainda não cadastrou eventos.</p><?php endif; ?>
    <?php $gruposExibidos = 0; ?>
        <?php foreach ($eventos as $evento):
            $idEvento=(int)$evento['id_evento'];
            if ($eventoAberto && $idEvento!==$eventoAberto) continue;
            $inscritos=$participantes[$idEvento] ?? [];
            if (!$inscritos && ($busca!=='' || $situacao!=='')) continue;
            $gruposExibidos++;
        ?>
        <details class="participant-group" id="evento-<?= $idEvento ?>" <?= ($eventoAberto===$idEvento || $busca!=='' || $situacao!=='' || ($selecionado && (int)$selecionado['id_evento']===$idEvento))?'open':'' ?>>
            <summary class="participant-group-heading"><h2><?= escapar($evento['titulo']) ?></h2><span><?= count($inscritos) ?> inscrito<?= count($inscritos)===1?'':'s' ?></span></summary>
            <div class="participant-group-body">
                <a class="text-link" href="evento.php?id=<?= $idEvento ?>">Ver detalhes do evento</a>
                <?php tabelaParticipantes($inscritos,$filtros); ?>
            </div>
        </details>
        <?php endforeach; ?>
        <?php if (!$gruposExibidos && $eventos): ?><p class="empty-state">Nenhum participante encontrado com esses filtros.</p><?php endif; ?>
    <?php if ($selecionado): $r=$selecionado; ?>
    <section class="detail-card workflow-card participant-management" id="participacao-<?= (int)$r['id_inscricao'] ?>" aria-labelledby="participacao-titulo">
        <a class="close-control" href="meus_eventos.php?<?= escapar(http_build_query($filtros)) ?>" aria-label="Fechar detalhes" title="Fechar detalhes">×</a>
        <h2 id="participacao-titulo"><?= escapar($r['nome']) ?></h2>
        <?php dado('Evento',$r['titulo']); dado('Instituição',$r['instituicao']); ?>
        <?php formulario('participante',(int)$r['id_inscricao']); selecionar('status','Situação',array_combine($estados=['Pendente','Confirmada','Cancelada','Concluída'],$estados),$r['status']); selecionar('presenca','Presença',[0=>'Não confirmada',1=>'Confirmada'],$r['presenca']); botao('Atualizar participação'); ?>
        <?php if ($r['id_certificado']): ?>
            <p>Certificado disponível na área do aluno.</p>
            <?php dado('Envio à instituição',$r['id_validacao']?'Enviado para análise':'Aguardando encaminhamento pelo aluno'); ?>
        <?php elseif ($r['status']==='Concluída' && $r['presenca'] && $r['encerrado'] && !in_array($r['evento_status'],['Rascunho','Cancelado'],true)): ?>
            <h3>Enviar certificado ao participante</h3><p>PDF, JPG ou PNG, até 5 MB. O aluno receberá o arquivo na área de Certificados.</p>
            <?php formulario('enviar_certificado_organizador',(int)$r['id_inscricao'],true); campoForm('arquivo','Certificado','','file'); campoForm('horas','Carga horária do certificado',$r['carga_horaria'],'number'); opcaoEnvioInstituicao(); botao('Enviar certificado ao aluno'); ?>
        <?php else: ?>
            <p>Para enviar o certificado, aguarde o término de um evento válido, confirme a presença e salve a situação como Concluída.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>
<?php rodape(); ?>
