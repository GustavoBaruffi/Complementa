# Diagnóstico do cadastro — 20/10/2026

Este documento registra a investigação inicial. As pendências de organizador
e eventos mencionadas abaixo foram resolvidas posteriormente, conforme
[o relatório final da Sprint 2](./resultado-sprint-2.md).

## Causa confirmada no servidor em execução

A conexão do servidor usa `complementa_app` e o driver PDO MySQL está ativo.
A conta tem SELECT, INSERT, UPDATE e DELETE no banco do projeto.

A preparação da mesma consulta de `cadastro.php`, sem executar INSERT,
retornou:

```text
SQLSTATE 42S22 / MySQL 1054
Unknown column 'senha_hash' in 'field list'
```

O MySQL em uso tem um schema diferente do arquivo `banco.sql` do repositório.
A tabela `aluno` real possui:

| Campo | Tipo | Obrigatório |
| --- | --- | --- |
| id_aluno | INT UNSIGNED AUTO_INCREMENT | gerado |
| matricula | VARCHAR(20) | sim |
| id_instituicao | INT UNSIGNED, FK para instituicao | sim |
| nome | VARCHAR(100) | sim |
| email | VARCHAR(150) | sim |
| data_nascimento | DATE | sim |
| curso | VARCHAR(100) | sim |
| cidade | VARCHAR(100) | sim |
| meta_horas | SMALLINT UNSIGNED, máximo 999 | sim |

Não existe campo de senha nem outra tabela de autenticação.
Há unicidade de `(matricula, id_instituicao)`, mas não de e-mail.
Na inspeção, havia zero alunos, zero instituições e zero e-mails duplicados.
Essas contagens podem mudar e devem ser reconferidas antes de uma migração.

O verificador antigo apenas contava registros; por isso conexão/leitura OK
não garantia compatibilidade das colunas usadas pelo cadastro.

## Logs

O servidor está com `log_errors=1` e `error_log` vazio: os erros são enviados
ao terminal do servidor PHP, sem arquivo de log configurado.
O histórico desse terminal não estava acessível nesta sessão.
O erro foi reproduzido diretamente no mesmo processo PHP, com a mesma conexão,
sem imprimir credenciais, hashes ou dados de alunos.

Uma requisição HTTP ao cadastro real, com CSRF válido e dados de teste,
também retornou 503. A captura temporária do log dessa requisição confirmou
`SQLSTATE=42S22; MySQL=1054; causa=coluna_inexistente`. A consulta falhou na
preparação e não inseriu o aluno. O arquivo temporário de log foi removido.

`app.php` agora registra SQLSTATE, código MySQL e uma classificação segura.
O erro completo do PDO não é registrado, pois pode conter valores pessoais.
A resposta pública continua genérica.

## Correção autorizada e estado atual

O responsável autorizou a alteração de estrutura proposta, a adaptação ao
schema real e a criação de uma instituição fictícia para teste.

- `cadastro.php` foi adaptado aos campos reais, aos limites de 100 caracteres
  para nome/curso/cidade, matrícula de 20 caracteres, nascimento válido,
  meta inteira de 0 a 999 e instituição existente.
- A lista de instituições vem do banco. Sem instituições disponíveis, o
  formulário explica a pendência e desabilita o botão de cadastro.
- `login.php` recusa hashes nulos sem erro de tipo.
- `tools/verificar_ambiente.php` agora considera os campos reais do aluno.
- A instituição fictícia `Instituicao Ficticia - Teste Cadastro Complementa`
  foi criada com ID 1 e e-mail `teste-cadastro@example.invalid`.
- A execução administrativa do SQL foi concluída pelo responsável em
  20/10/2026. O teste real confirmou `senha_hash` e unicidade de e-mail.
- O cadastro HTTP inseriu o aluno fictício de teste com ID 1, vinculado à
  instituição fictícia ID 1. Os registros foram mantidos; nada foi apagado.

Para repetir o teste (cada execução bem-sucedida cria um novo aluno de teste):

```powershell
python tests/cadastro_mysql.py
```

O teste usa o servidor local na porta 8000 e sua conexão existente, sem
exportar credenciais. Confere cadastro HTTP, dados persistidos, hash de senha,
validações, duplicidade, login, renovação de CSRF e logout. Os registros de
teste permanecem no banco, claramente identificados. O endpoint temporário
de inspeção só aceita loopback com token aleatório e é removido ao terminar.

Os 17 testes sem banco passaram após as adaptações.

## Resultado dos testes reais após a migração

`python tests/cadastro_mysql.py` terminou com código 0:

- validação de meta acima de 999, data impossível, nome acima de 100 caracteres
  e ID de instituição inválido;
- cadastro HTTP com redirecionamento ao login;
- leitura do aluno persistido, campos e vínculo à instituição corretos;
- hash diferente da senha original e validado por `password_verify`;
- e-mail duplicado e matrícula duplicada na mesma instituição rejeitados;
- senha incorreta rejeitada; senha correta autenticou o aluno;
- sessão reconhecida, token CSRF renovado após login e logout concluído.

Também foram executados os 17 testes sem banco, lint de todos os arquivos PHP
e `git diff --check`, sem falhas.

As páginas inicial, eventos e login retornaram HTTP 200 sem erros PHP expostos.
Um ID de evento inválido retornou 404. Isso não comprova listagem/detalhes com
eventos reais: o schema de eventos ainda difere das consultas da implementação.

O login de organizador retornou HTTP 503 no teste real: `organizador` ainda não
possui `senha_hash`. A autorização de migração contemplou apenas `aluno`.
Cadastro de organizadores, login de organizadores e gerenciamento de eventos
permanecem pendentes de compatibilização. Validação visual no navegador também
não foi realizada.

## Escopo da proposta aprovada

1. Preservar o modelo real e adaptar o formulário/INSERT aos campos acima,
   com seleção de instituição existente e validação das restrições reais.
2. Adicionar `aluno.senha_hash` e unicidade de e-mail, conforme
   `proposta-correcao-cadastro.sql`. O SQL é uma proposta, não foi executado.
3. Ajustar o login para recusar contas com hash nulo.
4. Cadastrar uma instituição autorizada antes de testar um aluno, pois a FK
   é obrigatória e a tabela está vazia. Pode ser uma instituição de teste
   claramente identificada, se autorizada pelo responsável.
5. Testar cadastro HTTP real, persistência do hash, duplicidade e login,
   mantendo os registros de teste identificados, sem apagar registros.

A conta `complementa_app` não tem ALTER: a migração aprovada precisará ser
executada por uma conta administrativa, sem conceder ALTER à aplicação.

O schema de organizador/evento também difere do repositório. Essas diferenças
não são corrigidas automaticamente nesta investigação do cadastro de aluno.

Nenhuma alteração de estrutura ou gravação de dados foi realizada durante
o diagnóstico inicial; os endpoints temporários de inspeção foram removidos.

O verificador CLI passou a conferir colunas esperadas e campos obrigatórios
não enviados, além de conexão e leitura. Ele ainda deve apontar incompatibilidades
de organizador/evento, mesmo com o cadastro e login de aluno corrigidos.
