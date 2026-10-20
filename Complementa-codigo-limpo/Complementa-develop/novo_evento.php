<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/eventos_dados.php';
$organizador = exigirOrganizador();
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
} catch (PDOException $erro) {
    $erros[] = falhaBanco($erro);
}
cabecalho('Cadastrar evento');
?>
<main class="container section">
    <div class="form-card form-wide">
        <p class="eyebrow">Área do organizador</p><h1>Cadastrar evento</h1>
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
                <p>O evento será publicado. As horas ficarão aguardando validação da instituição.</p>
                <button class="button" type="submit">Cadastrar evento</button>
            </form>
        <?php endif; ?>
        <p class="form-bottom"><a class="text-link" href="meus_eventos.php">Voltar aos meus eventos</a></p>
    </div>
</main>
<?php rodape(); ?>
