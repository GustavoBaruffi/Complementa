CREATE DATABASE IF NOT EXISTS complementa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE complementa;

CREATE TABLE instituicao (
    id_instituicao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE aluno (
    id_aluno INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    curso VARCHAR(120) NOT NULL,
    horas_meta DECIMAL(6,2) NOT NULL DEFAULT 60.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE organizador (
    id_organizador INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    id_instituicao INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_organizador_instituicao FOREIGN KEY (id_instituicao)
        REFERENCES instituicao (id_instituicao) ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE TABLE categoria (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE evento (
    id_evento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_organizador INT UNSIGNED NOT NULL,
    id_categoria INT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    descricao TEXT NOT NULL,
    data_evento DATETIME NOT NULL,
    local_evento VARCHAR(180) NOT NULL,
    horas DECIMAL(5,2) NOT NULL,
    status ENUM('disponivel', 'encerrado', 'cancelado') NOT NULL DEFAULT 'disponivel',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evento_organizador FOREIGN KEY (id_organizador)
        REFERENCES organizador (id_organizador),
    CONSTRAINT fk_evento_categoria FOREIGN KEY (id_categoria)
        REFERENCES categoria (id_categoria)
) ENGINE = InnoDB;

CREATE TABLE inscricao (
    id_inscricao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_aluno INT UNSIGNED NOT NULL,
    id_evento INT UNSIGNED NOT NULL,
    data_inscricao DATE NOT NULL,
    status ENUM('realizada', 'cancelada', 'concluida') NOT NULL DEFAULT 'realizada',
    UNIQUE KEY uq_inscricao_aluno_evento (id_aluno, id_evento),
    CONSTRAINT fk_inscricao_aluno FOREIGN KEY (id_aluno)
        REFERENCES aluno (id_aluno) ON DELETE CASCADE,
    CONSTRAINT fk_inscricao_evento FOREIGN KEY (id_evento)
        REFERENCES evento (id_evento) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE certificado (
    id_certificado INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_inscricao INT UNSIGNED NOT NULL UNIQUE,
    arquivo VARCHAR(255) NOT NULL,
    enviado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pendente', 'aprovado', 'recusado') NOT NULL DEFAULT 'pendente',
    CONSTRAINT fk_certificado_inscricao FOREIGN KEY (id_inscricao)
        REFERENCES inscricao (id_inscricao) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE validacao_horas (
    id_validacao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_certificado INT UNSIGNED NOT NULL UNIQUE,
    id_instituicao INT UNSIGNED NOT NULL,
    horas_validadas DECIMAL(5,2) NOT NULL,
    status ENUM('pendente', 'aprovado', 'recusado') NOT NULL DEFAULT 'pendente',
    observacao TEXT NULL,
    validado_em TIMESTAMP NULL,
    CONSTRAINT fk_validacao_certificado FOREIGN KEY (id_certificado)
        REFERENCES certificado (id_certificado) ON DELETE CASCADE,
    CONSTRAINT fk_validacao_instituicao FOREIGN KEY (id_instituicao)
        REFERENCES instituicao (id_instituicao)
) ENGINE = InnoDB;

CREATE TABLE avaliacao (
    id_avaliacao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_aluno INT UNSIGNED NOT NULL,
    id_evento INT UNSIGNED NOT NULL,
    nota TINYINT UNSIGNED NOT NULL,
    comentario TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_avaliacao_aluno_evento (id_aluno, id_evento),
    CONSTRAINT ck_avaliacao_nota CHECK (nota BETWEEN 1 AND 5),
    CONSTRAINT fk_avaliacao_aluno FOREIGN KEY (id_aluno) REFERENCES aluno (id_aluno) ON DELETE CASCADE,
    CONSTRAINT fk_avaliacao_evento FOREIGN KEY (id_evento) REFERENCES evento (id_evento) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE notificacao (
    id_notificacao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_aluno INT UNSIGNED NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensagem TEXT NOT NULL,
    lida BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notificacao_aluno FOREIGN KEY (id_aluno)
        REFERENCES aluno (id_aluno) ON DELETE CASCADE
) ENGINE = InnoDB;

INSERT INTO categoria (nome) VALUES
    ('Tecnologia'), ('Carreira'), ('Comunidade');