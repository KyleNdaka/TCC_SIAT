CREATE DATABASE IF NOT EXISTS assistencia_tecnica;
USE assistencia_tecnica;

CREATE TABLE IF NOT EXISTS clientes (
    id_Cliente INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    email VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS aparelhos (
    id_Aparelho INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    marca VARCHAR(50),
    modelo VARCHAR(50),
    imei VARCHAR(30),
    defeito TEXT,
    CONSTRAINT fk_aparelhos_clientes FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ordens_servico (
    id_Servico INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    aparelho_id INT,
    status_os VARCHAR(50) DEFAULT 'Recebido',
    valor DECIMAL(10,2),
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ordens_clientes FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ordens_aparelhos FOREIGN KEY (aparelho_id)
        REFERENCES aparelhos(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS historico_status (
    id_Status INT AUTO_INCREMENT PRIMARY KEY,
    ordem_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    descricao TEXT,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historico_ordens FOREIGN KEY (ordem_id)
        REFERENCES ordens_servico(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pagamentos (
    id_Pagamento INT AUTO_INCREMENT PRIMARY KEY,
    ordem_id INT NOT NULL,
    cliente_id INT NOT NULL,
    valor_pago DECIMAL(10,2) NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    data_pagamento DATETIME DEFAULT CURRENT_TIMESTAMP,
    status_pagamento VARCHAR(30) DEFAULT 'Pago',
    observacao TEXT,
    CONSTRAINT fk_pagamentos_ordem FOREIGN KEY (ordem_id)
        REFERENCES ordens_servico(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pagamentos_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS despesas (
    id_Despesa INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_despesa DATE NOT NULL,
    observacao TEXT
);

CREATE TABLE IF NOT EXISTS usuarios (
    id_Usuario INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO usuarios (nome, email, senha)
SELECT 'Administrador', 'admin@assistencia.local', '$2y$10$P1RzjfeRTfwsR5rgpUzyCu4LzlWKa/Qd1amm8N4JcSZlDDCmqamFi'
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE email = 'admin@assistencia.local'
);
