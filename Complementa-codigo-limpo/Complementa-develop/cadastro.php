<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

if (usuario()) {
    redirecionar('eventos.php');
}
$erros = [];
$nome = campo('nome');
$email = campo('email');
$curso = campo('curso');
$matricula = campo('matricula');
$instituicao = campo('id_instituicao');
$nascimento = campo('data_nascimento');
$cidade = campo('cidade');
$meta = campo('meta_horas');
$instituicoes = [];
$erroInstituicoes = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
    $confirmacao = is_string($_POST['confirmacao'] ?? null) ? $_POST['confirmacao'] : '';
    if ($nome === '' || tamanho($nome) > 100) {
        $erros[] = 'Informe seu nome com até 100 caracteres.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $erros[] = 'Informe um e-mail válido com até 150 caracteres.';
    }
    if ($curso === '' || tamanho($curso) > 100) {
        $erros[] = 'Informe seu curso com até 100 caracteres.';
    }
    if ($matricula === '' || tamanho($matricula) > 20) {
        $erros[] = 'Informe sua matrícula com até 20 caracteres.';
    }
    $idInstituicao = filter_var($instituicao, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$idInstituicao) {
        $erros[] = 'Selecione uma instituição válida.';
    }
    $data = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $nascimento)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $nascimento) : false;
    if (!$data || $data->format('Y-m-d') !== $nascimento
        || (int) $data->format('Y') < 1000 || $data > new DateTimeImmutable('today')) {
        $erros[] = 'Informe uma data de nascimento válida, que não esteja no futuro.';
    }
    if ($cidade === '' || tamanho($cidade) > 100) {
        $erros[] = 'Informe sua cidade com até 100 caracteres.';
    }
    if (filter_var($meta, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]) === false) {
        $erros[] = 'Informe uma meta de horas inteira entre 0 e 999.';
    }
    if (strlen($senha) < 8 || strlen($senha) > 72 || str_contains($senha, "\0")) {
        $erros[] = 'A senha deve ter entre 8 e 72 bytes (letras acentuadas podem ocupar mais de um byte).';
    }
    if ($senha !== $confirmacao) {
        $erros[] = 'A confirmação da senha não confere.';
    }
    if (!$erros) {
        try {
            $pdo = obterConexao();
            $consulta = $pdo->prepare('SELECT id_instituicao FROM instituicao WHERE id_instituicao = :id');
            $consulta->execute(['id' => $idInstituicao]);
            if (!$consulta->fetch()) {
                $erros[] = 'Selecione uma instituição existente.';
            } else {
                $consulta = $pdo->prepare('INSERT INTO aluno
                    (nome, email, senha_hash, curso, matricula, id_instituicao, data_nascimento, cidade, meta_horas)
                    VALUES (:nome, :email, :senha, :curso, :matricula, :instituicao, :nascimento, :cidade, :meta)');
                $consulta->execute([
                    'nome' => $nome, 'email' => $email,
                    'senha' => password_hash($senha, PASSWORD_DEFAULT), 'curso' => $curso,
                    'matricula' => $matricula, 'instituicao' => $idInstituicao,
                    'nascimento' => $nascimento, 'cidade' => $cidade, 'meta' => (int) $meta,
                ]);
                $_SESSION['aviso'] = 'Cadastro realizado! Entre com seu e-mail e senha.';
                redirecionar('login.php');
            }
        } catch (PDOException $erro) {
            $erros[] = ($erro->errorInfo[1] ?? null) === 1062
                ? 'O e-mail ou a matrícula nesta instituição já possui cadastro.'
                : falhaBanco($erro);
        }
    }
}
try {
    $consulta = obterConexao()->prepare('SELECT id_instituicao, nome FROM instituicao ORDER BY nome');
    $consulta->execute();
    $instituicoes = $consulta->fetchAll();
} catch (PDOException $erro) {
    $erroInstituicoes = falhaBanco($erro);
}
cabecalho('Cadastro de aluno');
?>
<main class="container section">
    <div class="form-card">
        <p class="eyebrow">Comece sua jornada</p><h1>Criar conta de aluno</h1>
        <?php mostrarErros($erros); ?>
        <?php if ($erroInstituicoes): ?>
            <p class="alert alert-error" role="alert"><?= escapar($erroInstituicoes) ?></p>
        <?php elseif (!$instituicoes): ?>
            <p class="alert" role="status">Ainda não há instituições cadastradas. Solicite o cadastro à equipe antes de criar sua conta.</p>
        <?php endif; ?>
        <form method="post" class="form-stack">
            <?php campoCsrf(); ?>
            <label for="nome">Nome completo</label>
            <input id="nome" name="nome" autocomplete="name" maxlength="100" required value="<?= escapar($nome) ?>">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" autocomplete="email" maxlength="150" required value="<?= escapar($email) ?>">
            <label for="curso">Curso</label>
            <input id="curso" name="curso" maxlength="100" required value="<?= escapar($curso) ?>">
            <label for="matricula">Matrícula</label>
            <input id="matricula" name="matricula" maxlength="20" required value="<?= escapar($matricula) ?>">
            <label for="id_instituicao">Instituição</label>
            <select id="id_instituicao" name="id_instituicao" required>
                <option value="">Selecione sua instituição</option>
                <?php foreach ($instituicoes as $item): ?>
                    <option value="<?= (int) $item['id_instituicao'] ?>" <?= (string) $item['id_instituicao'] === $instituicao ? 'selected' : '' ?>><?= escapar($item['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="data_nascimento">Data de nascimento</label>
            <input id="data_nascimento" name="data_nascimento" type="date" max="<?= date('Y-m-d') ?>" required value="<?= escapar($nascimento) ?>">
            <label for="cidade">Cidade</label>
            <input id="cidade" name="cidade" autocomplete="address-level2" maxlength="100" required value="<?= escapar($cidade) ?>">
            <label for="meta_horas">Meta de horas complementares</label>
            <input id="meta_horas" name="meta_horas" type="number" min="0" max="999" step="1" required value="<?= escapar($meta) ?>">
            <label for="senha">Senha</label>
            <input id="senha" name="senha" type="password" autocomplete="new-password" minlength="8" maxlength="72" required aria-describedby="senha-ajuda">
            <small id="senha-ajuda">Use pelo menos 8 caracteres. Limite de 72 bytes.</small>
            <label for="confirmacao">Confirme a senha</label>
            <input id="confirmacao" name="confirmacao" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
            <button class="button" type="submit" <?= !$instituicoes ? 'disabled' : '' ?>>Criar conta</button>
        </form>
        <p class="form-bottom">Já tem uma conta? <a class="text-link" href="login.php">Entrar</a></p>
    </div>
</main>
<?php rodape(); ?>
