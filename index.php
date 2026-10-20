<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$eventos = [];
$erros = [];
try {
    $eventos = listarEventos(obterConexao(), null, true);
} catch (PDOException $erro) {
    $erros[] = falhaBanco($erro);
}
cabecalho('Início');
?>
    <main id="inicio">
        <section class="hero">
            <div class="container hero-content">
                <div class="hero-copy">
                    <p class="eyebrow">Sua jornada acadêmica, mais simples</p>
                    <h1>Conectando experiências, <span>complementando futuros.</span></h1>
                    <p class="hero-description">Encontre atividades, acompanhe suas inscrições e valide suas horas complementares em um só lugar.</p>
                    <div class="hero-actions">
                        <a class="button" href="#eventos">Explorar eventos</a>
                        <a class="button button-secondary" href="#como-funciona">Como funciona</a>
                    </div>
                </div>
                <div class="hero-summary" aria-label="Sua conta">
                    <p class="muted">Bem-vindo ao Complementa</p>
                    <h2><?= escapar(usuario()['nome'] ?? 'Sua próxima experiência começa aqui') ?></h2>
                    <p>Explore atividades acadêmicas e encontre oportunidades para complementar sua formação.</p>
                    <?php if (usuario()): ?>
                        <a class="button" href="<?= usuario()['perfil'] === 'organizador' ? 'meus_eventos.php' : 'eventos.php' ?>"><?= usuario()['perfil'] === 'organizador' ? 'Gerenciar meus eventos' : 'Consultar eventos' ?></a>
                    <?php else: ?>
                        <a class="button" href="cadastro.php">Criar conta de aluno</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="eventos" class="section">
            <div class="container">
                <div class="section-heading">
                    <div><p class="eyebrow">Descubra oportunidades</p><h2>Próximos eventos</h2></div>
                    <a class="text-link" href="eventos.php">Ver todos <span aria-hidden="true">→</span></a>
                </div>
                <?php mostrarErros($erros); ?>
                <?php if (!$erros) { cardsEventos($eventos); } ?>
            </div>
        </section>

        <section id="como-funciona" class="section section-soft">
            <div class="container">
                <div class="section-heading centered"><p class="eyebrow">Do evento à validação</p><h2>Como o Complementa funciona</h2><p>Um fluxo simples para você se concentrar no que realmente importa.</p></div>
                <div class="steps">
                    <div class="step"><span>01</span><h3>Encontre</h3><p>Explore atividades acadêmicas e escolha as que combinam com seus objetivos.</p></div>
                    <div class="step"><span>02</span><h3>Participe</h3><p>Faça sua inscrição e acompanhe todos os detalhes do evento.</p></div>
                    <div class="step"><span>03</span><h3>Valide</h3><p>Envie seu certificado e acompanhe a validação das suas horas.</p></div>
                </div>
            </div>
        </section>

        <section id="acesso" class="cta-section">
            <div class="container cta-content"><div><p class="eyebrow">Comece agora</p><h2>Pronto para complementar seu futuro?</h2></div><a class="button button-light" href="cadastro.php">Criar minha conta</a></div>
        </section>
    </main>

<?php rodape(); ?>