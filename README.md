# Complementa

> **Conectando experiências, complementando futuros.**

Plataforma web para divulgação de atividades acadêmicas, inscrição em eventos,
envio de certificados e validação de horas complementares de estudantes
universitários.

Este projeto é desenvolvido como Projeto Integrador (PjBL) do curso de
Engenharia de Software da PUCPR, no segundo semestre de 2026, envolvendo as
disciplinas de Desenvolvimento Web, Arquitetura e Banco de Dados e Engenharia
de Requisitos.

## Sumário

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

### Instituição

- Analisar certificados enviados;
- aprovar ou recusar solicitações de horas;
- registrar observações sobre as validações.

## Fluxo principal

1. O organizador cadastra um evento.
2. O evento é disponibilizado na plataforma.
3. O aluno consulta os eventos e realiza sua inscrição.
4. Após participar, o aluno envia o certificado.
5. A instituição analisa o certificado.
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

O repositório está na primeira fase de implementação. Até o momento, estão
confirmados:

- escopo geral e fluxo principal definidos;
- planejamento das seis sprints documentado;
- identidade visual conceitual preparada;
- modelagem acadêmica do banco de dados trabalhada;
- diagrama de casos de uso apresentado;
- repositório e branches iniciais criados;
- layout inicial responsivo e navegação principal implementados;
- schema inicial do MySQL e conexão PDO configurável por ambiente implementados.

As funcionalidades de autenticação, cadastros, inscrições, certificados e
validação serão implementadas nas próximas sprints. A presença de uma tela ou
tabela no repositório **não significa que o fluxo completo correspondente
esteja implementado**.

## Estrutura do repositório

```text
.
├── .gitignore   # Arquivos locais, temporários e segredos ignorados pelo Git
├── README.md    # Documentação do projeto
├── banco.sql    # Script do banco de dados
├── conexao.php  # Conexão entre PHP e MySQL
├── index.php    # Página inicial da aplicação
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

O modelo físico definitivo será refletido em [`banco.sql`](./banco.sql) quando
for implementado e validado.

### Consultas e manipulação de dados

O projeto também deverá utilizar comandos SQL para consultar e manipular os
dados conforme as necessidades de cada funcionalidade:

- **DQL (Data Query Language):** consultas com `SELECT`, incluindo filtros,
  ordenação, junções e agregações para exibir eventos, inscrições, certificados
  e horas validadas;
- **DML (Data Manipulation Language):** comandos `INSERT`, `UPDATE` e `DELETE`
  para cadastrar, alterar e remover registros de acordo com as regras do
  sistema.

Os exemplos abaixo são ilustrativos e deverão ser ajustados aos nomes das
tabelas e colunas definidos na modelagem física:

```sql
-- DQL: consultar eventos disponíveis por categoria e data
SELECT e.id_evento, e.nome, e.data_evento, c.nome AS categoria
FROM evento AS e
JOIN categoria AS c ON c.id_categoria = e.id_categoria
WHERE e.status = 'disponivel'
ORDER BY e.data_evento ASC;

-- DQL: consultar as horas aprovadas de um aluno
SELECT a.id_aluno, a.nome, COALESCE(SUM(vh.horas_validadas), 0) AS total_horas
FROM aluno AS a
LEFT JOIN inscricao AS i ON i.id_aluno = a.id_aluno
LEFT JOIN certificado AS c ON c.id_inscricao = i.id_inscricao
LEFT JOIN validacao_horas AS vh ON vh.id_certificado = c.id_certificado
  AND vh.status = 'aprovado'
WHERE a.id_aluno = 1
GROUP BY a.id_aluno, a.nome;

-- DML - INSERT: cadastrar a inscrição de um aluno em um evento
INSERT INTO inscricao (id_aluno, id_evento, data_inscricao, status)
VALUES (1, 10, CURRENT_DATE, 'realizada');

-- DML - UPDATE: aprovar a validação de um certificado
UPDATE validacao_horas
SET status = 'aprovado',
    observacao = 'Certificado conferido.'
WHERE id_validacao = 25;

-- DML - DELETE: cancelar uma inscrição específica
DELETE FROM inscricao
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

Requisitos: PHP 8.1+, MySQL 8+ e um servidor web (Apache, Nginx ou o servidor
embutido do PHP).

1. Crie o banco e as tabelas executando [`banco.sql`](./banco.sql) no MySQL:
  `mysql -u root -p < banco.sql`.
2. Configure, de preferência por variáveis de ambiente, `DB_HOST`, `DB_PORT`,
  `DB_NAME`, `DB_USER` e `DB_PASSWORD`. Os valores padrão estão documentados
  em [`conexao.php`](./conexao.php) para desenvolvimento local.
3. Inicie o servidor embutido na raiz do projeto:
  ```bash
  php -S localhost:8000
  ```
4. Acesse `http://localhost:8000` no navegador.

A página inicial da sprint 1 é navegável sem conexão com o banco. As operações
de persistência serão conectadas nas próximas sprints; não há credenciais de
teste versionadas no repositório.

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
