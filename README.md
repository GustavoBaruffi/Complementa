# Complementa

## Como abrir o projeto com o banco de dados — passo a passo

Escolha o tutorial do seu computador e siga apenas aquela seção:

- [Como iniciar no Windows](#como-iniciar-no-windows) — com XAMPP.
- [Como iniciar no Mac](#como-iniciar-no-mac) — com MAMP gratuito.
- [Contas de acesso para teste](#contas-de-acesso-para-teste) — aluno, organizador e instituição.

Esses programas incluem o PHP (que executa o site), o banco de dados (que guarda
os cadastros) e o phpMyAdmin (uma tela para administrar o banco).
Você não precisa instalar Node.js, npm ou Composer.

### Como iniciar no Windows

#### 1. Instalar os programas e preparar o projeto

1. Se ainda não tiver o XAMPP, baixe o instalador para Windows no
   [site oficial do Apache Friends](https://www.apachefriends.org/download.html).
   Escolha uma versão com **PHP 8.1 ou superior**.
2. Instale o XAMPP. Este tutorial considera a pasta padrão `C:\xampp`.
3. Se recebeu o projeto em um arquivo ZIP, clique nele com o botão direito e
   escolha **Extrair tudo**. Não tente executar o projeto dentro do ZIP.
4. Abra a pasta extraída e encontre os arquivos `index.php`, `conexao.php`,
   `router.php` e `banco.sql`. **Essa é a pasta principal do projeto.**
   Pode existir uma pasta `Complementa` dentro de outra com o mesmo nome.

Você pode deixar o projeto em Downloads. Não precisa movê-lo para `htdocs`.

#### 2. Ligar o banco e o servidor

1. Abra o **XAMPP Control Panel** pelo menu Iniciar. Se não encontrar, abra
   `C:\xampp\xampp-control.exe` pelo Explorador de Arquivos.
2. Na linha **Apache**, clique em **Start**.
3. Na linha **MySQL**, clique em **Start** também.
4. Espere os dois aparecerem como iniciados, normalmente destacados em verde.

O Apache permite abrir o phpMyAdmin. Mantenha o MySQL ligado enquanto usa o projeto.

#### 3. Importar o banco — somente na primeira vez

1. Com os serviços ligados, acesse `http://localhost/phpmyadmin`.
   Você também pode clicar em **Admin** na linha do MySQL no XAMPP.
2. Observe a lista de bancos do lado esquerdo. **Se já existir um banco chamado
   `complementa`, não importe o arquivo novamente nem apague esse banco.**
   Se ele já foi configurado para este projeto, continue no passo 4.
3. Se o banco ainda não existe, fique na página inicial do phpMyAdmin, sem selecionar
   outro banco. Clique no ícone de início/casa se precisar voltar para essa página.
4. Clique na aba **Importar** e depois em **Escolher arquivo**.
5. Selecione o arquivo `banco.sql` que está na pasta principal do projeto.
6. Mantenha o formato **SQL** e clique em **Importar** ou **Executar**, no final da página.
7. Aguarde a mensagem de sucesso. O banco `complementa` deve aparecer à esquerda.
8. Clique nele e confira se aparecem tabelas como `aluno`, `evento`, `inscricao`
   e `certificado`.

**Não crie o banco manualmente antes de importar:** o próprio `banco.sql` já cria
o banco `complementa`, suas tabelas e alguns dados de exemplo. Se a importação
mostrar um erro, consulte a seção de problemas abaixo antes de tentar novamente.

#### 4. Abrir o terminal na pasta certa

1. No Explorador de Arquivos, abra a pasta que contém `index.php` e `router.php`.
2. Clique na barra de endereço da janela, onde aparece o caminho da pasta.
3. Digite `powershell` e pressione **Enter**. Uma janela de terminal será aberta
   nessa pasta.
4. Digite o comando abaixo e pressione **Enter**:

```powershell
Get-ChildItem index.php, router.php, banco.sql
```

Se os três arquivos forem listados, você está na pasta correta. Se aparecer
“não é possível localizar”, volte ao Explorador e entre na pasta que contém esses arquivos.

A conexão padrão usa endereço `127.0.0.1`, porta `3306`, banco `complementa`,
usuário `root` e senha vazia. Se sua instalação usa outra porta ou senha, configure
na mesma janela antes de continuar, substituindo os valores do exemplo:

```powershell
$env:DB_PORT = '3307'
$env:DB_PASSWORD = 'SUBSTITUA_PELA_SENHA_DO_SEU_BANCO'
```

**Não execute esse exemplo se seu XAMPP usa os valores padrão.** Esses ajustes
valem apenas para essa janela: repita-os ao abrir outra. O projeto não carrega
arquivos `.env` automaticamente.

#### 5. Criar as contas de teste e iniciar o site

Na mesma janela, crie as contas demonstrativas de aluno, organizador e instituição:

```powershell
& "C:\xampp\php\php.exe" tools/criar_contas_teste.php
```

Aguarde **“Contas de teste prontas”**. Depois, inicie o site:

Na mesma janela do PowerShell, copie este comando e pressione **Enter**:

```powershell
& "C:\xampp\php\php.exe" -d upload_max_filesize=6M -d post_max_size=8M -S localhost:8000 router.php
```

Se instalou o XAMPP em outro lugar, substitua `C:\xampp` pelo caminho da sua instalação.

Os limites de upload desses comandos permitem enviar
os certificados de até 5 MB aceitos pelo sistema.

O terminal deve mostrar uma mensagem com `Development Server` e
`http://localhost:8000`. **Deixe essa janela aberta:** ela mantém o site funcionando.
É normal o terminal ficar ocupado e mostrar novas linhas conforme você navega.

#### 6. Acessar e fazer o primeiro cadastro

1. Abra o navegador e digite **`http://localhost:8000`** na barra de endereço.
2. A página inicial do Complementa deve aparecer. Abra **Eventos** para conferir
   se os eventos do banco são carregados.
3. Para criar sua conta de aluno, acesse `http://localhost:8000/cadastro.php`.
4. Preencha seus dados, escolha uma das instituições cadastradas, informe uma
   matrícula ainda não utilizada nessa instituição e crie uma senha com pelo menos 8 caracteres.
5. Depois do cadastro, entre com o perfil **Aluno**, seu e-mail e a senha que criou.

Os alunos e organizadores de exemplo do `banco.sql` **não têm senha de acesso
configurada**. Não existe uma senha padrão para entrar com essas contas.
Para experimentar os três perfis, use as **contas de teste** descritas abaixo.
Para criar sua própria conta de aluno, use o cadastro deste passo. Para obter outra
conta de organizador ou instituição, solicite ao responsável pelo projeto. O cadastro público
cria apenas alunos. O login aceita somente os perfis **Aluno**, **Organizador** e
**Instituição**; o acesso por login de equipe foi removido.

**Abra o endereço no navegador, não o arquivo `index.php` com um duplo clique.**
O PHP precisa do servidor iniciado no passo 5 para funcionar.

#### 7. Fechar e abrir novamente

Para encerrar, pressione **Ctrl + C** no PowerShell. Se não estiver usando
outros projetos, clique em **Stop** para Apache e MySQL no XAMPP. Os dados continuam salvos.

Na próxima vez:

1. Inicie o MySQL no XAMPP. Inicie também o Apache se quiser usar o phpMyAdmin.
2. Abra o PowerShell na pasta do projeto, como na etapa 4. Repita os ajustes de
   conexão apenas se sua instalação usa valores diferentes do padrão.
3. Execute o comando que inicia o site, na etapa 5.
4. Acesse `http://localhost:8000`.

**Não importe o banco novamente nem recrie as contas.**

#### Problemas comuns

| O que aconteceu | O que fazer |
| --- | --- |
| `localhost/phpmyadmin` não abre | Confira se o Apache está iniciado no XAMPP. Se a porta do Apache foi alterada, use o endereço com a porta configurada. |
| Apache ou MySQL não inicia | Confira a mensagem no painel do XAMPP. Outro programa pode estar usando a mesma porta. Peça ajuda ao responsável antes de apagar arquivos ou alterar o banco. |
| A importação diz que o banco ou uma tabela já existe | Não apague o banco. Se o projeto já estava configurado, pule a importação. Se houve uma importação incompleta, peça ao responsável para verificar as tabelas existentes. |
| O PowerShell não encontra `php.exe` | Confira onde o XAMPP foi instalado e ajuste o caminho no comando do passo 5. |
| Aparece `Failed to listen` ao iniciar o site | A porta 8000 pode estar ocupada. Troque `localhost:8000` por `localhost:8001` no comando e abra `http://localhost:8001` no navegador. |
| O site mostra “Não foi possível acessar o banco de dados” | Confira se o MySQL está ligado, se a importação terminou sem erros e se nome, usuário, senha e porta correspondem à sua instalação. |
| A página abre, mas o cadastro não lista instituições | Verifique se a importação terminou com sucesso e se a tabela `instituicao` tem os dados de exemplo. |
| Aparece que o e-mail ou a matrícula já está cadastrado | Entre com sua conta existente ou use outro e-mail e uma matrícula ainda não cadastrada na instituição escolhida. |
| O navegador mostra código PHP ou um endereço começando com `file:///` | Use `http://localhost:8000` com o servidor ligado, em vez de abrir o arquivo diretamente. |
| O site parou depois de fechar o terminal | Abra o PowerShell na pasta do projeto e execute novamente os comandos do passo 5. |

### Como iniciar no Mac

#### 1. Instalar os programas e preparar o projeto

1. Baixe o **MAMP para macOS** no [site oficial do MAMP](https://www.mamp.info/en/mac/).
   Use a edição gratuita; não é necessário comprar o MAMP PRO.
2. Escolha o instalador compatível com seu Mac. Para conferir o processador,
   clique no menu da maçã ** → Sobre Este Mac**: pode aparecer **Intel** ou um
   chip **Apple M1, M2, M3, M4…**.
3. Abra o instalador e siga as etapas. Este tutorial considera a instalação
   padrão em `/Applications/MAMP` (pasta **Aplicativos → MAMP** no Finder).
4. Se o projeto veio em ZIP, dê dois cliques nele no Finder para extrair.
5. Abra a pasta extraída e encontre `index.php`, `conexao.php`, `router.php`
   e `banco.sql`. Essa é a pasta principal, mesmo que exista uma pasta
   `Complementa` dentro de outra com o mesmo nome.

Você pode deixar o projeto em Downloads. Não precisa movê-lo para `htdocs`.

#### 2. Ligar o banco e o servidor

1. No Finder, abra **Aplicativos → MAMP** e inicie o aplicativo **MAMP**, não o MAMP PRO.
2. Selecione **Apache** como servidor web e uma versão de **PHP 8.1 ou superior**.
3. Clique em **Start** ou **Start Servers** e aguarde os serviços iniciarem.
4. Em **Settings/Preferences → Ports**, confira as portas. Este tutorial usa os
   padrões do MAMP: **Apache 8888** e **MySQL 8889**.
5. Clique em **WebStart** para abrir a página inicial do MAMP no navegador.

O servidor web permite abrir o phpMyAdmin. O serviço **MySQL** guarda os dados
do projeto e precisa ficar ligado enquanto você usa o site. Os nomes dos botões
podem variar conforme a versão. Veja a [interface oficial do MAMP](https://documentation.mamp.info/en/MAMP-Mac/Reference/Interface/).

#### 3. Importar o banco — somente na primeira vez

1. Clique em **WebStart** no MAMP e abra **phpMyAdmin** na página que aparecer.
   Se pedir login, o padrão local é usuário `root` e senha `root`.
2. Observe a lista de bancos do lado esquerdo. **Se já existir um banco chamado
   `complementa`, não importe o arquivo novamente nem apague esse banco.**
   Se ele já foi configurado para este projeto, continue no passo 4.
3. Se o banco ainda não existe, fique na página inicial do phpMyAdmin, sem selecionar
   outro banco. Clique no ícone de início/casa se precisar voltar para essa página.
4. Clique na aba **Importar** e depois em **Escolher arquivo**.
5. Selecione o arquivo `banco.sql` que está na pasta principal do projeto.
6. Mantenha o formato **SQL** e clique em **Importar** ou **Executar**, no final da página.
7. Aguarde a mensagem de sucesso. O banco `complementa` deve aparecer à esquerda.
8. Clique nele e confira se aparecem tabelas como `aluno`, `evento`, `inscricao`
   e `certificado`.

**Não crie o banco manualmente antes de importar:** o próprio `banco.sql` já cria
o banco `complementa`, suas tabelas e alguns dados de exemplo. Se a importação
mostrar um erro, consulte a seção de problemas abaixo antes de tentar novamente.

#### 4. Abrir o terminal na pasta certa

1. Pressione **Command (⌘) + Espaço**, digite **Terminal** e pressione **Return/Enter**.
2. Digite `cd` seguido de um espaço, **sem pressionar Enter ainda**.
3. Arraste do Finder a pasta principal do projeto para dentro da janela do Terminal.
   Isso preenche o caminho, inclusive quando o nome contém espaços.
4. Agora pressione **Return/Enter** para entrar nessa pasta.
5. Confira os arquivos com este comando:

```bash
ls index.php router.php banco.sql
```

Se aparecer `No such file or directory`, você está na pasta errada. Repita a etapa
com a pasta que realmente contém os três arquivos.

#### 5. Criar as contas de teste e iniciar o site

Na janela do Terminal aberta na pasta do projeto, configure a conexão com o MAMP.
Copie as linhas abaixo e pressione **Return/Enter**:

```bash
export DB_HOST='127.0.0.1'
export DB_PORT='8889'
export DB_NAME='complementa'
export DB_USER='root'
export DB_PASSWORD='root'
```

Esses são os padrões locais do MAMP. Se você alterou a porta ou a senha, use os
valores configurados. A [documentação de conexão do MAMP](https://documentation.mamp.info/en/MAMP-Mac/How-to/Connect-to-MySQL-from-PHP/)
confirma esses dados. A senha `root` aqui é do banco local, não da sua conta de aluno.

Descubra quais executáveis PHP foram instalados:

```bash
ls /Applications/MAMP/bin/php/*/bin/php
```

O Terminal mostrará um ou mais caminhos. Escolha um com PHP **8.1 ou superior**.
Nos dois comandos abaixo, **substitua `php8.X.Y` pelo nome exato da pasta que apareceu**
(o `X` e o `Y` são apenas marcadores, não devem ser digitados literalmente):

Primeiro, usando esse mesmo caminho do PHP, crie as contas demonstrativas:

```bash
"/Applications/MAMP/bin/php/php8.X.Y/bin/php" tools/criar_contas_teste.php
```

Aguarde **“Contas de teste prontas”**. Depois, inicie o site:

```bash
"/Applications/MAMP/bin/php/php8.X.Y/bin/php" -d upload_max_filesize=6M -d post_max_size=8M -S localhost:8000 router.php
```

Os limites de upload desses comandos permitem enviar
os certificados de até 5 MB aceitos pelo sistema.

O terminal deve mostrar uma mensagem com `Development Server` e
`http://localhost:8000`. **Deixe essa janela aberta:** ela mantém o site funcionando.
É normal o terminal ficar ocupado e mostrar novas linhas conforme você navega.

Repita as linhas `export` ao abrir uma nova janela do Terminal. O projeto não carrega arquivos `.env` automaticamente.

#### 6. Acessar e fazer o primeiro cadastro

1. Abra o navegador e digite **`http://localhost:8000`** na barra de endereço.
2. A página inicial do Complementa deve aparecer. Abra **Eventos** para conferir
   se os eventos do banco são carregados.
3. Para criar sua conta de aluno, acesse `http://localhost:8000/cadastro.php`.
4. Preencha seus dados, escolha uma das instituições cadastradas, informe uma
   matrícula ainda não utilizada nessa instituição e crie uma senha com pelo menos 8 caracteres.
5. Depois do cadastro, entre com o perfil **Aluno**, seu e-mail e a senha que criou.

Os alunos e organizadores de exemplo do `banco.sql` **não têm senha de acesso
configurada**. Não existe uma senha padrão para entrar com essas contas.
Para experimentar os três perfis, use as **contas de teste** descritas abaixo.
Para criar sua própria conta de aluno, use o cadastro deste passo. Para obter outra
conta de organizador ou instituição, solicite ao responsável pelo projeto. O cadastro público
cria apenas alunos. O login aceita somente os perfis **Aluno**, **Organizador** e
**Instituição**; o acesso por login de equipe foi removido.

**Abra o endereço no navegador, não o arquivo `index.php` com um duplo clique.**
O PHP precisa do servidor iniciado no passo 5 para funcionar.

#### 7. Fechar e abrir novamente

Para encerrar, pressione **Control + C** no Terminal — não é Command + C.
Se não estiver usando outros projetos, clique em **Stop** ou **Stop Servers**
no MAMP. Os dados continuam salvos.

Na próxima vez:

1. Abra o MAMP e clique em **Start**.
2. Abra o Terminal na pasta do projeto, como na etapa 4.
3. Repita as linhas `export` e o comando que inicia o site na etapa 5, usando o caminho real do PHP.
4. Acesse `http://localhost:8000`.

**Não importe o banco novamente nem recrie as contas.**

#### Problemas comuns

| O que aconteceu | O que fazer |
| --- | --- |
| O phpMyAdmin não abre | Inicie os serviços do MAMP e abra o phpMyAdmin pelo WebStart. |
| Apache ou MySQL não inicia | Confira a mensagem no aplicativo MAMP. Outro programa pode estar usando a mesma porta. Peça ajuda ao responsável antes de apagar arquivos ou alterar o banco. |
| A importação diz que o banco ou uma tabela já existe | Não apague o banco. Se o projeto já estava configurado, pule a importação. Se houve uma importação incompleta, peça ao responsável para verificar as tabelas existentes. |
| No Mac, o caminho do PHP mostra `No such file or directory` | Execute o comando `ls` do passo 5 e copie o caminho real. Substitua `php8.X.Y`; não use esse marcador literalmente. |
| No Mac, o site não conecta ao banco do MAMP | Confira se o MAMP está iniciado e execute as linhas `export` do passo 5 na mesma janela em que inicia o PHP. Verifique a porta do MySQL nas configurações do MAMP. |
| Aparece `Failed to listen` ao iniciar o site | A porta 8000 pode estar ocupada. Troque `localhost:8000` por `localhost:8001` no comando e abra `http://localhost:8001` no navegador. |
| O site mostra “Não foi possível acessar o banco de dados” | Confira se o MySQL está ligado, se a importação terminou sem erros e se nome, usuário, senha e porta correspondem à sua instalação. |
| A página abre, mas o cadastro não lista instituições | Verifique se a importação terminou com sucesso e se a tabela `instituicao` tem os dados de exemplo. |
| Aparece que o e-mail ou a matrícula já está cadastrado | Entre com sua conta existente ou use outro e-mail e uma matrícula ainda não cadastrada na instituição escolhida. |
| O navegador mostra código PHP ou um endereço começando com `file:///` | Use `http://localhost:8000` com o servidor ligado, em vez de abrir o arquivo diretamente. |
| O site parou depois de fechar o terminal | Abra o Terminal na pasta do projeto e execute novamente os comandos do passo 5. |

## Contas de acesso para teste

Após iniciar o projeto pelo tutorial de Windows ou Mac acima, abra
**`http://localhost:8000/login.php`**. Selecione o **perfil correspondente**
e entre com uma das três contas abaixo:

| Perfil no login | E-mail | Senha |
| --- | --- | --- |
| Aluno | `aluno.teste@complementa.test` | `Complementa123!` |
| Organizador | `organizador.teste@complementa.test` | `Complementa123!` |
| Instituição | `instituicao.teste@complementa.test` | `Complementa123!` |

O aluno está vinculado à **Instituição de Teste**, que também está associada à conta
institucional da tabela. Assim, os certificados encaminhados por esse aluno
aparecem para essa instituição analisar.

**Em uma nova instalação, importar `banco.sql` não cria essas contas de acesso.**
Execute o comando de criação de contas da etapa 5 do tutorial
[Windows](#como-iniciar-no-windows) ou [Mac](#como-iniciar-no-mac).
Depois, com o site iniciado, acesse `http://localhost:8000/login.php`.
O comando pode ser repetido: ele não duplica as contas nem redefine senhas existentes.
Se encontrar uma conta com o mesmo e-mail e dados diferentes, interrompe a operação
sem alterar os cadastros.

Essas credenciais são públicas e servem **somente para testes locais**. As senhas
são armazenadas no banco como hash. As contas começam sem eventos ou certificados;
entre como organizador para cadastrar um evento e como aluno para se inscrever.

---

> **Conectando experiências, complementando futuros.**

Plataforma web para divulgação de atividades acadêmicas, inscrição em eventos,
envio de certificados e validação de horas complementares de estudantes
universitários.

Este projeto é desenvolvido como Projeto Integrador (PjBL) do curso de
Engenharia de Software da PUCPR, no segundo semestre de 2026, envolvendo as
disciplinas de Desenvolvimento Web, Arquitetura e Banco de Dados e Engenharia
de Requisitos.

## Sumário

- [Como abrir o projeto com o banco de dados](#como-abrir-o-projeto-com-o-banco-de-dados--passo-a-passo)
- [Sobre o projeto](#sobre-o-projeto)
- [Perfis de usuário](#perfis-de-usuário)
- [Fluxo principal](#fluxo-principal)
- [Tecnologias](#tecnologias)
- [Status do desenvolvimento](#status-do-desenvolvimento)
- [Estrutura do repositório](#estrutura-do-repositório)
- [Banco de dados](#banco-de-dados)
- [Identidade visual](#identidade-visual)
- [Cronograma](#cronograma)
- [Como contribuir](#como-contribuir)
- [Segurança](#segurança)

## Sobre o projeto

Durante a graduação, estudantes precisam cumprir horas complementares por meio
de cursos, palestras, eventos e outras atividades. Essas informações podem
ficar distribuídas entre diferentes plataformas e processos.

O Complementa propõe centralizar esse fluxo em um único sistema, conectando
estudantes, organizadores e instituições. A aplicação deverá permitir que uma
atividade seja divulgada, que os estudantes se inscrevam e enviem seus
certificados e que a instituição responsável analise e valide as horas.

## Perfis de usuário

### Aluno

- Consultar eventos disponíveis e seus detalhes;
- realizar e acompanhar inscrições;
- enviar certificados após participar das atividades;
- acompanhar a situação dos certificados e o total de horas validadas;
- avaliar eventos e consultar notificações.

### Organizador

- Cadastrar, editar e gerenciar eventos;
- consultar eventos cadastrados;
- visualizar os alunos inscritos.
- enviar certificados individuais aos participantes com inscrição concluída,
  presença confirmada e evento encerrado.

### Instituição

- Analisar certificados enviados;
- aprovar ou recusar solicitações de horas;
- registrar observações sobre as validações.

## Fluxo principal

1. O organizador cadastra um evento.
2. O evento é disponibilizado na plataforma.
3. O aluno consulta os eventos e realiza sua inscrição.
4. Após participar, o aluno anexa seu certificado ou o recebe do organizador.
5. O aluno encaminha o certificado à instituição. Também é possível selecionar
   **Enviar também à instituição agora** durante o upload pelo aluno ou organizador.
   O documento aparece em **Analisar horas** no painel da instituição vinculada ao aluno.
6. A instituição aprova ou recusa a solicitação, registrando uma observação
   quando necessário.
7. Quando aprovado, o sistema contabiliza as horas complementares.
8. O aluno acompanha o resultado na plataforma.

Esse é o fluxo funcional central que deverá ser demonstrado na apresentação
final.

## Tecnologias

| Camada | Tecnologia | Uso previsto |
| --- | --- | --- |
| Front-end | HTML, CSS e JavaScript | Estrutura, estilo, responsividade e interações |
| Back-end | PHP | Formulários, autenticação e regras de negócio |
| Banco de dados | MySQL/SQL | Persistência dos usuários, eventos e validações |
| Versionamento | Git e GitHub | Colaboração e histórico do código |
| Organização | Trello | Planejamento e acompanhamento das sprints |

O projeto foi planejado inicialmente **sem frameworks**, com uma aplicação web
composta por front-end em HTML/CSS/JavaScript, back-end em PHP e banco de dados
MySQL.

## Status do desenvolvimento

O repositório contém a base inicial e a implementação da Sprint 2. Até o momento, estão
implementados ou documentados:

- escopo geral e fluxo principal definidos;
- planejamento das seis sprints documentado;
- identidade visual conceitual preparada;
- modelagem acadêmica do banco de dados trabalhada;
- diagrama de casos de uso apresentado;
- repositório e branches iniciais criados;
- layout inicial responsivo e navegação principal implementados;
- schema físico do MySQL e conexão PDO configurável por ambiente implementados.

- cadastro de alunos, login dos dois perfis e logout com sessões;
- senhas com `password_hash()` e `password_verify()`;
- listagem e detalhes de eventos consultando MySQL;
- cadastro de eventos e listagem própria para organizadores autenticados;
- criação de organizadores pela equipe, pela área de gestão.

Cadastro/login de alunos, criação/login de organizadores e cadastro/listagem/
detalhes de eventos foram testados com sucesso no MySQL local. A interface
também foi testada no Edge em desktop e celular. As migrações autorizadas
de autenticação foram aplicadas pelo responsável; `banco.sql` foi preservado.
Inscrições, certificados, análise institucional, horas, avaliações, notificações
e manutenção de cadastros foram acrescentados na Fase 2.

## Estrutura do repositório

```text
.
├── .gitignore   # Arquivos locais, temporários e segredos ignorados pelo Git
├── README.md    # Documentação do projeto
├── banco.sql    # Script do banco de dados
├── conexao.php  # Conexão entre PHP e MySQL
├── app.php      # Sessões, CSRF, layout compartilhado e consultas de eventos
├── cadastro.php # Cadastro público de alunos
├── login.php    # Login de alunos e organizadores
├── logout.php   # Encerramento da sessão por POST
├── eventos.php  # Lista de eventos do banco
├── evento.php   # Detalhes de um evento
├── meus_eventos.php # Eventos do organizador autenticado
├── novo_evento.php  # Cadastro de evento
├── index.php    # Página inicial com próximos eventos do banco
├── arquivo.php # Download controlado de certificados
├── uploads/    # Arquivos enviados, fora do controle de acesso público
├── script.js    # Interações e validações no navegador
└── style.css    # Estilos e identidade visual
```

Essa estrutura poderá crescer à medida que novas funcionalidades e páginas
forem implementadas.

## Banco de dados

A modelagem acadêmica prevê as seguintes entidades principais:

1. Aluno
2. Instituição
3. Organizador
4. Categoria
5. Evento
6. Inscrição
7. Certificado
8. Validação de Horas
9. Avaliação
10. Notificação

Os relacionamentos devem utilizar chaves primárias e estrangeiras conforme o
modelo validado pela equipe e pelo professor. Entre os relacionamentos
esperados estão:

- organizadores cadastram eventos;
- eventos pertencem a categorias;
- alunos realizam inscrições em eventos;
- certificados são associados aos alunos e às atividades correspondentes;
- instituições realizam validações de horas;
- avaliações e notificações complementam o fluxo da plataforma.

O modelo físico atualizado está em [`banco.sql`](./banco.sql). Além das dez
entidades principais, `usuario_gestao` guarda as contas da equipe e dos analistas
institucionais, e `certificado_conteudo` armazena os arquivos com acesso controlado.

### Consultas e manipulação de dados

O projeto também deverá utilizar comandos SQL para consultar e manipular os
dados conforme as necessidades de cada funcionalidade:

- **DQL (Data Query Language):** consultas com `SELECT`, incluindo filtros,
  ordenação, junções e agregações para exibir eventos, inscrições, certificados
  e horas validadas;
- **DML (Data Manipulation Language):** comandos `INSERT`, `UPDATE` e `DELETE`
  para cadastrar, alterar e remover registros de acordo com as regras do
  sistema.

Os exemplos abaixo usam os nomes e os estados definidos no schema atual:

```sql
-- DQL: consultar eventos disponíveis por categoria e data
SELECT e.id_evento, e.titulo, e.data_evento, c.nome AS categoria
FROM evento AS e
JOIN categoria AS c ON c.id_categoria = e.id_categoria
WHERE e.status = 'Publicado'
ORDER BY e.data_evento ASC;

-- DQL: consultar as horas aprovadas de um aluno
SELECT a.id_aluno, a.nome, COALESCE(SUM(vh.horas_aprovadas), 0) AS total_horas
FROM aluno AS a
LEFT JOIN inscricao AS i ON i.id_aluno = a.id_aluno
LEFT JOIN certificado AS c ON c.id_inscricao = i.id_inscricao
LEFT JOIN validacao_horas AS vh ON vh.id_certificado = c.id_certificado
  AND vh.status IN ('Aprovada', 'Parcialmente aprovada')
WHERE a.id_aluno = 1
GROUP BY a.id_aluno, a.nome;

-- DML - INSERT: cadastrar a inscrição de um aluno em um evento
INSERT INTO inscricao (id_aluno, id_evento, data_inscricao, status, presenca)
VALUES (1, 10, NOW(), 'Confirmada', FALSE);

-- DML - UPDATE: aprovar a validação de um certificado
UPDATE validacao_horas
SET status = 'Aprovada',
    horas_aprovadas = horas_solicitadas,
    observacao = 'Certificado conferido.'
WHERE id_validacao = 25;

-- DML - UPDATE: cancelar uma inscrição específica
UPDATE inscricao
SET status = 'Cancelada'
WHERE id_inscricao = 42
  AND id_aluno = 1;
```

As operações devem ser executadas pelo back-end com parâmetros preparados,
validação de permissões e transações quando uma ação alterar mais de uma
tabela. Com isso, o sistema reduz o risco de SQL injection e mantém a
consistência dos relacionamentos.

## Identidade visual

A interface seguirá uma proposta moderna, limpa e responsiva, baseada em:

- **Fonte:** Inter
- **Verde-escuro:** `#0B1F17`
- **Verde:** `#10B981`
- **Verde-claro:** `#34D399`
- **Cinza-claro:** `#E5E7EB`
- **Branco:** `#FFFFFF`

O conceito do logotipo combina um capelo de formatura com um símbolo de
confirmação. As referências de dashboard incluem informações como total de
horas, próximos eventos, inscrições, certificados e notificações.

### Painel visual

![Painel de identidade visual do Complementa](./assets/identidade-visual.jpeg)

Essas referências visuais representam a direção de design e não devem ser
interpretadas como telas já implementadas.

## Cronograma

O desenvolvimento está organizado em seis sprints semanais, entre **05/10/2026
e 15/11/2026**:

| Sprint | Período | Objetivo |
| --- | --- | --- |
| 1 | 05/10–11/10 | Estruturar o projeto, revisar o banco e preparar a conexão PHP/MySQL |
| 2 | 12/10–18/10 | Implementar a base de usuários e eventos |
| 3 | 19/10–25/10 | Implementar o fluxo de inscrições |
| 4 | 26/10–01/11 | Implementar o envio e acompanhamento de certificados |
| 5 | 02/11–08/11 | Implementar a análise e validação das horas |
| 6 | 09/11–15/11 | Integrar, testar, revisar e preparar a apresentação |

As entregas previstas incluem cadastro e login, gerenciamento de eventos,
inscrições, envio de certificados, análise institucional, atualização das
horas, revisão da interface, responsividade e teste do fluxo completo.

## Como executar

O tutorial completo para instalar, importar o banco e abrir o site está
[no início deste README](#como-abrir-o-projeto-com-o-banco-de-dados--passo-a-passo).
Ele também explica como acessar novamente sem recriar o banco e como resolver
os problemas mais comuns.

### Cancelar e refazer uma inscrição

Em **Minhas inscrições**, o aluno pode cancelar antes do início do evento,
desde que não tenha presença registrada. A inscrição cancelada fica com fundo
cinza-claro, borda diferenciada e o aviso **Inscrição cancelada**. O catálogo de
eventos também identifica os cancelamentos do próprio aluno.

Se o evento continua **Publicado** e ainda não começou, o botão **Inscrever-me
novamente** aparece em **Minhas inscrições** e nos detalhes do evento. A ação
reativa o mesmo registro e confirma a participação, sem duplicar a inscrição.
Uma inscrição ativa mostra sua situação nos detalhes, em vez de oferecer outro cadastro.

Eventos cancelados pelo organizador, em rascunho ou que já começaram não recebem
novas inscrições nem reinscrições. A conferência considera a data e o horário
de início, no horário de Brasília. Registros com presença ou certificado não
são reativados por esse fluxo. Não é necessário alterar o banco de dados.

### Envio de certificados pelo organizador

Entre com o perfil **Organizador** e acesse **Participantes**. Use a pesquisa ou os
filtros de evento e situação para localizar o aluno. Cada evento apresenta sua
própria lista de pessoas em ordem alfabética. Clique no nome do evento para abrir
ou recolher essa lista. Os eventos começam recolhidos; ao pesquisar ou filtrar,
as listas correspondentes são abertas para mostrar os resultados.
Clique no nome do aluno para abrir os detalhes da participação. Confirme a
presença do aluno, selecione a situação **Concluída** e clique em **Atualizar
participação**. Após o término do evento, o formulário **Enviar certificado ao
participante** permite anexar um PDF, JPG ou PNG de até 5 MB e informar sua
carga horária.

O aluno recebe uma notificação e pode baixar o arquivo em **Certificados**.
Depois de conferir o documento, informa as horas solicitadas e clica em
**Enviar certificado à instituição**, sem precisar baixar e anexar novamente.

Para disponibilizar o documento diretamente à instituição, marque **Enviar também
à instituição agora** no formulário de upload (disponível para organizador e aluno).
Essa opção solicita a validação de toda a carga horária informada. Desmarcada,
o documento fica com o aluno, que pode encaminhá-lo depois e solicitar menos horas.
O envio direto salva o certificado, a solicitação e as notificações na mesma transação.

O responsável institucional encontra os documentos em **Analisar horas**, onde pode
baixar o arquivo, aprovar, aprovar parcialmente ou recusar as horas. O aluno acompanha
o status, as horas reconhecidas e a observação em **Certificados** e **Minhas horas**.
O organizador pode consultar em **Participantes**, dentro do evento correspondente, se o documento já foi encaminhado.
Esse fluxo ocorre dentro do Complementa; não envia e-mails nem integra portais externos.

Cada inscrição aceita um certificado; o envio não substitui um arquivo já cadastrado. O organizador só
pode enviar certificados para participantes dos próprios eventos. Essa função
utiliza as tabelas existentes e não exige executar novamente `banco.sql`.

O acesso aos arquivos ocorre exclusivamente por `arquivo.php`, que verifica o aluno
ou a instituição destinatária. O `router.php` e o `.htaccess` bloqueiam URLs diretas
em `uploads/`. No Nginx, configure uma regra equivalente (`location ^~ /uploads/ { deny all; }`).

As páginas de eventos e a página inicial agora consultam o MySQL. Se a conexão
falhar, mostram uma mensagem genérica sem expor credenciais. Não há credenciais
versionadas no projeto.

### Testar o fluxo de certificados

No Windows, com Python 3, PHP com `pdo_mysql`/`fileinfo` e MariaDB do XAMPP,
execute `python tests/certificados.py`. Se necessário, configure `PHP_BIN` com
o caminho do PHP e `MYSQL_BIN_DIR` com a pasta dos executáveis do MariaDB.
Os testes criam uma cópia temporária da aplicação e um banco isolado, verificam
os envios manual e direto, a análise parcial, as permissões, as duplicidades e
a reversão de um upload quando o encaminhamento falha. Os processos e arquivos
temporários são removidos ao final; o banco do projeto não é utilizado.

## Como contribuir

O fluxo de trabalho utiliza duas branches principais:

- `main`: versão estável;
- `develop`: desenvolvimento e integração das funcionalidades.

Para contribuir:

1. crie uma branch a partir de `develop`;
2. implemente uma tarefa pequena e relacionada ao escopo;
3. teste o fluxo alterado;
4. descreva a mudança no commit e no pull request;
5. solicite a revisão antes de integrar em `develop`.

As tarefas e o andamento das sprints são acompanhados no Trello.

## Atualizar um banco já existente

Para registrar quando a instituição recebe um certificado, execute uma vez na
pasta do projeto (com o MySQL ligado). O comando preserva os dados existentes e
pode ser repetido sem criar a coluna novamente.

**Windows (PowerShell com XAMPP):**

```powershell
& "C:\xampp\php\php.exe" tools/atualizar_banco.php
```

**Mac (Terminal com MAMP):** substitua `php8.X.Y` pela versão instalada, como no
passo a passo de inicialização. Use as mesmas variáveis de conexão do projeto.

```bash
"/Applications/MAMP/bin/php/php8.X.Y/bin/php" tools/atualizar_banco.php
```

Novos bancos importados de `banco.sql` já incluem a coluna. A data é gravada no
encaminhamento à instituição, tanto pelo aluno quanto no envio direto pelo
organizador, e exibida no horário de Brasília. Recebimentos antigos ficam como
“Não registrado”, pois o horário não era armazenado.

## Segurança

Nunca versione senhas, tokens, credenciais de banco ou outros segredos. Use
variáveis de ambiente ou arquivos locais ignorados pelo Git. O arquivo
`.gitignore` já contempla `.env`, arquivos de editores, logs e temporários.

## Contexto acadêmico

O projeto também apoia as entregas das disciplinas envolvidas:

- **Desenvolvimento Web:** implementação da aplicação e integração
  front-end/back-end;
- **Arquitetura e Banco de Dados:** modelagem, relacionamentos e persistência;
- **Engenharia de Requisitos:** escopo, atores, casos de uso e regras do
  sistema.

O repositório oficial é
[`GustavoBaruffi/Complementa`](https://github.com/GustavoBaruffi/Complementa).
