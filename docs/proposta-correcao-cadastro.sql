-- ALTERACAO AUTORIZADA E APLICADA PELO RESPONSAVEL EM 20/10/2026.
-- Mantido como registro. NAO executar novamente no mesmo banco.
-- Aplicavel somente ao schema real inspecionado em 20/10/2026.
-- Exige autorizacao do responsavel e uma conta com permissao ALTER.
-- Nao substitui banco.sql; nao recria tabelas nem remove registros.
--
-- Pre-condicoes a conferir novamente antes de executar:
-- 1. aluno.senha_hash ainda nao existe.
-- 2. nao existem emails duplicados em aluno.
-- 3. o banco selecionado e o banco correto do projeto.
--
-- NULL preserva a compatibilidade com eventuais registros preexistentes,
-- sem inventar senhas para eles. O cadastro novo sempre gravara um hash.
-- Login deve recusar contas sem hash e exigir futura definicao de senha.
USE complementa;

ALTER TABLE aluno
    ADD COLUMN senha_hash VARCHAR(255) NULL,
    ADD UNIQUE KEY uq_aluno_email (email);
