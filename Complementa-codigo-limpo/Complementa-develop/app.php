<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';

date_default_timezone_set('America/Sao_Paulo');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}
header('Cache-Control: no-store');

function escapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function campo(string $nome): string
{
    return isset($_POST[$nome]) && is_string($_POST[$nome]) ? trim($_POST[$nome]) : '';
}

function tamanho(string $valor): int
{
    return preg_match_all('/./us', $valor, $matches) ?: 0;
}

function redirecionar(string $destino): never
{
    header('Location: ' . $destino, true, 303);
    exit;
}

function tokenCsrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function campoCsrf(): void
{
    echo '<input type="hidden" name="csrf" value="' . escapar(tokenCsrf()) . '">';
}

function verificarCsrf(): void
{
    if (!hash_equals(tokenCsrf(), campo('csrf'))) {
        http_response_code(403);
        cabecalho('Formulário expirado');
        echo '<main class="container section"><h1>Formulário expirado</h1><p>Volte, atualize a página e tente novamente.</p></main>';
        rodape();
        exit;
    }
}

function usuario(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function exigirOrganizador(): array
{
    $usuario = usuario();
    if (!$usuario) {
        $_SESSION['aviso'] = 'Entre como organizador para gerenciar seus eventos.';
        redirecionar('login.php');
    }
    if ($usuario['perfil'] !== 'organizador') {
        http_response_code(403);
        cabecalho('Acesso restrito');
        echo '<main class="container section"><h1>Acesso restrito</h1><p>Somente organizadores podem gerenciar eventos.</p></main>';
        rodape();
        exit;
    }
    return $usuario;
}

function falhaBanco(Throwable $erro): string
{
        $codigoMysql = $erro instanceof PDOException ? (int) ($erro->errorInfo[1] ?? 0) : 0;
    $causas = [
        1045 => 'autenticacao_recusada',
        1049 => 'banco_inexistente',
        1054 => 'coluna_inexistente',
        1062 => 'registro_duplicado',
        1142 => 'permissao_insuficiente',
        1146 => 'tabela_inexistente',
        1364 => 'campo_obrigatorio_ausente',
        1452 => 'relacionamento_inexistente',
        2002 => 'servidor_indisponivel',
    ];
    $sqlstate = preg_replace('/[^A-Z0-9]/i', '', (string) $erro->getCode());
    error_log(sprintf(
        'Complementa: falha no banco; SQLSTATE=%s; MySQL=%d; causa=%s.',
        $sqlstate, $codigoMysql, $causas[$codigoMysql] ?? 'nao_classificada'
    ));
    http_response_code(503);
    return 'Não foi possível acessar o banco de dados. Tente novamente mais tarde.';
}

function mostrarErros(array $erros): void
{
    if (!$erros) {
        return;
    }
    echo '<div class="alert alert-error" role="alert"><ul>';
    foreach ($erros as $erro) {
        echo '<li>' . escapar($erro) . '</li>';
    }
    echo '</ul></div>';
}

function cabecalho(string $titulo): void
{
    $usuario = usuario();
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="Complementa: acompanhe eventos e horas complementares em um só lugar.">
        <title>Complementa | <?= escapar($titulo) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
        <script src="script.js" defer></script>
    </head>
    <body>
    <header class="site-header">
        <div class="container header-content">
            <a class="brand" href="index.php" aria-label="Complementa - página inicial"><span class="brand-mark" aria-hidden="true">✓</span><span>Complementa</span></a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav">
                <span class="sr-only">Abrir menu</span><span></span><span></span><span></span>
            </button>
            <nav id="main-nav" class="main-nav" aria-label="Navegação principal">
                <a href="index.php">Início</a>
                <a href="eventos.php">Eventos</a>
                <?php if ($usuario): ?>
                    <?php if ($usuario['perfil'] === 'organizador'): ?>
                        <a href="meus_eventos.php">Meus eventos</a>
                    <?php endif; ?>
                    <span class="nav-user"><?= escapar($usuario['nome']) ?></span>
                    <form action="logout.php" method="post">
                        <?php campoCsrf(); ?>
                        <button class="button button-small" type="submit">Sair</button>
                    </form>
                <?php else: ?>
                    <a href="cadastro.php">Criar conta</a>
                    <a class="button button-small" href="login.php">Entrar</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <?php if (isset($_SESSION['aviso'])): ?>
        <div class="container"><p class="alert" role="status"><?= escapar($_SESSION['aviso']) ?></p></div>
        <?php unset($_SESSION['aviso']); ?>
    <?php endif;
}

function rodape(): void
{
    ?>
    <footer class="site-footer"><div class="container footer-content"><a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">✓</span><span>Complementa</span></a><p>© 2026 Complementa. Projeto Integrador PUCPR.</p></div></footer>
    </body>
    </html>
    <?php
}

function listarEventos(PDO $pdo, ?int $organizador = null, bool $destaques = false): array
{
    $sql = 'SELECT e.*, c.nome AS categoria FROM evento e JOIN categoria c ON c.id_categoria = e.id_categoria';
    $parametros = [];
    if ($organizador !== null) {
        $sql .= ' WHERE e.id_organizador = :organizador';
        $parametros['organizador'] = $organizador;
    } elseif ($destaques) {
        $sql .= ' WHERE e.status = :status AND TIMESTAMP(e.data_evento, e.horario_inicio) >= :agora';
        $parametros = ['status' => 'Publicado', 'agora' => date('Y-m-d H:i:s')];
    } else {
        $sql .= ' WHERE e.status <> :rascunho';
        $parametros['rascunho'] = 'Rascunho';
    }
    $sql .= ' ORDER BY e.data_evento ASC, e.horario_inicio ASC, e.id_evento ASC';
    if ($destaques) {
        $sql .= ' LIMIT 3';
    }
    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);
    return $consulta->fetchAll();
}

function cardsEventos(array $eventos): void
{
    if (!$eventos) {
        echo '<p class="empty-state">Nenhum evento encontrado.</p>';
        return;
    }
    echo '<div class="event-grid">';
    foreach ($eventos as $evento) {
        ?>
        <article class="event-card">
            <span class="event-category"><?= escapar($evento['categoria']) ?></span>
            <h3><?= escapar($evento['titulo']) ?></h3>
            <p class="event-detail"><?= escapar(date('d/m/Y', strtotime($evento['data_evento']))) ?> · <?= escapar(substr($evento['horario_inicio'], 0, 5)) ?> às <?= escapar(substr($evento['horario_termino'], 0, 5)) ?></p>
            <p class="event-detail"><?= escapar($evento['modalidade']) ?><?= $evento['cidade'] ? ' · ' . escapar($evento['cidade']) : '' ?></p>
            <p class="event-detail"><?= escapar($evento['local_evento']) ?></p>
            <p class="event-detail">Status: <?= escapar($evento['status']) ?></p>
            <div class="event-footer"><strong><?= escapar(number_format((float) $evento['carga_horaria'], 1, ',', '.')) ?> horas</strong><a href="evento.php?id=<?= (int) $evento['id_evento'] ?>">Ver detalhes</a></div>
        </article>
        <?php
    }
    echo '</div>';
}
