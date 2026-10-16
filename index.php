<?php
declare(strict_types=1);

$navigation = [
    ['label' => 'Início', 'href' => '#inicio'],
    ['label' => 'Eventos', 'href' => '#eventos'],
    ['label' => 'Inscrições', 'href' => '#inscricoes'],
    ['label' => 'Certificados', 'href' => '#certificados'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Complementa: acompanhe eventos e horas complementares em um só lugar.">
    <title>Complementa | Início</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="script.js" defer></script>
</head>
<body>
    <header class="site-header">
        <div class="container header-content">
            <a class="brand" href="#inicio" aria-label="Complementa - página inicial">
                <span class="brand-mark" aria-hidden="true">✓</span>
                <span>Complementa</span>
            </a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav">
                <span class="sr-only">Abrir menu</span>
                <span></span><span></span><span></span>
            </button>
            <nav id="main-nav" class="main-nav" aria-label="Navegação principal">
                <?php foreach ($navigation as $item): ?>
                    <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
                <a class="button button-small" href="#acesso">Entrar</a>
            </nav>
        </div>
    </header>

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
                <div class="hero-summary" aria-label="Resumo do estudante">
                    <div class="summary-heading">
                        <div>
                            <p class="muted">Olá, estudante</p>
                            <h2>Seu progresso</h2>
                        </div>
                        <span class="avatar">AS</span>
                    </div>
                    <div class="progress-value"><strong>32</strong><span>/ 60 horas</span></div>
                    <div class="progress-bar" role="progressbar" aria-label="Horas complementares concluídas" aria-valuenow="53" aria-valuemin="0" aria-valuemax="100"><span></span></div>
                    <p class="muted">Você já completou 53% da sua meta.</p>
                    <div class="summary-stats">
                        <div><strong>4</strong><span>inscrições</span></div>
                        <div><strong>2</strong><span>certificados</span></div>
                        <div><strong>3</strong><span>eventos próximos</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="eventos" class="section">
            <div class="container">
                <div class="section-heading">
                    <div><p class="eyebrow">Descubra oportunidades</p><h2>Próximos eventos</h2></div>
                    <a class="text-link" href="#eventos">Ver todos <span aria-hidden="true">→</span></a>
                </div>
                <div class="event-grid">
                    <article class="event-card">
                        <span class="event-category">Tecnologia</span>
                        <h3>Workshop de inovação e criatividade</h3>
                        <p class="event-detail">18 out · 19h às 21h</p>
                        <p class="event-detail">Auditório Principal · PUCPR</p>
                        <div class="event-footer"><strong>2 horas</strong><a href="#acesso">Inscrever-se</a></div>
                    </article>
                    <article class="event-card">
                        <span class="event-category category-blue">Carreira</span>
                        <h3>Conexões para o futuro profissional</h3>
                        <p class="event-detail">22 out · 18h30 às 20h</p>
                        <p class="event-detail">Online · transmissão ao vivo</p>
                        <div class="event-footer"><strong>1,5 horas</strong><a href="#acesso">Inscrever-se</a></div>
                    </article>
                    <article class="event-card">
                        <span class="event-category category-orange">Comunidade</span>
                        <h3>Voluntariado e impacto social</h3>
                        <p class="event-detail">26 out · 9h às 13h</p>
                        <p class="event-detail">Centro de convivência</p>
                        <div class="event-footer"><strong>4 horas</strong><a href="#acesso">Inscrever-se</a></div>
                    </article>
                </div>
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
            <div class="container cta-content"><div><p class="eyebrow">Comece agora</p><h2>Pronto para complementar seu futuro?</h2></div><a class="button button-light" href="mailto:contato@complementa.local">Criar minha conta</a></div>
        </section>
    </main>

    <footer class="site-footer"><div class="container footer-content"><a class="brand" href="#inicio"><span class="brand-mark" aria-hidden="true">✓</span><span>Complementa</span></a><p>© 2026 Complementa. Projeto Integrador PUCPR.</p></div></footer>
</body>
</html>