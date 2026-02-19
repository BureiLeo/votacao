-- ============================================================
--  VOTAÇÃO JORNADA JOVEM — Script de instalação
--  Execute no phpMyAdmin: http://localhost/phpmyadmin
--  Aba SQL → colar tudo → Executar
-- ============================================================

CREATE DATABASE IF NOT EXISTS votacao_jornada
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE votacao_jornada;

-- ------------------------------------------------------------
-- CARGOS FIXOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cargos (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    nome  VARCHAR(120) NOT NULL,
    ordem INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO cargos (nome, ordem) VALUES
    ('Espiritualidade e Liturgia (Intercessão)', 1),
    ('Animação e Música (Banda)',                 2),
    ('Teatro',                                   3),
    ('Cozinha',                                  4),
    ('Decoração',                                5),
    ('Secretaria e Tesouraria',                  6),
    ('Coordenação Geral',                        7);

-- ------------------------------------------------------------
-- CANDIDATOS  (cargo_id referencia cargos.id)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS candidatos (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    nome     VARCHAR(120) NOT NULL,
    cargo_id INT          NOT NULL,
    ativo    TINYINT(1)   NOT NULL DEFAULT 1,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Exemplos — substitua pelos nomes reais via admin
INSERT INTO candidatos (nome, cargo_id) VALUES
    ('Candidato A', 1), ('Candidato B', 1),
    ('Candidato C', 2), ('Candidato D', 2),
    ('Candidato E', 3),
    ('Candidato F', 4),
    ('Candidato G', 5),
    ('Candidato H', 6),
    ('Candidato I', 7), ('Candidato J', 7);

-- ------------------------------------------------------------
-- CÓDIGOS ÚNICOS DE VOTAÇÃO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS codigos (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    codigo    VARCHAR(20)  NOT NULL UNIQUE,
    usado     TINYINT(1)   NOT NULL DEFAULT 0,
    criado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usado_em  DATETIME         NULL DEFAULT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- VOTOS
--   candidato_id = NULL  →  voto nulo
--   UNIQUE(codigo_id, cargo_id) impede voto duplo no mesmo cargo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS votos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    codigo_id    INT  NOT NULL,
    cargo_id     INT  NOT NULL,
    candidato_id INT      NULL DEFAULT NULL,
    criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_voto (codigo_id, cargo_id),
    FOREIGN KEY (codigo_id)    REFERENCES codigos(id),
    FOREIGN KEY (cargo_id)     REFERENCES cargos(id),
    FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CONFIGURAÇÕES DO SISTEMA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS configuracoes (
    chave VARCHAR(60)  NOT NULL PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- votacao_ativa: controla se o sistema aceita novos votos
-- admin_hash: hash bcrypt da senha do painel admin (padrão: admin@2026)
INSERT INTO configuracoes (chave, valor) VALUES
    ('votacao_ativa', 'true'),
    ('revelado',      'false'),
    ('admin_hash', '$2y$10$thuIr1l.Vof3NCOGs2OrP.bRwPHgzQxYIzdHEpi2yWrZtKRnKdRMe')
ON DUPLICATE KEY UPDATE chave = chave;
-- Se já instalou o banco, rode este INSERT manualmente:
-- INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('revelado', 'false');
-- ↑ Para trocar a senha rode no terminal:
--   C:\xampp\php\php.exe -r "echo password_hash('NOVA_SENHA', PASSWORD_DEFAULT);"
--   Depois faça UPDATE configuracoes SET valor='HASH' WHERE chave='admin_hash';
