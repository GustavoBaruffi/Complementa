# Sprint 2 — execução e testes

## Escopo implementado

- `cadastro.php`: cadastro público de alunos, confirmação de senha, validação
  no servidor e tratamento de e-mail duplicado.
- `login.php`: seleção explícita de perfil (aluno/organizador), consulta preparada,
  verificação do hash e renovação do identificador da sessão.
- `logout.php`: POST com CSRF, destruição da sessão e expiração do cookie.
- `eventos.php` e `evento.php?id=...`: consultas reais, categoria, organizador,
  data, local, descrição, horas e status; resposta 404 para evento inexistente.
- `novo_evento.php`: exclusivo para organizadores. Usa o organizador da sessão,
  valida a categoria existente e cadastra o evento como `Publicado`, com
  `validade_horas` em `Necessita validação`. Os horários de início e término
  são separados e obedecem à restrição de término após o início no mesmo dia.
- `meus_eventos.php`: somente os eventos do organizador autenticado.
- `index.php`: próximos três eventos disponíveis, ordenados por data. Os cards
  e os números fictícios do protótipo foram substituídos; identidade visual,
  apresentação e seção explicativa foram preservadas.

Organizadores são cadastrados pela equipe, sem cadastro público, conforme
decisão do responsável pelo projeto. Edição/exclusão de eventos, inscrições,
certificados, recuperação de senha e validação de horas não integram esta entrega.
Nenhuma tabela, coluna ou dado existente foi alterado pela implementação.
O SQL dos colegas, inclusive `VARCHAR` nos status e as chaves estrangeiras,
foi preservado.

## Credenciais locais

`conexao.php` lê `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD`
do ambiente do processo PHP. Não há carregamento automático de `.env`.
Não coloque senhas em arquivos PHP, no README, em comandos versionados ou no chat.

No PowerShell, na raiz do projeto:

```powershell
# Apenas verifica conexão e conta registros, sem modificar dados:
.\tools\servir_local.ps1 -DbHost 127.0.0.1 -DbPort 3306 -DbName complementa -DbUser root -Verificar

# Inicia a aplicação; pede a senha novamente:
.\tools\servir_local.ps1 -DbHost 127.0.0.1 -DbPort 3306 -DbName complementa -DbUser root
```

A senha é solicitada por `Read-Host -AsSecureString` e passada ao PHP por
variável de ambiente do processo. Não é gravada em disco nem incluída nos
argumentos do PHP. As variáveis anteriores são restauradas ao encerrar o comando.
O servidor escuta somente em `127.0.0.1`, na porta 8000 (altere com `-Porta`).
Abra `http://localhost:8000`. Use Ctrl+C para parar.

Se a política local impedir scripts PowerShell, configure as variáveis no
ambiente do seu servidor PHP por seu procedimento local. Não é necessário
alterar políticas globais para usar a aplicação.

Com as variáveis já configuradas no terminal/servidor:

```powershell
php -d extension=pdo_mysql tools/verificar_ambiente.php
php -d extension=pdo_mysql -S localhost:8000
```

Se `pdo_mysql` já estiver habilitado no `php.ini`, omita `-d extension=pdo_mysql`.
O iniciador PowerShell detecta isso automaticamente. A extensão deve estar
instalada e o diretório de extensões corretamente configurado.

**Não execute `banco.sql` novamente em um banco existente.** O script original
de criação serve somente para uma instalação nova. A implementação não executa
migrações, recria tabelas ou insere dados automaticamente.

## Criar organizadores pela equipe

Após validar a conexão:

```powershell
.\tools\servir_local.ps1 -DbUser root -CriarOrganizador
```

Ou, com as variáveis já configuradas:

```powershell
php -d extension=pdo_mysql tools/criar_organizador.php
```

O comando solicita nome, e-mail, senha da conta, tipo, cidade e telefone
(opcional), conforme o schema real. A senha da **conta de organizador**
é digitada visivelmente nesse comando interativo; o banco recebe apenas o hash.
Execute-o em um terminal privado. Esse comando cria um registro novo e não
edita contas existentes. Pela web, as ferramentas PHP de terminal retornam 404.

## Testes automatizados sem banco

Requisitos: PHP no PATH e Python 3.

```powershell
python tests/sem_banco.py
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

O teste HTTP inicia seu próprio servidor PHP, usa sessões temporárias em
`.test-runtime/` (ignorado pelo Git) e força uma conexão de banco inacessível.
Não se conecta ao MySQL existente e não cria nem apaga dados de negócio.
As sessões de aluno/organizador são fixtures locais, não logins reais.

Cobertura: formulários públicos, cookies, validação de entrada, escape HTML,
CSRF, rejeição de perfil inválido, bloqueio de visitantes/alunos na área de
organização, logout, 404/405, proteção das ferramentas CLI, falhas de banco
sem exposição de detalhes e execução de `password_hash`/`password_verify`.

## Roteiro de testes reais após configurar o MySQL

Use contas de teste novas e identificáveis. Os testes abaixo inserem registros;
não exclua dados existentes para executá-los.

1. Execute `-Verificar`: confirme o driver e a leitura das quatro tabelas.
2. Cadastre um aluno; confirme no MySQL os dados e um hash no campo
   `senha_hash`, nunca a senha original. Tente repetir o e-mail: deve ser recusado.
3. Tente login com senha incorreta; depois entre com a correta como aluno.
   Confirme o nome no menu. Acesse `novo_evento.php`: deve retornar 403.
4. Saia pelo botão; tente acessar `meus_eventos.php`: deve redirecionar ao login.
5. Crie um organizador pelo comando da equipe; entre selecionando Organizador.
6. Cadastre um evento escolhendo uma categoria existente. Confirme os detalhes
   e a listagem. O `id_organizador` salvo deve ser o da conta autenticada.
7. Tente categoria inexistente, data inválida e horas zero/negativas: não devem
   gerar registro. Recarregar a página após sucesso não deve duplicar o evento.
8. Entre com outro organizador: sua lista própria não deve conter os eventos
   do primeiro. A lista pública deve continuar exibindo os eventos.
9. Confira em tela larga e celular: menu, formulários, mensagens e cards.

## Verificação realizada nesta entrega

Atualização final em 20/10/2026: os fluxos de alunos, organizadores e eventos
passaram em testes reais com MySQL. Foram aprovados também os 17 testes sem
banco e os testes de navegador Edge em desktop e celular. Consulte
[o relatório final](./resultado-sprint-2.md).

```powershell
python tests/cadastro_mysql.py
python tests/eventos_mysql.py
```

Esses dois testes precisam do servidor PHP conectado ao banco na porta 8000.
Eles criam registros fictícios identificados e os mantêm. O segundo também
requer Node.js com WebSocket nativo (testado em Node 24) e Microsoft Edge.
Se o Edge estiver em outro caminho, configure `EDGE_PATH`.
As credenciais fictícias são geradas em memória e não são impressas.
Capturas e perfis isolados do navegador ficam em `.test-runtime/`, ignorado
pelo Git. O teste encerra somente o navegador que ele próprio iniciou.

Os itens abaixo registram a verificação inicial, antes da configuração local:

- Sintaxe PHP validada.
- 16 testes automatizados sem banco passaram.
- O MySQL local estava ativo, mas recusou as credenciais padrão (1045).
- O driver `pdo_mysql` estava instalado, porém desabilitado no PHP padrão.
- Persistência, login real e consultas com dados reais permanecem pendentes
  até a configuração das credenciais pelo responsável.
- Não foi feita validação visual em navegador nesta entrega.
