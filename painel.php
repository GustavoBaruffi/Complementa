<?php
declare(strict_types=1);
require_once __DIR__.'/app.php';
require_once __DIR__.'/gestao_dados.php';
require_once __DIR__.'/painel_ui.php';
$u = exigirPerfil('aluno','organizador','instituicao','equipe');
if ($u['perfil'] === 'organizador') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
    redirecionar('meus_eventos.php');
}
$menu = menuPainel($u['perfil']);
$pagina = is_string($_GET['pagina'] ?? null) ? $_GET['pagina'] : array_key_first($menu);
if (!isset($menu[$pagina])) { http_response_code(404); exit('Página não encontrada.'); }
$erros=[]; $lista=[]; $perfil=[]; $total=0; $meta=0; $editar=[];
$analiseSelecionada = (int)filter_var($_GET['analise'] ?? ($_POST['id'] ?? 0), FILTER_VALIDATE_INT);
$cfg=cadastrosEquipe()[$pagina] ?? []; $pk=$cfg['id'] ?? '';
if ($_SERVER['REQUEST_METHOD']==='POST') verificarCsrf();
elseif ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); header('Allow: GET, POST'); exit; }
try {
    $pdo=obterConexao();
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $acao=campo('acao');
        $permitidas=match($pagina) {
            'inscricoes'=>['cancelar','reativar','enviar_certificado','avaliar'],
            'certificados'=>['solicitar'], 'notificacoes'=>['ler'], 'perfil'=>['perfil'],
            'analises'=>['analisar'],
            'instituicao','categoria','organizador'=>['salvar','excluir'],default=>[],
        };
        if (!in_array($acao,$permitidas,true)) throw new DomainException('Operação não permitida nesta página.');
        $id=inteiro($_POST['id'] ?? 0,0);
        switch ($acao) {
            case 'cancelar': cancelarInscricao($pdo,$u['id'],$id); break;
            case 'reativar':
                $inscricao=registro($pdo,'SELECT id_evento,status FROM inscricao WHERE id_inscricao=? AND id_aluno=?',[$id,$u['id']]);
                if ($inscricao['status']!=='Cancelada') throw new DomainException('Somente inscrições canceladas podem ser reativadas.');
                inscrever($pdo,$u['id'],(int)$inscricao['id_evento']); break;
            case 'enviar_certificado': enviarCertificado($pdo,$u['id'],$_POST,$_FILES['arquivo'] ?? []); break;
            case 'avaliar': avaliar($pdo,$u['id'],$_POST); break;
            case 'solicitar': solicitarHoras($pdo,$u['id'],$id,campo('horas')); break;
            case 'ler':
                registro($pdo,'SELECT id_notificacao FROM notificacao WHERE id_notificacao=? AND id_aluno=?',[$id,$u['id']]);
                sql($pdo,'UPDATE notificacao SET lida=1 WHERE id_notificacao=? AND id_aluno=?',[$id,$u['id']]); break;
            case 'perfil': $_SESSION['usuario']['nome']=salvarPerfil($pdo,$u['id'],$_POST); break;
            case 'analisar':
                $conta=registro($pdo,"SELECT id_instituicao FROM usuario_gestao WHERE id_usuario=? AND perfil='instituicao'",[$u['id']]);
                analisarHoras($pdo,(int)$conta['id_instituicao'],$_POST); break;
            case 'salvar': salvarCadastro($pdo,$pagina,$_POST); break;
            case 'excluir':
                if (campo('confirmar')!=='sim') throw new DomainException('Confirme a exclusão do cadastro.');
                excluirCadastro($pdo,$pagina,$id); break;
        }
        $_SESSION['aviso']=match ($acao) {
            'reativar'=>'Inscrição reativada. Sua participação está confirmada.',
            'cancelar'=>'Inscrição cancelada. Você pode se inscrever novamente enquanto o evento estiver publicado e não tiver começado.',
            'enviar_certificado'=>campo('enviar_instituicao')==='sim'
                ? 'Certificado disponível para o aluno e enviado à instituição para análise.'
                : 'Certificado disponível para o aluno. O encaminhamento à instituição pode ser feito em Certificados.',
            'solicitar'=>'Certificado enviado à instituição. Acompanhe o resultado em Certificados ou Minhas horas.',
            default=>'Operação concluída.',
        };
        redirecionar('painel.php?pagina='.$pagina.($pagina==='analises'?'&analise='.$id.'#analise-detalhes':''));
    }
} catch (Throwable $e) { $erros[]=erroFluxo($e); }
try {
    if (isset($pdo)) {
        switch ($pagina) {
            case 'inscricoes':
                $lista=sql($pdo,'SELECT i.id_inscricao,i.id_evento,i.status,i.presenca,e.titulo,e.data_evento,e.status AS evento_status,TIMESTAMP(e.data_evento,e.horario_inicio)>NOW() AS futuro,c.id_certificado,a.id_avaliacao FROM inscricao i JOIN evento e ON e.id_evento=i.id_evento LEFT JOIN certificado c ON c.id_inscricao=i.id_inscricao LEFT JOIN avaliacao a ON a.id_evento=i.id_evento AND a.id_aluno=i.id_aluno WHERE i.id_aluno=? ORDER BY e.data_evento DESC,i.id_inscricao DESC',[$u['id']])->fetchAll(); break;
            case 'certificados':
                $lista=sql($pdo,'SELECT c.id_certificado,c.carga_horaria_declarada,c.data_envio,e.titulo,v.id_validacao,v.status AS status_validacao,v.horas_solicitadas,v.horas_aprovadas,v.observacao,v.data_analise,t.nome AS instituicao FROM certificado c JOIN inscricao i ON i.id_inscricao=c.id_inscricao JOIN aluno a ON a.id_aluno=i.id_aluno JOIN evento e ON e.id_evento=i.id_evento LEFT JOIN validacao_horas v ON v.id_certificado=c.id_certificado JOIN instituicao t ON t.id_instituicao=COALESCE(v.id_instituicao,a.id_instituicao) WHERE i.id_aluno=? ORDER BY c.id_certificado DESC',[$u['id']])->fetchAll(); break;
            case 'horas':
                $meta=(int)registro($pdo,'SELECT meta_horas FROM aluno WHERE id_aluno=?',[$u['id']])['meta_horas'];
                $lista=sql($pdo,'SELECT v.id_validacao,v.status,v.horas_solicitadas,v.horas_aprovadas,v.observacao,v.data_analise,e.titulo FROM validacao_horas v JOIN certificado c ON c.id_certificado=v.id_certificado JOIN inscricao i ON i.id_inscricao=c.id_inscricao JOIN evento e ON e.id_evento=i.id_evento WHERE i.id_aluno=? ORDER BY v.id_validacao DESC',[$u['id']])->fetchAll();
                foreach ($lista as $r) if (in_array($r['status'],['Aprovada','Parcialmente aprovada'],true)) $total+=(float)$r['horas_aprovadas'];
                break;
            case 'notificacoes': $lista=sql($pdo,'SELECT id_notificacao,titulo,mensagem,data_criacao,lida FROM notificacao WHERE id_aluno=? ORDER BY id_notificacao DESC',[$u['id']])->fetchAll(); break;
            case 'perfil': $perfil=registro($pdo,'SELECT a.nome,a.email,a.matricula,a.data_nascimento,a.curso,a.cidade,a.meta_horas,t.nome AS instituicao FROM aluno a JOIN instituicao t ON t.id_instituicao=a.id_instituicao WHERE a.id_aluno=?',[$u['id']]); break;
            case 'analises':
                $conta=registro($pdo,"SELECT id_instituicao FROM usuario_gestao WHERE id_usuario=? AND perfil='instituicao'",[$u['id']]);
                $lista=sql($pdo,'SELECT v.id_validacao,v.id_certificado,v.data_recebimento,v.status,v.horas_solicitadas,v.horas_aprovadas,v.observacao,a.nome,e.id_evento,e.titulo,c.carga_horaria_declarada FROM validacao_horas v JOIN certificado c ON c.id_certificado=v.id_certificado JOIN inscricao i ON i.id_inscricao=c.id_inscricao JOIN aluno a ON a.id_aluno=i.id_aluno JOIN evento e ON e.id_evento=i.id_evento WHERE v.id_instituicao=? ORDER BY e.data_evento DESC,e.id_evento,a.nome,v.id_validacao DESC',[$conta['id_instituicao']])->fetchAll(); break;
            default:
                $cfg=cadastrosEquipe()[$pagina]; $pk=$cfg['id'];
                $campos=implode(',',array_keys($cfg['campos']));
                $lista=sql($pdo,"SELECT $pk,$campos FROM $pagina ORDER BY nome")->fetchAll();
                if (isset($_GET['editar'])) {
                    $id=inteiro($_GET['editar']);
                    $editar=registro($pdo,"SELECT $pk,$campos FROM $pagina WHERE $pk=?",[$id]);
                }
        }
    }
} catch (Throwable $e) { $erros[]=erroFluxo($e); }
cabecalho($menu[$pagina]);
?>
<main class="container section">
    <h1><?= escapar($menu[$pagina]) ?></h1>
    <nav class="workflow-nav" aria-label="Área do usuário">
        <?php foreach ($menu as $p=>$titulo): ?><a class="button button-small" href="painel.php?pagina=<?= escapar($p) ?>" <?= $p===$pagina?'aria-current="page"':'' ?>><?= escapar($titulo) ?></a><?php endforeach; ?>
    </nav>
    <?php mostrarErros(array_unique($erros)); ?>
    <?php if ($pagina==='perfil' && $perfil): ?>
        <div class="form-card form-wide">
        <?php dado('Instituição',$perfil['instituicao']); ?>
        <p>Para alterar a instituição, solicite atendimento à equipe. Confirme sua senha para salvar os demais dados.</p>
        <?php
        formulario('perfil');
        foreach (['nome'=>'Nome','email'=>'E-mail','matricula'=>'Matrícula','data_nascimento'=>'Nascimento','curso'=>'Curso','cidade'=>'Cidade','meta_horas'=>'Meta de horas'] as $c=>$rotulo) {
            $tipo=match($c) {'email'=>'email','data_nascimento'=>'date','meta_horas'=>'number',default=>'text'};
            campoForm($c,$rotulo,($_SERVER['REQUEST_METHOD']==='POST'?campo($c):$perfil[$c]),$tipo,match($c){'email'=>150,'matricula'=>20,default=>100});
        }
        campoForm('senha_atual','Senha atual','','password',72); botao('Salvar perfil'); ?>
        </div>
    <?php elseif ($u['perfil']==='equipe'): ?>
        <div class="form-card form-wide">
        <h2><?= $editar?'Editar cadastro':'Novo cadastro' ?></h2>
        <?php formulario('salvar',(int)($editar[$pk] ?? 0));
        foreach ($cfg['campos'] as $c=>$max) {
            if ($c==='tipo') selecionar('tipo','Tipo',array_combine($tipos=['Faculdade','Empresa','Prefeitura','ONG','Associação','Instituição','Outro'],$tipos),$editar[$c] ?? '');
            else campoForm($c,ucfirst($c),$editar[$c] ?? '',$c==='email'?'email':($c==='descricao'?'textarea':'text'),$max,!in_array($c,['sigla','telefone','descricao'],true));
        }
        if ($pagina==='organizador' && !$editar) campoForm('senha','Senha inicial (mínimo 8 caracteres)','','password',72);
        botao('Salvar cadastro'); ?>
        </div>
        <?php vazio($lista); foreach ($lista as $r): inicioCard($r['nome']);
        foreach ($cfg['campos'] as $c=>$max) if ($c!=='nome') dado(ucfirst($c),$r[$c]); ?>
        <a class="text-link" href="painel.php?pagina=<?= escapar($pagina) ?>&amp;editar=<?= (int)$r[$pk] ?>">Editar</a>
        <?php formulario('excluir',(int)$r[$pk]); ?>
        <label class="check-label"><input type="checkbox" name="confirmar" value="sim" required> Confirmo a exclusão deste cadastro</label>
        <?php botao('Excluir cadastro'); echo '</article>'; endforeach; ?>
    <?php else: ?>
        <?php if ($pagina==='horas'): ?>
        <div class="detail-card"><h2><?= escapar(number_format($total,1,',','.')) ?> horas reconhecidas</h2>
        <p>Meta: <?= $meta ?> horas · Faltam <?= escapar(number_format(max(0,$meta-$total),1,',','.')) ?> horas.</p>
        <?php if ($meta>0): ?><progress aria-label="Progresso de horas reconhecidas" max="<?= $meta ?>" value="<?= min($meta,$total) ?>"></progress><?php endif; ?></div>
        <?php endif; ?>
        <?php if ($pagina==='analises') listaAnalises($lista, $analiseSelecionada); ?>
        <?php vazio($lista); foreach ($lista as $r):
            if ($pagina==='analises' && (int)$r['id_validacao']!==$analiseSelecionada) continue;
            inicioCard($r['titulo'] ?? 'Registro', $pagina==='inscricoes' ? 'registration-card'.($r['status']==='Cancelada' ? ' registration-cancelled' : '') : ''); ?>
            <?php if ($pagina==='inscricoes'): ?>
                <div class="registration-meta">
                    <span><?= escapar(date('d/m/Y',strtotime($r['data_evento']))) ?></span>
                    <span <?= $r['status']==='Cancelada'?'class="registration-badge"':'' ?>><?= escapar($r['status']==='Cancelada'?'Inscrição cancelada':$r['status']) ?></span>
                    <span>Presença <?= $r['presenca']?'confirmada':'não confirmada' ?></span>
                </div>
                <div class="registration-actions">
                <a class="text-link" href="evento.php?id=<?= (int)$r['id_evento'] ?>">Ver evento</a>
                <?php if (in_array($r['status'],['Pendente','Confirmada'],true) && !$r['presenca'] && $r['futuro']) { formulario('cancelar',(int)$r['id_inscricao']); botao('Cancelar inscrição'); } ?>
                <?php if ($r['status']==='Cancelada'): ?>
                    <?php if ($r['evento_status']==='Publicado' && $r['futuro'] && !$r['presenca'] && !$r['id_certificado']): ?>
                        <?php formulario('reativar',(int)$r['id_inscricao']); botao('Inscrever-me novamente'); ?>
                    <?php else: ?>
                        <span class="registration-note">Reinscrição indisponível.</span>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
                <?php if ($r['presenca'] && !$r['id_certificado']): ?>
                    <h3>Enviar certificado após o término do evento</h3><p>PDF, JPG ou PNG, até 5 MB.</p>
                    <?php formulario('enviar_certificado',(int)$r['id_inscricao'],true); campoForm('arquivo','Arquivo','','file'); campoForm('horas','Carga horária declarada','','number'); opcaoEnvioInstituicao(); botao('Enviar certificado'); ?>
                <?php endif; ?>
                <?php if ($r['id_certificado']): ?><p><a class="text-link" href="painel.php?pagina=certificados">Acessar certificado e acompanhar envio à instituição</a></p><?php endif; ?>
                <?php if ($r['presenca'] && !$r['id_avaliacao']): ?>
                    <h3>Avaliar participação</h3>
                    <?php formulario('avaliar',(int)$r['id_evento']); selecionar('nota','Nota',[1=>'1',2=>'2',3=>'3',4=>'4',5=>'5']); campoForm('comentario','Comentário (opcional)','','textarea',1000,false); botao('Enviar avaliação'); ?>
                <?php endif; ?>
            <?php elseif ($pagina==='certificados'): ?>
                <?php dado('Disponível desde',$r['data_envio']); ?>
                <div class="certificate-preview" data-certificate-url="arquivo.php?id=<?= (int)$r['id_certificado'] ?>" aria-label="Prévia do certificado"></div>
                <?php dado('Instituição',$r['instituicao']); ?>
                <?php if (!$r['id_validacao']): ?>
                    <?php dado('Situação','Aguardando envio à instituição'); ?>
                    <?php dado('Horas declaradas',$r['carga_horaria_declarada']); ?>
                    <p>Este certificado ainda não foi enviado à instituição. Confira o arquivo e informe as horas que deseja validar.</p>
                    <?php formulario('solicitar',(int)$r['id_certificado']); campoForm('horas','Horas solicitadas',$r['carga_horaria_declarada'],'number'); botao('Enviar certificado à instituição'); ?>
                <?php else: ?>
                    <?php dado('Situação da análise',$r['status_validacao']==='Pendente'?'Aguardando análise da instituição':$r['status_validacao']); dado('Horas solicitadas',$r['horas_solicitadas']); ?>
                    <?php if ($r['status_validacao']!=='Pendente') { dado('Horas reconhecidas',$r['horas_aprovadas']); dado('Data da análise',$r['data_analise']); dado('Observação',$r['observacao']); } ?>
                    <a class="text-link" href="painel.php?pagina=horas">Acompanhar validação das horas</a>
                <?php endif; ?>
                <a class="text-link" href="arquivo.php?id=<?= (int)$r['id_certificado'] ?>">Baixar certificado</a>
            <?php elseif ($pagina==='horas'): ?>
                <?php foreach (['status'=>'Situação','horas_solicitadas'=>'Horas solicitadas','horas_aprovadas'=>'Horas reconhecidas','data_analise'=>'Data da análise','observacao'=>'Observação'] as $c=>$rotulo) dado($rotulo,$r[$c]); ?>
            <?php elseif ($pagina==='notificacoes'): ?>
                <?php dado('Data',$r['data_criacao']); dado('Mensagem',$r['mensagem']); dado('Leitura',$r['lida']?'Lida':'Não lida');
                if (!$r['lida']) { formulario('ler',(int)$r['id_notificacao']); botao('Marcar como lida'); } ?>
            <?php elseif ($pagina==='analises'): ?>
                <div id="analise-detalhes"><a class="close-control" href="painel.php?pagina=analises" aria-label="Fechar detalhes" title="Fechar detalhes">×</a></div>
                <?php dado('Recebido em', dataRecebimento($r['data_recebimento'])); ?>
                <div class="certificate-preview" data-certificate-url="arquivo.php?id=<?= (int)$r['id_certificado'] ?>" aria-label="Prévia do certificado"></div>
                <?php dado('Aluno',$r['nome']); dado('Situação',$r['status']); ?>
                <?php if ($r['status']==='Pendente') {
                    dado('Horas declaradas',$r['carga_horaria_declarada']); dado('Horas solicitadas',$r['horas_solicitadas']);
                    formulario('analisar',(int)$r['id_validacao']); selecionar('status','Decisão',array_combine($decisoes=['Aprovada','Parcialmente aprovada','Recusada'],$decisoes));
                    campoForm('horas','Horas aprovadas (zero para recusa)', $r['horas_solicitadas'], 'number'); campoForm('observacao','Observação (opcional)','','textarea',1000,false); botao('Concluir análise');
                } else { dado('Horas aprovadas',rtrim(rtrim(number_format((float)$r['horas_aprovadas'],1,',',''),'0'),',').'h'); dado('Observação',$r['observacao']); } ?>
                <p><a class="text-link" href="arquivo.php?id=<?= (int)$r['id_certificado'] ?>">Baixar certificado</a></p>
            <?php endif; ?>
        </article><?php endforeach; ?>
    <?php endif; ?>
</main>
<?php rodape(); ?>
