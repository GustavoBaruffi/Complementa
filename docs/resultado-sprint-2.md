# Resultado final da Sprint 2 — 20/10/2026

## Situação

Os três conjuntos funcionais da Semana 2 estão implementados e testados:

| Conjunto | Evidência | Resultado |
| --- | --- | --- |
| Usuários e autenticação | Cadastro de aluno, hash persistido, duplicidades, login/logout; criação de organizadores pela equipe e login de organizador | Aprovado no MySQL real |
| Cadastro de eventos | Formulário HTTP e navegador, validação de categoria/campos/horários, organizador da sessão, persistência e regras de publicação | Aprovado |
| Consulta de eventos | Lista pública, detalhes, lista própria, proteção de rascunhos, campos e horários do banco | Aprovado |

## Alterações

- Migração mínima de `organizador`: `senha_hash` nullable e índice único de
  e-mail. Executada pelo responsável no Workbench, com autorização; nenhuma
  senha foi inventada para contas existentes.
- `organizadores.php` centraliza o cadastro utilizado por
  `tools/criar_organizador.php`: nome, tipo, e-mail, telefone opcional, cidade
  e hash. O cadastro continua exclusivo da equipe, sem endpoint público.
- O login existente reconhece ambos os perfis e recusa hash nulo.
- `novo_evento.php` e `eventos_dados.php` usam as colunas reais: `titulo`,
  `data_evento`, `horario_inicio`, `horario_termino`, `carga_horaria`,
  `modalidade`, `cidade`, `local_evento`, `link` e os relacionamentos.
- Eventos novos são `Publicado` e `Necessita validação`, conforme decisão
  do responsável. Valores enviados pelo cliente não alteram essas regras
  nem o organizador autenticado.
- `app.php` e `evento.php` consultam/exibem os campos reais. A lista pública
  oculta rascunhos; a lista do organizador inclui seus próprios rascunhos.
  Detalhes de rascunho retornam 404 para visitantes e outros organizadores.
- Destaques da página inicial usam `Publicado` e data/horário de início.
- `tools/verificar_ambiente.php` foi atualizado para o schema real.
- Layout, estilos e navegação existentes foram preservados.

## Testes executados

### MySQL real e HTTP

`python tests/eventos_mysql.py` terminou com código 0:

- criação de dois organizadores fictícios pelo mesmo serviço utilizado pelo
  comando da equipe, validação do hash e rejeição de e-mail duplicado;
- login de organizador com senha correta, rejeição de senha incorreta e logout;
- visitante redirecionado ao tentar cadastrar evento;
- CSRF inválido rejeitado;
- categoria inválida, data impossível, horário de término anterior ao início,
  carga abaixo de 0,5, precisão excessiva, modalidade inválida, local obrigatório,
  título acima de 150 caracteres e URL insegura rejeitados;
- evento presencial salvo e conferido coluna a coluna no MySQL;
- tentativa de forjar organizador, status e validade das horas ignorada;
- título, categoria, data, horários, carga, cidade, local e link persistidos;
- lista pública, lista própria e detalhes exibindo os dados;
- HTML fornecido na descrição escapado;
- rascunho acessível ao proprietário e oculto para os demais.

O evento HTTP da execução integral aprovada recebeu ID 3; o rascunho de
teste recebeu ID 4. Os registros anteriores da primeira tentativa também
foram mantidos: naquela tentativa o fluxo HTTP passou, mas o ambiente
restrito impediu a inicialização do navegador.

`python tests/cadastro_mysql.py` também terminou com código 0 na regressão:
cadastro, persistência, hash, duplicidades, validação de dados, login, CSRF
renovado e logout de aluno. O aluno desta regressão recebeu ID 4.

### Navegador

Microsoft Edge automatizado via protocolo DevTools, com perfil isolado:

- preenchimento e envio do login real de organizador;
- preenchimento do formulário e criação de evento online;
- evento online ID 5 conferido depois no MySQL;
- detalhes com título, carga de 1,5 horas e validade correta;
- telas em 1366 × 900 e 390 × 844, sem rolagem horizontal;
- abertura/fechamento do menu móvel, navegação à lista e área do organizador;
- logout e retorno ao menu de visitante;
- nenhuma exceção JavaScript capturada.

As capturas desktop/mobile foram inspecionadas. Evidências locais:

- `.test-runtime/browser-cf4daffab748d7cd/desktop.png`
- `.test-runtime/browser-cf4daffab748d7cd/mobile.png`

O Maestri CLI não estava disponível; foi usado o Edge instalado. A execução
do navegador exigiu execução fora da restrição do ambiente, aprovada pela
ferramenta. Nenhuma credencial foi exibida.

### Regressão e verificações

- 17 testes automatizados sem banco aprovados.
- Sintaxe de todos os arquivos PHP aprovada.
- `git diff --check` sem erros de conteúdo.
- `banco.sql` sem alterações.

## Pendências e limites

Nenhum bloqueio encontrado para os fluxos solicitados da Sprint 2.
Os testes de navegador foram feitos no Edge, não em todos os navegadores.
Contas anteriores com hash nulo não autenticam; recuperação/definição de senha
para contas antigas não integra esta sprint.

Inscrições, certificados, aprovação institucional de horas e edição/exclusão
de eventos não foram acrescentados ao escopo desta entrega.

O `banco.sql` original permanece diferente do schema real, por determinação
do responsável. Não deve ser usado para recriar o banco testado.
As migrações já aplicadas estão documentadas separadamente.

Os registros fictícios de alunos, instituição, organizadores, categoria e
eventos foram mantidos e identificados como teste. Não houve exclusão de dados,
recriação de tabelas, alteração de estrutura por parte do agente, commit ou push.
