<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$organizador = exigirOrganizador();
$eventos = [];
$erros = [];
try {
    $eventos = listarEventos(obterConexao(), $organizador['id']);
} catch (PDOException $erro) {
    $erros[] = falhaBanco($erro);
}
cabecalho('Meus eventos');
?>
<main class="container section">
    <div class="section-heading">
        <div><p class="eyebrow">Área do organizador</p><h1>Meus eventos</h1></div>
        <a class="button" href="novo_evento.php">Cadastrar evento</a>
    </div>
    <?php mostrarErros($erros); ?>
    <?php if (!$erros) { cardsEventos($eventos); } ?>
</main>
<?php rodape(); ?>
