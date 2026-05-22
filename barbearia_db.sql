-- Criar o banco de dados se não existir
CREATE DATABASE IF NOT EXISTS `barbearia_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `barbearia_db`;

-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `tipo` VARCHAR(20) NOT NULL DEFAULT 'cliente', -- 'cliente' ou 'admin'
  `data_cadastro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Agendamentos
CREATE TABLE IF NOT EXISTS `agendamentos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `telefone` VARCHAR(20) NOT NULL,
  `servico` VARCHAR(50) NOT NULL,
  `valor` DECIMAL(10,2) NOT NULL, -- Valor do serviço para controle de lucros
  `data_agendamento` DATE NOT NULL,
  `hora_agendamento` TIME NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'agendado', -- 'agendado', 'concluido' ou 'cancelado'
  `data_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
