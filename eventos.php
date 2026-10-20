<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$eventos = [];
$erros = [];
try {
    $eventos = listarEventos(obterConexao());
} catch (PDOException $erro) {
    $erros[] = falhaBanco($erro);
}
cabecalho('Eventos');
?>
<main class="container section">
    <div class="section-heading">
        <div><p class="eyebrow">Descubra oportunidades</p><h1>Eventos</h1><p>Consulte os detalhes e a situação de cada atividade.</p></div>
        <?php if ((usuario()['perfil'] ?? '') === 'organizador'): ?>
            <a class="button" href="novo_evento.php">Cadastrar evento</a>
        <?php endif; ?>
    </div>
    <?php mostrarErros($erros); ?>
    <?php if (!$erros) { cardsEventos($eventos); } ?>
</main>
<?php rodape(); ?>
