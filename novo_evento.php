<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/eventos_dados.php';
require_once __DIR__ . '/gestao_dados.php';
$organizador = exigirOrganizador();
$editando = isset($_GET['id']);
$idEvento = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if ($editando && !$idEvento) { http_response_code(404); exit('Evento não encontrado.'); }
$situacao = 'Publicado';
$erros = [];
$categorias = [];
$dados = [];
foreach (['titulo', 'descricao', 'data_evento', 'horario_inicio', 'horario_termino', 'local_evento', 'carga_horaria', 'id_categoria', 'modalidade', 'cidade', 'link'] as $campo) {
    $dados[$campo] = campo($campo);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
}
try {
    $pdo = obterConexao();
    if ($editando) {
        $atual = registro($pdo,'SELECT id_categoria,titulo,descricao,data_evento,horario_inicio,horario_termino,local_evento,carga_horaria,modalidade,cidade,link,status FROM evento WHERE id_evento=? AND id_organizador=?',[$idEvento,$organizador['id']]);
        $situacao=$atual['status'];
        if ($_SERVER['REQUEST_METHOD']==='GET') {
            foreach ($dados as $c=>$_) $dados[$c]=(string)($atual[$c] ?? '');
            $dados['horario_inicio']=substr($dados['horario_inicio'],0,5);
            $dados['horario_termino']=substr($dados['horario_termino'],0,5);
        }
        if ($_SERVER['REQUEST_METHOD']==='POST' && campo('acao')==='excluir') {
            if (campo('confirmar')!=='sim') throw new DomainException('Confirme a exclusão do evento.');
            excluirEvento($pdo,$organizador['id'],(int)$idEvento);
            $_SESSION['aviso']='Evento excluído.';
            redirecionar('meus_eventos.php');
        }
    }
    $consulta = $pdo->prepare('SELECT id_categoria, nome FROM categoria ORDER BY nome');
    $consulta->execute();
    $categorias = $consulta->fetchAll();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $erros = validarEvento($dados);
        $categoria = filter_var($dados['id_categoria'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$categoria || !in_array($categoria, array_map('intval', array_column($categorias, 'id_categoria')), true)) {
            $erros[] = 'Selecione uma categoria existente.';
        }
        if (!$erros) {
            if ($editando) {
                $situacao=escolha(campo('status'),['Rascunho','Publicado','Cancelado','Encerrado']);
                transacao($pdo,function () use ($pdo,$dados,$situacao,$categoria,$idEvento,$organizador) {
                    $anterior=registro($pdo,'SELECT status FROM evento WHERE id_evento=? AND id_organizador=? FOR UPDATE',[$idEvento,$organizador['id']]);
                    $inscritos=sql($pdo,'SELECT id_aluno FROM inscricao WHERE id_evento=?',[$idEvento])->fetchAll();
                    if ($situacao==='Rascunho' && $inscritos) throw new DomainException('Um evento com inscrições não pode voltar a rascunho.');
                    if ($situacao==='Encerrado' && $dados['data_evento'].' '.$dados['horario_termino']>date('Y-m-d H:i')) throw new DomainException('O evento só pode ser encerrado após seu término.');
                    if (sql($pdo,'SELECT c.id_certificado FROM certificado c JOIN inscricao i ON i.id_inscricao=c.id_inscricao WHERE i.id_evento=? LIMIT 1',[$idEvento])->fetch()) throw new DomainException('Evento com certificados emitidos não pode ser alterado.');
                    sql($pdo,'UPDATE evento SET id_categoria=?,titulo=?,descricao=?,data_evento=?,horario_inicio=?,horario_termino=?,local_evento=?,carga_horaria=?,modalidade=?,cidade=?,link=?,status=? WHERE id_evento=? AND id_organizador=?',[
                        $categoria,$dados['titulo'],$dados['descricao'],$dados['data_evento'],$dados['horario_inicio'],$dados['horario_termino'],$dados['local_evento']?:null,str_replace(",",".",$dados['carga_horaria']),$dados['modalidade'],$dados['cidade']?:null,$dados['link']?:null,$situacao,$idEvento,$organizador['id']]);
                    foreach ($inscritos as $i) notificar($pdo,(int)$i['id_aluno'],'Evento atualizado','Um evento em que você se inscreveu foi atualizado. Consulte os detalhes.');
                    if ($situacao==='Cancelado') sql($pdo,"UPDATE inscricao SET status='Cancelada',presenca=0 WHERE id_evento=?",[$idEvento]);
                });
                $_SESSION['aviso']='Evento atualizado.';
                redirecionar('evento.php?id='.$idEvento);
            }
                        $consulta = $pdo->prepare('INSERT INTO evento
                (id_organizador,id_categoria,titulo,descricao,data_evento,horario_inicio,horario_termino,
                 local_evento,carga_horaria,modalidade,cidade,link,status,validade_horas)
                VALUES (:organizador,:categoria,:titulo,:descricao,:data,:inicio,:termino,
                        :local,:horas,:modalidade,:cidade,:link,:status,:validade)');
            $consulta->execute([
                'organizador' => $organizador['id'], 'categoria' => $categoria,
                'titulo' => $dados['titulo'], 'descricao' => $dados['descricao'],
                'data' => $dados['data_evento'], 'inicio' => $dados['horario_inicio'],
                'termino' => $dados['horario_termino'], 'local' => $dados['local_evento'] ?: null,
                'horas' => str_replace(',', '.', $dados['carga_horaria']),
                'modalidade' => $dados['modalidade'], 'cidade' => $dados['cidade'] ?: null,
                'link' => $dados['link'] ?: null, 'status' => 'Publicado', 'validade' => 'Necessita validação',
            ]);
            $_SESSION['aviso'] = 'Evento cadastrado com sucesso.';
            redirecionar('evento.php?id=' . $pdo->lastInsertId());
        }
    }
} catch (Throwable $erro) {
    $erros[] = erroFluxo($erro);
}
cabecalho($editando ? 'Editar evento' : 'Cadastrar evento');
?>
<main class="container section">
    <div class="form-card form-wide">
        <p class="eyebrow">Área do organizador</p><h1><?= $editando ? 'Editar evento' : 'Cadastrar evento' ?></h1>
        <?php mostrarErros($erros); ?>
        <?php if (!$categorias): ?>
            <p>O cadastro depende de categorias disponíveis no banco. Entre em contato com a equipe.</p>
        <?php else: ?>
            <form method="post" class="form-stack">
                <?php campoCsrf(); ?>
                <label for="titulo">Título do evento</label>
                <input id="titulo" name="titulo" maxlength="150" required value="<?= escapar($dados['titulo']) ?>">
                <label for="id_categoria">Categoria</label>
                <select id="id_categoria" name="id_categoria" required>
                    <option value="">Selecione</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?= (int) $categoria['id_categoria'] ?>" <?= (string) $categoria['id_categoria'] === $dados['id_categoria'] ? 'selected' : '' ?>><?= escapar($categoria['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="descricao">Descrição</label>
                <textarea id="descricao" name="descricao" rows="7" maxlength="2000" required><?= escapar($dados['descricao']) ?></textarea>
                <label for="data_evento">Data do evento</label>
                <input id="data_evento" name="data_evento" type="date" required value="<?= escapar($dados['data_evento']) ?>">
                <label for="horario_inicio">Horário de início (Brasília)</label>
                <input id="horario_inicio" name="horario_inicio" type="time" required value="<?= escapar($dados['horario_inicio']) ?>">
                <label for="horario_termino">Horário de término (mesmo dia)</label>
                <input id="horario_termino" name="horario_termino" type="time" required value="<?= escapar($dados['horario_termino']) ?>">
                <label for="modalidade">Modalidade</label>
                <select id="modalidade" name="modalidade" required>
                    <option value="">Selecione</option>
                    <?php foreach (['Presencial', 'Online', 'Híbrido'] as $modalidade): ?>
                        <option value="<?= escapar($modalidade) ?>" <?= $dados['modalidade'] === $modalidade ? 'selected' : '' ?>><?= escapar($modalidade) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="cidade">Cidade (obrigatória para presencial)</label>
                <input id="cidade" name="cidade" maxlength="100" value="<?= escapar($dados['cidade']) ?>">
                <label for="local_evento">Local (obrigatório para presencial)</label>
                <input id="local_evento" name="local_evento" maxlength="200" value="<?= escapar($dados['local_evento']) ?>">
                <label for="link">Link do evento (opcional)</label>
                <input id="link" name="link" type="url" maxlength="500" value="<?= escapar($dados['link']) ?>">
                <label for="carga_horaria">Carga horária</label>
                <input id="carga_horaria" name="carga_horaria" type="number" min="0.5" max="999" step="0.1" required value="<?= escapar($dados['carga_horaria']) ?>">
                <?php if ($editando): ?>
                <label for="status">Situação do evento</label>
                <select id="status" name="status" required>
                    <?php foreach (['Rascunho','Publicado','Cancelado','Encerrado'] as $s): ?>
                    <option value="<?= escapar($s) ?>" <?= $situacao===$s?'selected':'' ?>><?= escapar($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?><p>O evento será publicado. As horas ficarão aguardando validação da instituição.</p><?php endif; ?>
                <button class="button" type="submit"><?= $editando ? 'Salvar alterações' : 'Cadastrar evento' ?></button>
            </form>
        <?php endif; ?>
        <?php if ($editando && isset($atual)): ?>
        <form method="post" class="form-stack">
            <?php campoCsrf(); ?><input type="hidden" name="acao" value="excluir">
            <label class="check-label"><input type="checkbox" name="confirmar" value="sim" required> Confirmo a exclusão deste evento</label>
            <p>Eventos com registros vinculados não podem ser excluídos.</p>
            <button class="button" type="submit">Excluir evento</button>
        </form>
        <?php endif; ?>
        <p class="form-bottom"><a class="text-link" href="meus_eventos.php">Voltar aos meus eventos</a></p>
    </div>
</main>
<?php rodape(); ?>
