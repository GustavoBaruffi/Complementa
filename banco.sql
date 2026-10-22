CREATE DATABASE complementa;
USE complementa;

CREATE TABLE instituicao (
 id_instituicao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(150) NOT NULL, sigla VARCHAR(20) NULL, cidade VARCHAR(100) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE aluno (
 id_aluno INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, senha_hash VARCHAR(255) NULL,
 matricula VARCHAR(20) NOT NULL, id_instituicao INT UNSIGNED NOT NULL,
 data_nascimento DATE NOT NULL, curso VARCHAR(100) NOT NULL, cidade VARCHAR(100) NOT NULL,
 meta_horas SMALLINT UNSIGNED NOT NULL,
 UNIQUE (id_instituicao,matricula), FOREIGN KEY (id_instituicao) REFERENCES instituicao(id_instituicao),
 CHECK (meta_horas BETWEEN 0 AND 999)
);

CREATE TABLE organizador (
 id_organizador INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(150) NOT NULL, tipo VARCHAR(30) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE,
 telefone VARCHAR(20) NULL, cidade VARCHAR(100) NOT NULL, senha_hash VARCHAR(255) NULL
);

CREATE TABLE categoria (
 id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(80) NOT NULL UNIQUE, descricao VARCHAR(500) NULL
);

CREATE TABLE evento (
 id_evento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_organizador INT UNSIGNED NOT NULL, id_categoria INT UNSIGNED NOT NULL,
 titulo VARCHAR(150) NOT NULL, descricao VARCHAR(2000) NOT NULL, data_evento DATE NOT NULL,
 horario_inicio TIME NOT NULL, horario_termino TIME NOT NULL,
 carga_horaria DECIMAL(4,1) NOT NULL, modalidade VARCHAR(20) NOT NULL,
 cidade VARCHAR(100) NULL, local_evento VARCHAR(200) NULL, link VARCHAR(500) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'Publicado', validade_horas VARCHAR(30) NOT NULL DEFAULT 'Necessita validação',
 FOREIGN KEY (id_organizador) REFERENCES organizador(id_organizador),
 FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria),
 CHECK (carga_horaria BETWEEN 0.5 AND 999), CHECK (horario_termino > horario_inicio)
);

CREATE TABLE inscricao (
 id_inscricao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_aluno INT UNSIGNED NOT NULL, id_evento INT UNSIGNED NOT NULL,
 data_inscricao DATETIME NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Confirmada', presenca BOOLEAN NOT NULL DEFAULT FALSE,
 UNIQUE (id_aluno,id_evento), FOREIGN KEY (id_aluno) REFERENCES aluno(id_aluno),
 FOREIGN KEY (id_evento) REFERENCES evento(id_evento)
);

CREATE TABLE certificado (
 id_certificado INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_inscricao INT UNSIGNED NOT NULL UNIQUE,
 arquivo VARCHAR(255) NOT NULL, data_envio DATETIME NOT NULL, carga_horaria_declarada DECIMAL(4,1) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'Pendente',
 FOREIGN KEY (id_inscricao) REFERENCES inscricao(id_inscricao), CHECK (carga_horaria_declarada BETWEEN 0.5 AND 999)
);

CREATE TABLE validacao_horas (
 id_validacao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_certificado INT UNSIGNED NOT NULL UNIQUE, id_instituicao INT UNSIGNED NOT NULL,
 horas_solicitadas DECIMAL(4,1) NOT NULL, horas_aprovadas DECIMAL(4,1) NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'Pendente', data_analise DATETIME NULL, observacao VARCHAR(1000) NULL,
 FOREIGN KEY (id_certificado) REFERENCES certificado(id_certificado),
 FOREIGN KEY (id_instituicao) REFERENCES instituicao(id_instituicao),
 CHECK (horas_solicitadas BETWEEN 0.5 AND 999), CHECK (horas_aprovadas BETWEEN 0 AND 999)
);

CREATE TABLE avaliacao (
 id_avaliacao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_aluno INT UNSIGNED NOT NULL, id_evento INT UNSIGNED NOT NULL,
 nota TINYINT UNSIGNED NOT NULL, comentario VARCHAR(1000) NULL, data_avaliacao DATETIME NOT NULL,
 UNIQUE (id_aluno,id_evento), FOREIGN KEY (id_aluno) REFERENCES aluno(id_aluno),
 FOREIGN KEY (id_evento) REFERENCES evento(id_evento), CHECK (nota BETWEEN 1 AND 5)
);

CREATE TABLE notificacao (
 id_notificacao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_aluno INT UNSIGNED NOT NULL,
 titulo VARCHAR(100) NOT NULL, mensagem VARCHAR(500) NOT NULL, data_criacao DATETIME NOT NULL,
 lida BOOLEAN NOT NULL DEFAULT FALSE, FOREIGN KEY (id_aluno) REFERENCES aluno(id_aluno)
);

CREATE TABLE usuario_gestao (
 id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(150) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, senha_hash VARCHAR(255) NOT NULL,
 perfil VARCHAR(20) NOT NULL, id_instituicao INT UNSIGNED NULL,
 FOREIGN KEY (id_instituicao) REFERENCES instituicao(id_instituicao),
 CHECK ((perfil='equipe' AND id_instituicao IS NULL) OR (perfil='instituicao' AND id_instituicao IS NOT NULL))
);

CREATE TABLE certificado_conteudo (
 id_certificado INT UNSIGNED PRIMARY KEY,
 mime VARCHAR(40) NOT NULL,
 caminho_arquivo VARCHAR(255) NOT NULL,
 FOREIGN KEY (id_certificado) REFERENCES certificado(id_certificado)
);

INSERT INTO instituicao (nome, sigla, cidade, email) VALUES
('PUCPR', 'PUCPR', 'Curitiba', 'contato@pucpr.exemplo'),
('Instituto Exemplo', 'IE', 'Curitiba', 'contato@instituto.exemplo');

INSERT INTO categoria (nome, descricao) VALUES
('Tecnologia', 'Eventos sobre tecnologia'),
('Pesquisa', 'Eventos de pesquisa'),
('Carreira', 'Eventos de carreira'),
('Extensao', 'Eventos de extensao'),
('Cultura', 'Eventos culturais'),
('Empreendedorismo', 'Eventos de empreendedorismo');

INSERT INTO aluno (nome, email, matricula, id_instituicao, data_nascimento, curso, cidade, meta_horas) VALUES
('Ana Souza', 'ana.aluna@exemplo.com', 'MAT001', 1, '2003-02-14', 'Engenharia de Software', 'Curitiba', 60),
('Bruno Lima', 'bruno.aluno@exemplo.com', 'MAT002', 1, '2002-06-25', 'Direito', 'Curitiba', 60),
('Carla Mendes', 'carla.aluna@exemplo.com', 'MAT003', 1, '2004-09-10', 'Administracao', 'Curitiba', 60),
('Daniel Rocha', 'daniel.aluno@exemplo.com', 'MAT004', 1, '2001-11-06', 'Engenharia de Software', 'Pinhais', 60),
('Eduarda Alves', 'eduarda.aluna@exemplo.com', 'MAT005', 1, '2003-08-17', 'Psicologia', 'Curitiba', 60),
('Felipe Costa', 'felipe.aluno@exemplo.com', 'MAT006', 1, '2002-03-04', 'Design', 'Colombo', 60),
('Gabriela Silva', 'gabriela.aluna@exemplo.com', 'MAT007', 1, '2004-01-12', 'Pedagogia', 'Curitiba', 60);

INSERT INTO organizador (nome, tipo, email, telefone, cidade) VALUES
('Equipe Tecnologia', 'Equipe', 'tecnologia@exemplo.com', '41999990001', 'Curitiba'),
('Equipe Pesquisa', 'Equipe', 'pesquisa@exemplo.com', '41999990002', 'Curitiba'),
('Equipe Carreira', 'Equipe', 'carreira@exemplo.com', '41999990003', 'Curitiba'),
('Equipe Extensao', 'Equipe', 'extensao@exemplo.com', '41999990004', 'Curitiba'),
('Equipe Cultura', 'Equipe', 'cultura@exemplo.com', '41999990005', 'Curitiba'),
('Equipe Empreendedorismo', 'Equipe', 'empreender@exemplo.com', '41999990006', 'Curitiba');

INSERT INTO evento (id_organizador, id_categoria, titulo, descricao, data_evento, horario_inicio, horario_termino, carga_horaria, modalidade) VALUES
(1, 1, 'Palestra de Python', 'Introducao ao Python', '2026-11-05', '09:00:00', '11:00:00', 2.0, 'Presencial'),
(2, 2, 'Feira de Pesquisa', 'Apresentacao de projetos', '2026-11-06', '14:00:00', '16:00:00', 2.0, 'Presencial'),
(3, 3, 'Oficina de Curriculo', 'Preparacao profissional', '2026-11-07', '10:00:00', '12:00:00', 2.0, 'Online'),
(4, 4, 'Semana de Extensao', 'Atividades de extensao', '2026-11-08', '08:00:00', '12:00:00', 4.0, 'Presencial'),
(5, 5, 'Palestra de Cultura', 'Arte e sociedade', '2026-11-09', '13:00:00', '15:00:00', 2.0, 'Online'),
(6, 6, 'Empreender na Pratica', 'Oficina de negocios', '2026-11-10', '10:00:00', '13:00:00', 3.0, 'Presencial'),
(1, 1, 'Banco de Dados', 'Introducao ao SQL', '2026-11-11', '09:00:00', '12:00:00', 3.0, 'Presencial');

INSERT INTO inscricao (id_aluno, id_evento, data_inscricao, status, presenca) VALUES
(1, 1, '2026-10-22 09:00:00', 'Confirmada', 0),
(2, 2, '2026-10-22 09:10:00', 'Confirmada', 0),
(3, 3, '2026-10-22 09:20:00', 'Confirmada', 0),
(4, 4, '2026-10-22 09:30:00', 'Confirmada', 0),
(5, 5, '2026-10-22 09:40:00', 'Confirmada', 0),
(6, 6, '2026-10-22 09:50:00', 'Confirmada', 0),
(7, 7, '2026-10-22 10:00:00', 'Confirmada', 0);

INSERT INTO notificacao (id_aluno, titulo, mensagem, data_criacao, lida) VALUES
(1, 'Bem-vinda', 'Seu cadastro foi realizado', '2026-10-22 11:00:00', 0),
(2, 'Novo evento', 'Confira a programacao', '2026-10-22 11:05:00', 0),
(3, 'Inscricao', 'Sua inscricao foi registrada', '2026-10-22 11:10:00', 0),
(4, 'Lembrete', 'Evento agendado', '2026-10-22 11:15:00', 0),
(5, 'Aviso', 'Confira sua conta', '2026-10-22 11:20:00', 0),
(6, 'Novidades', 'Novos eventos cadastrados', '2026-10-22 11:25:00', 0),
(7, 'Certificados', 'Confira as novidades', '2026-10-22 11:30:00', 0);