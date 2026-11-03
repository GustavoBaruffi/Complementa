# Participantes — verificação visual

Referência: imagem fornecida na conversa, usada somente para a estrutura visual.
Nenhum nome ou fotografia da referência foi transcrito ou salvo no projeto.
As capturas de validação usam dados fictícios de um banco temporário isolado.

## Escopo e evidências

- Implementação: `meus_eventos.php`, tabela compartilhada em `painel_ui.php` e estilos em `style.css`.
- Desktop: 1613 × 833, escala 1, `.test-runtime/participants-desktop.png`.
- Celular: 390 × 844, escala 1, `.test-runtime/participants-mobile.png`.
- Pesquisa: `.test-runtime/participants-filtered.png`.
- Agrupamento: `.test-runtime/participants-groups.png`.
- Eventos recolhidos: `.test-runtime/participants-collapsed.png` e
  `.test-runtime/participants-mobile-collapsed.png`.
- Capturas locais ignoradas pelo Git. A referência não foi reproduzida nos artefatos.

## Comparação e ajustes

- Tipografia: nomes em peso normal, sem destaque decorativo; tabela compacta e legível.
  A validação usou o fallback Arial, com fontes externas bloqueadas no navegador de teste.
- Espaçamento: a primeira captura revelou espaço excessivo acima da lista.
  Reduzidos o espaçamento da seção, o título e a altura das linhas; as capturas finais
  foram abertas e conferidas novamente após o ajuste.
- Cores: linhas alternadas brancas e cinza-claro, bordas discretas, links sóbrios.
- Imagens: nenhuma foto da referência utilizada. Sem avatares inventados;
  o cadastro atual de alunos não fornece fotografias.
- Conteúdo: nomes vêm do banco. Colunas Evento, Instituição e Situação adaptam
  a referência ao Complementa. Papel é Aluno porque a lista contém inscrições de alunos.
- Navegação: preservados o cabeçalho existente e o cadastro de eventos.
  A lista é exibida exclusivamente por evento, sem abas ou lista geral de pessoas.
  Os eventos começam recolhidos; o controle nativo `details/summary` abre e fecha
  as pessoas abaixo do evento. Filtros e a participação selecionada abrem a lista correspondente.
- Responsividade: filtros empilhados no celular; a tabela tem rolagem horizontal
  própria, sem ampliar a largura da página ou esconder os filtros.

## Validação funcional

- Onze testes de integração aprovados, incluindo permissões, filtros, pesquisa,
  presença, certificados, duplicidade, reversão de transação e reinscrição.
- Navegador local Edge: login, pesquisa, abertura de detalhes, agrupamento e
  expansão/recolhimento dos eventos verificados.
- Sem exceções JavaScript na execução e sem transbordamento horizontal da página no celular.
- PHP sem erros de sintaxe e diferenças sem problemas de whitespace.

## Inscrições canceladas e reinscrição

- Conferidas as capturas `.test-runtime/registration-cancelled-desktop.png`
  (1440 × 1000) e `.test-runtime/registration-cancelled-mobile.png` (390 × 844).
- Cartões compactos com título menor, data/situação/presença na mesma faixa e
  ações com largura ajustada ao texto. Removida a explicação repetitiva de cancelamento.
- Cartão cancelado com fundo cinza-claro, borda lateral fina e aviso textual em vermelho;
  o estado não depende apenas da cor. Nomes e conteúdo continuam legíveis.
- Conferidos o botão de reinscrição e a volta ao estado confirmado no navegador,
  além da página de detalhes em `.test-runtime/registration-cancelled-event.png`.
- Testes cobrem a reativação do mesmo registro, a primeira inscrição, o bloqueio
  de duplicidade, eventos não publicados, horário de início, acesso de outro aluno
  e registros com presença ou certificado. Banco real não utilizado na validação.

## Estilo minimalista global

- Aplicados títulos menores, cartões sem sombras, bordas discretas, botões compactos
  e navegação dos painéis com destaque apenas na opção ativa.
- Detalhes do evento com informações mais próximas e confirmação de inscrição
  discreta. Mantidos os avisos textuais e o vermelho das inscrições canceladas.
- Formulários preservam rótulos visíveis, foco de teclado e campos confortáveis no celular.
- Verificação no Edge em 1440 × 1000 e 390 × 844: início, catálogo, login, cadastro,
  formulário de evento, detalhes do evento, inscrições, certificados, horas, perfil
  e painel da instituição. Participantes também verificados com eventos recolhidos e abertos.
- Evidências locais: `.test-runtime/minimal-*-desktop.png` e
  `.test-runtime/minimal-*-mobile.png`. Capturas de detalhes, início, cadastro,
  formulário e inscrições abertas para revisão visual; fontes externas bloqueadas,
  com renderização em Arial. Ajustada a quebra do link de acompanhamento no celular.
- Nenhum transbordamento horizontal da página ou exceção JavaScript na execução.
  Onze testes de integração aprovados; validação em banco temporário isolado.

final result: passed
