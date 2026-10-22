<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
require_once __DIR__.'/gestao_dados.php';
require_once __DIR__.'/painel_ui.php';
$avaliacoes=[];
$evento = null;
$erros = [];
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id) {
    try {
        $consulta = obterConexao()->prepare('SELECT e.id_evento,e.id_organizador,e.titulo,e.descricao,e.data_evento,e.horario_inicio,e.horario_termino,e.carga_horaria,e.modalidade,e.cidade,e.local_evento,e.link,e.status,e.validade_horas, c.nome AS categoria, o.nome AS organizador
            FROM evento e JOIN categoria c ON c.id_categoria = e.id_categoria
            JOIN organizador o ON o.id_organizador = e.id_organizador
            WHERE e.id_evento = :id');
        $consulta->execute(['id' => $id]);
        $evento = $consulta->fetch();
        if ($evento && $evento['status'] === 'Rascunho'
            && ((usuario()['perfil'] ?? '') !== 'organizador'
                || (usuario()['id'] ?? 0) !== (int) $evento['id_organizador'])) {
            $evento = null;
        }
    } catch (PDOException $erro) {
        $erros[] = falhaBanco($erro);
    }
}
if (!$evento && !$erros) {
    http_response_code(404);
}
if ($evento) {
    try {
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $u=exigirPerfil('aluno'); verificarCsrf();
            inscrever(obterConexao(),$u['id'],(int)$evento['id_evento']);
            $_SESSION['aviso']='Inscrição confirmada.';
            redirecionar('painel.php?pagina=inscricoes');
        }
        $avaliacoes=sql(obterConexao(),'SELECT nota,comentario,data_avaliacao FROM avaliacao WHERE id_evento=? ORDER BY data_avaliacao DESC',[(int)$evento['id_evento']])->fetchAll();
    } catch (Throwable $e) { $erros[]=erroFluxo($e); }
}
cabecalho($evento ? $evento['titulo'] : 'Evento não encontrado');
?>
<main class="container section">
    <a class="text-link" href="eventos.php">← Voltar aos eventos</a>
    <?php mostrarErros($erros); ?>
    <?php if ($evento): ?>
        <article class="detail-card">
            <span class="event-category"><?= escapar($evento['categoria']) ?></span>
            <h1><?= escapar($evento['titulo']) ?></h1>
            <dl class="event-info">
                <dt>Organizador</dt><dd><?= escapar($evento['organizador']) ?></dd>
                <dt>Data e horário</dt><dd><?= escapar(date('d/m/Y', strtotime($evento['data_evento']))) ?> · <?= escapar(substr($evento['horario_inicio'], 0, 5)) ?> às <?= escapar(substr($evento['horario_termino'], 0, 5)) ?> (horário de Brasília)</dd>
                <dt>Modalidade</dt><dd><?= escapar($evento['modalidade']) ?></dd>
                <dt>Cidade</dt><dd><?= escapar($evento['cidade'] ?: 'Não informada') ?></dd>
                <dt>Local</dt><dd><?= escapar($evento['local_evento']) ?></dd>
                <dt>Carga horária</dt><dd><?= escapar(number_format((float) $evento['carga_horaria'], 1, ',', '.')) ?> horas</dd>
                <dt>Status</dt><dd><?= escapar($evento['status']) ?></dd>
                <dt>Validade das horas</dt><dd><?= escapar($evento['validade_horas']) ?></dd>
                <?php if ($evento['link']): ?>
                    <dt>Link</dt><dd>
                    <?php if (filter_var($evento['link'], FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($evento['link'], PHP_URL_SCHEME)), ['http', 'https'], true)): ?>
                        <a class="text-link" href="<?= escapar($evento['link']) ?>" rel="noopener noreferrer"><?= escapar($evento['link']) ?></a>
                    <?php else: ?>
                        <?= escapar($evento['link']) ?>
                    <?php endif; ?>
                    </dd>
                <?php endif; ?>
            </dl>
            <?php if ((usuario()['perfil'] ?? '')==='aluno' && $evento['status']==='Publicado') { formulario('inscrever'); botao('Inscrever-me'); } ?>
            <?php if ((usuario()['perfil'] ?? '')==='organizador' && (int)usuario()['id']===(int)$evento['id_organizador']): ?>
                <a class="button" href="novo_evento.php?id=<?= (int)$evento['id_evento'] ?>">Editar evento</a>
            <?php endif; ?>
            <h2>Sobre o evento</h2>
            <div class="event-description"><?= escapar($evento['descricao']) ?></div>
            <h2>Avaliações dos participantes</h2>
            <?php vazio($avaliacoes); foreach ($avaliacoes as $av): ?>
                <div class="workflow-card"><?php dado('Nota',$av['nota'].' / 5'); dado('Comentário',$av['comentario']); dado('Data',$av['data_avaliacao']); ?></div>
            <?php endforeach; ?>
        </article>
    <?php elseif (!$erros): ?>
        <h1>Evento não encontrado</h1><p>Confira o endereço ou consulte a lista de eventos.</p>
    <?php endif; ?>
</main>
<?php rodape(); ?>
