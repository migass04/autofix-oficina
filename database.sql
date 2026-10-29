CREATE DATABASE IF NOT EXISTS oficina_autofix DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oficina_autofix;

-- 1. Tabela de Utilizadores
CREATE TABLE IF NOT EXISTS utilizadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('chefe', 'mecanico', 'cliente') NOT NULL DEFAULT 'cliente',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabela de Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    nif VARCHAR(20) NULL,
    data_registo TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabela de Veículos
CREATE TABLE IF NOT EXISTS veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    tipo ENUM('carro', 'moto') NOT NULL DEFAULT 'carro',
    marca VARCHAR(50) NOT NULL,
    modelo VARCHAR(50) NOT NULL,
    matricula VARCHAR(20) NOT NULL UNIQUE,
    data_registo TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabela de Reparações / Serviços
CREATE TABLE IF NOT EXISTS reparacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    veiculo_id INT NOT NULL,
    descricao TEXT NOT NULL,
    km INT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estado ENUM('Pendente', 'Em Andamento', 'Concluído') NOT NULL DEFAULT 'Pendente',
    data_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_fim DATETIME NULL,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabela de Agendamentos
CREATE TABLE IF NOT EXISTS agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    veiculo_id INT NOT NULL,
    servico VARCHAR(250) NOT NULL,
    data_agendamento DATETIME NOT NULL,
    estado ENUM('Pendente', 'Aprovado', 'Recusado') DEFAULT 'Pendente',
    observacoes TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dados Fictícios de Demonstração
-- Palavras-passe encriptadas (password123 para todos)
INSERT INTO utilizadores (nome, email, senha, tipo) VALUES
('Carlos Chefe', 'chefe@autofix.pt', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1eLmy2L1f9Zk9P9L0kM0X0Z0Z0Z0Z0', 'chefe'),
('Mário Mecânico', 'mecanico@autofix.pt', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1eLmy2L1f9Zk9P9L0kM0X0Z0Z0Z0Z0', 'mecanico'),
('Ana Silva', 'ana.silva@email.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1eLmy2L1f9Zk9P9L0kM0X0Z0Z0Z0Z0', 'cliente');

INSERT INTO clientes (nome, email, telefone, nif) VALUES
('Ana Silva', 'ana.silva@email.com', '912345678', '123456789'),
('João Pereira', 'joao.pereira@email.com', '967891234', '987654321');

INSERT INTO veiculos (cliente_id, tipo, marca, modelo, matricula) VALUES
(1, 'carro', 'Volkswagen', 'Golf VII', 'AA-00-AA'),
(1, 'moto', 'Honda', 'CB500F', '00-BB-11'),
(2, 'carro', 'BMW', '320d', 'CC-22-33');

INSERT INTO reparacoes (veiculo_id, descricao, km, valor, estado, data_inicio, data_fim) VALUES
(1, 'Troca de pastilhas de travão e calços frontais', 115000, 180.00, 'Concluído', '2026-10-10 09:00:00', '2026-10-10 16:30:00'),
(2, 'Mudança de óleo e filtro', 12000, 75.00, 'Em Andamento', '2026-10-28 10:00:00', NULL);

INSERT INTO agendamentos (cliente_id, veiculo_id, servico, data_agendamento, estado, observacoes) VALUES
(1, 1, 'Revisão Geral', '2026-11-02 10:00:00', 'Pendente', 'Fazer verificação do líquido de refrigeração');
