<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

if (usuario()) {
    redirecionar(usuario()['perfil'] === 'organizador' ? 'meus_eventos.php' : 'eventos.php');
}
$erros = [];
$email = campo('email');
$perfil = campo('perfil') ?: 'aluno';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
        $perfis = ['aluno' => 'id_aluno', 'organizador' => 'id_organizador', 'equipe' => 'id_usuario', 'instituicao' => 'id_usuario'];
    if (!isset($perfis[$perfil]) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || $senha === '' || strlen($senha) > 72) {
        $erros[] = 'Confira o perfil, o e-mail e a senha.';
    } else {
        try {
            $gestao = in_array($perfil, ['equipe','instituicao'], true);
            $tabela = $gestao ? 'usuario_gestao' : $perfil;
            $consulta = obterConexao()->prepare("SELECT {$perfis[$perfil]} AS id, nome, senha_hash FROM {$tabela} WHERE email = :email" . ($gestao ? ' AND perfil = :perfil' : ''));
            $consulta->execute($gestao ? ['email'=>$email,'perfil'=>$perfil] : ['email'=>$email]);
            $conta = $consulta->fetch();
            if ($conta && is_string($conta['senha_hash']) && password_verify($senha, $conta['senha_hash'])) {
                session_regenerate_id(true);
                $_SESSION = [
                    'usuario' => ['id' => (int) $conta['id'], 'nome' => $conta['nome'], 'perfil' => $perfil],
                    'csrf' => bin2hex(random_bytes(32)),
                    'aviso' => 'Bem-vindo(a), ' . $conta['nome'] . '!',
                ];
                redirecionar($perfil === 'organizador' ? 'meus_eventos.php' : ($gestao ? 'painel.php' : 'eventos.php'));
            }
            $erros[] = 'Perfil, e-mail ou senha incorretos.';
        } catch (PDOException $erro) {
            $erros[] = falhaBanco($erro);
        }
    }
}
cabecalho('Entrar');
?>
<main class="container section">
    <div class="form-card">
        <p class="eyebrow">Bem-vindo ao Complementa</p><h1>Entrar</h1>
        <?php mostrarErros($erros); ?>
        <form method="post" class="form-stack">
            <?php campoCsrf(); ?>
            <label for="perfil">Perfil</label>
            <select id="perfil" name="perfil" required>
                <option value="aluno" <?= $perfil === 'aluno' ? 'selected' : '' ?>>Aluno</option>
                <option value="organizador" <?= $perfil === 'organizador' ? 'selected' : '' ?>>Organizador</option>
                <option value="instituicao" <?= $perfil === 'instituicao' ? 'selected' : '' ?>>Instituição</option>
                <option value="equipe" <?= $perfil === 'equipe' ? 'selected' : '' ?>>Equipe</option>
            </select>
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" autocomplete="username" maxlength="150" required value="<?= escapar($email) ?>">
            <label for="senha">Senha</label>
            <input id="senha" name="senha" type="password" autocomplete="current-password" maxlength="72" required>
            <button class="button" type="submit">Entrar</button>
        </form>
        <p class="form-bottom">É aluno e ainda não tem conta? <a class="text-link" href="cadastro.php">Cadastre-se</a></p>
        <p>As contas de organizadores são cadastradas pela equipe.</p>
    </div>
</main>
<?php rodape(); ?>
