-- Migracao autorizada e aplicada pelo responsavel no Workbench em 20/10/2026.
-- Mantida como registro. NAO executar novamente no mesmo banco.
-- Inspecao em 20/10/2026: sem senha_hash e sem emails duplicados.
-- Antes de executar, confirme novamente que a consulta abaixo retorna zero linhas.
-- Se houver duplicados, pare e revise-os com a equipe; nao apague registros.
USE complementa;

SELECT email, COUNT(*) AS quantidade
FROM organizador
GROUP BY email
HAVING COUNT(*) > 1;

-- Preserva contas existentes sem inventar senhas. Contas sem hash nao autenticam.
-- O cadastro pela equipe sempre preenche senha_hash com password_hash().
-- Executar uma unica vez; nao amplia as permissoes da conta da aplicacao.
ALTER TABLE organizador
    ADD COLUMN senha_hash VARCHAR(255) NULL,
    ADD UNIQUE KEY uq_organizador_email (email);
