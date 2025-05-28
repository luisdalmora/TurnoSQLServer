-- SQL Server Initialization Script for SimPostoDB
-- Use este script para criar a estrutura inicial do banco de dados.
-- Execute os lotes (comandos separados por GO) individualmente se necessário.

-- Verifica se o banco de dados SimPostoDB existe; se não, cria.
-- Esta parte é geralmente feita ao configurar a conexão ou manualmente.
-- IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = N'SimPostoDB')
-- BEGIN
--     CREATE DATABASE SimPostoDB;
-- END
-- GO
-- USE SimPostoDB; -- Certifique-se de estar no contexto do banco de dados correto ao executar o restante.
-- GO

PRINT 'Iniciando a criação/verificação das tabelas para SimPostoDB...';
GO

-- Tabela de Usuários
IF OBJECT_ID('dbo.usuarios', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.usuarios (
        id INT PRIMARY KEY IDENTITY(1,1),
        nome_completo VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        usuario VARCHAR(100) UNIQUE NOT NULL,
        senha VARCHAR(255) NOT NULL, -- Armazena o hash da senha
        ativo BIT DEFAULT 1 NOT NULL,
        role VARCHAR(50) DEFAULT 'user' NOT NULL, -- Ex: 'admin', 'user'
        reset_token VARCHAR(255) NULL,
        reset_token_expires_at DATETIME2 NULL,
        data_criacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        data_modificacao DATETIME2 DEFAULT GETDATE() NOT NULL
    );
    PRINT 'Tabela "usuarios" criada.';
END
ELSE
BEGIN
    PRINT 'Tabela "usuarios" já existe.';
END
GO

-- Tabela de Colaboradores
IF OBJECT_ID('dbo.colaboradores', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.colaboradores (
        id INT PRIMARY KEY IDENTITY(1,1),
        nome_completo VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL, -- Pode ser UNIQUE se desejado e obrigatório
        cargo VARCHAR(100) NULL,
        ativo BIT DEFAULT 1 NOT NULL,
        criado_por_usuario_id INT NULL,
        data_criacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        data_modificacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        CONSTRAINT FK_colaboradores_criado_por FOREIGN KEY (criado_por_usuario_id) REFERENCES dbo.usuarios(id) ON DELETE SET NULL
    );
    PRINT 'Tabela "colaboradores" criada.';
END
ELSE
BEGIN
    PRINT 'Tabela "colaboradores" já existe.';
END
GO

-- Tabela de Turnos
-- Nota: O campo 'colaborador' é mantido como VARCHAR para compatibilidade com a estrutura atual.
-- Idealmente, seria 'colaborador_id INT' com uma chave estrangeira para 'colaboradores.id'.
IF OBJECT_ID('dbo.turnos', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.turnos (
        id INT PRIMARY KEY IDENTITY(1,1),
        data DATE NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fim TIME NOT NULL,
        colaborador VARCHAR(255) NOT NULL, 
        observacao TEXT NULL,
        criado_por_usuario_id INT NULL,
        data_criacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        data_modificacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        CONSTRAINT FK_turnos_criado_por FOREIGN KEY (criado_por_usuario_id) REFERENCES dbo.usuarios(id) ON DELETE SET NULL
    );
    PRINT 'Tabela "turnos" criada.';
END
ELSE
BEGIN
    PRINT 'Tabela "turnos" já existe.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'idx_turnos_data_colaborador' AND object_id = OBJECT_ID('dbo.turnos'))
BEGIN
    CREATE INDEX idx_turnos_data_colaborador ON dbo.turnos(data, colaborador);
    PRINT 'Índice "idx_turnos_data_colaborador" criado para a tabela "turnos".';
END
GO

-- Tabela de Ausências
-- Nota: O campo 'colaborador' é mantido como VARCHAR. Idealmente, 'colaborador_id INT FK'.
IF OBJECT_ID('dbo.ausencias', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ausencias (
        id INT PRIMARY KEY IDENTITY(1,1),
        data_inicio DATE NOT NULL,
        data_fim DATE NOT NULL,
        colaborador VARCHAR(255) NOT NULL,
        motivo TEXT NULL,
        criado_por_usuario_id INT NULL,
        data_criacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        data_modificacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        CONSTRAINT FK_ausencias_criado_por FOREIGN KEY (criado_por_usuario_id) REFERENCES dbo.usuarios(id) ON DELETE SET NULL
    );
    PRINT 'Tabela "ausencias" criada.';
END
ELSE
BEGIN
    PRINT 'Tabela "ausencias" já existe.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'idx_ausencias_datas_colaborador' AND object_id = OBJECT_ID('dbo.ausencias'))
BEGIN
    CREATE INDEX idx_ausencias_datas_colaborador ON dbo.ausencias(data_inicio, data_fim, colaborador);
    PRINT 'Índice "idx_ausencias_datas_colaborador" criado para a tabela "ausencias".';
END
GO

-- Tabela de Observações Gerais
IF OBJECT_ID('dbo.observacoes_gerais', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.observacoes_gerais (
        id INT PRIMARY KEY IDENTITY(1,1),
        mes_ano VARCHAR(7) NOT NULL UNIQUE, -- Formato 'YYYY-MM'
        observacao TEXT NULL,
        criado_por_usuario_id INT NULL,
        data_criacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        data_modificacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        CONSTRAINT FK_observacoes_gerais_criado_por FOREIGN KEY (criado_por_usuario_id) REFERENCES dbo.usuarios(id) ON DELETE SET NULL
    );
    PRINT 'Tabela "observacoes_gerais" criada.';
END
ELSE
BEGIN
    PRINT 'Tabela "observacoes_gerais" já existe.';
END
GO

-- Tabela de Scripts Armazenados
IF OBJECT_ID('dbo.scripts_armazenados', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.scripts_armazenados (
        id INT PRIMARY KEY IDENTITY(1,1),
        titulo VARCHAR(255) NOT NULL,
        conteudo TEXT NOT NULL,
        criado_por_usuario_id INT NULL,
        data_criacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        data_modificacao DATETIME2 DEFAULT GETDATE() NOT NULL,
        CONSTRAINT FK_scripts_armazenados_criado_por FOREIGN KEY (criado_por_usuario_id) REFERENCES dbo.usuarios(id) ON DELETE SET NULL
    );
    PRINT 'Tabela "scripts_armazenados" criada.';
END
ELSE
BEGIN
    PRINT 'Tabela "scripts_armazenados" já existe.';
END
GO

-- Tabela de Log do Sistema
IF OBJECT_ID('dbo.log_sistema', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.log_sistema (
        id BIGINT PRIMARY KEY IDENTITY(1,1),
        timestamp DATETIME2 DEFAULT GETDATE() NOT NULL,
        level VARCHAR(20) NOT NULL, 
        message TEXT NOT NULL,
        context TEXT NULL, -- Pode armazenar JSON com dados adicionais
        usuario_id INT NULL, 
        CONSTRAINT FK_log_sistema_usuario FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios(id) ON DELETE SET NULL
    );
    PRINT 'Tabela "log_sistema" criada.';
END
ELSE
BEGIN
    PRINT 'Tabela "log_sistema" já existe.';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'idx_log_timestamp_level' AND object_id = OBJECT_ID('dbo.log_sistema'))
BEGIN
    CREATE INDEX idx_log_timestamp_level ON dbo.log_sistema(timestamp DESC, level);
    PRINT 'Índice "idx_log_timestamp_level" criado para a tabela "log_sistema".';
END
GO

-- Inserts Iniciais (Exemplo: Usuário Admin)
-- O sistema de cadastro (cadastrar.php) já tem lógica para criar um usuário admin se não existir um.
-- Se preferir ter um admin criado explicitamente pelo script SQL, descomente e ajuste o INSERT abaixo.
-- Lembre-se que a senha deve ser um HASH gerado pela função password_hash() do PHP.
-- Exemplo (NÃO USE ESTA SENHA/HASH EM PRODUÇÃO - gere um novo):
-- Senha: 'admin123' -> Hash: '$2y$10$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' (substitua pelo seu hash)

-- IF NOT EXISTS (SELECT 1 FROM dbo.usuarios WHERE usuario = 'admin')
-- BEGIN
--     INSERT INTO dbo.usuarios (nome_completo, email, usuario, senha, ativo, role)
--     VALUES ('Administrador Padrão', 'admin@example.com', 'admin', '$2y$10$SEU_HASH_SEGURO_GERADO_PELO_PHP_AQUI', 1, 'admin');
--     PRINT 'Usuário "admin" padrão inserido (se não existia). ATENÇÃO: Configure uma senha forte e um hash correto!';
-- END
-- ELSE
-- BEGIN
--     PRINT 'Usuário "admin" já existe ou outro usuário com esse login foi encontrado.';
-- END
-- GO

PRINT 'Script de inicialização do banco de dados SimPostoDB concluído.';
GO