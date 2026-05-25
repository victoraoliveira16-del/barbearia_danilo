-- 1. Criação do Banco de Dados
CREATE DATABASE IF NOT EXISTS barbearia_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE barbearia_db;

-- 2. Criação da Tabela de Usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'barbeiro', 'cliente') DEFAULT 'cliente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Criação da Tabela de Agendamentos (Preservando nome e telefone para compatibilidade e contato rápido)
CREATE TABLE IF NOT EXISTS agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    data_hora DATETIME NOT NULL,
    servico VARCHAR(100) NOT NULL,
    valor DECIMAL(10, 2) NOT NULL,
    status ENUM('pendente', 'confirmado', 'cancelado', 'concluido') DEFAULT 'pendente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Criação da Tabela de Assinaturas (Controle de Planos)
CREATE TABLE IF NOT EXISTS assinaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome_cliente VARCHAR(100) NOT NULL,
    plano VARCHAR(50) NOT NULL,
    preco VARCHAR(50) NOT NULL,
    metodo_pagamento VARCHAR(50) NOT NULL,
    data_assinatura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================================================
-- SEED DATA (INSERÇÃO DAS CONTAS DE TESTE DE PLANOS)
-- ===================================================

-- 1. Criação do Barbeiro Admin (Senha: adminbarber)
INSERT INTO usuarios (id, nome, email, senha, tipo)
VALUES (1, 'Danilo Admin', 'barbeiro@danilo.com', '$2y$10$lU.yYn7N2Wle9r3M4E1Mee/Yf3VqF9kYxWv3m4c.g/Zz5Y6n/o22W', 'admin')
ON DUPLICATE KEY UPDATE senha=VALUES(senha);

-- 2. Criação do Cliente VIP Style (Senha: vipstyle)
INSERT INTO usuarios (id, nome, email, senha, tipo)
VALUES (2, 'Cliente VIP Danilo', 'vip@danilo.com', '$2y$10$4.eK8/FvVzG.V0z7mUoFmO7hEw7fXvYgA7f.vW2y4aXoB1C28H6d2', 'cliente')
ON DUPLICATE KEY UPDATE senha=VALUES(senha);

-- 3. Criação do Cliente Cavalheiro (Senha: cavalheiro)
INSERT INTO usuarios (id, nome, email, senha, tipo)
VALUES (3, 'Cliente Cavalheiro Danilo', 'cavalheiro@danilo.com', '$2y$10$9.jK8/FvVzG.V0z7mUoFmO7hEw7fXvYgA7f.vW2y4aXoB1C28H6d2', 'cliente')
ON DUPLICATE KEY UPDATE senha=VALUES(senha);

-- 4. Criação da Assinatura VIP Style (Clientes Ilimitados)
INSERT INTO assinaturas (id, usuario_id, nome_cliente, plano, preco, metodo_pagamento)
VALUES (1, 2, 'Cliente VIP Danilo', 'Plano VIP Style', 'R$ 129/mês', 'Pix Instantâneo')
ON DUPLICATE KEY UPDATE plano=VALUES(plano);

-- 5. Criação da Assinatura Cavalheiro (Clientes Limite 2/mês)
INSERT INTO assinaturas (id, usuario_id, nome_cliente, plano, preco, metodo_pagamento)
VALUES (2, 3, 'Cliente Cavalheiro Danilo', 'Plano Cavalheiro', 'R$ 69/mês', 'Cartão de Crédito')
ON DUPLICATE KEY UPDATE plano=VALUES(plano);
