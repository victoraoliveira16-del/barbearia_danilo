<?php
/**
 * Painel Central de Auditoria, Credenciais e Planos - Barbearia Danilo
 * Permite auditar, testar e redefinir o acesso de todas as contas chave do sistema
 * Com detecção inteligente e auto-correção de tabelas faltantes no banco de dados.
 */

include('conexao.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mensagem_sucesso = "";
$mensagem_erro = "";

// 1. Verificar de forma segura quais tabelas cruciais existem no DB
$tabelas_existentes = [];
try {
    $res_tables = $conn->query("SHOW TABLES");
    if ($res_tables) {
        while ($row = $res_tables->fetch_array()) {
            $tabelas_existentes[] = strtolower($row[0]);
        }
    }
} catch (Exception $e) {
    $mensagem_erro = "🚨 Erro ao ler tabelas do banco de dados: " . $e->getMessage();
}

$usuarios_existe = in_array('usuarios', $tabelas_existentes);
$assinaturas_existe = in_array('assinaturas', $tabelas_existentes);
$agendamentos_existe = in_array('agendamentos', $tabelas_existentes);

// 2. Ação POST: Criar e ajustar tabelas faltantes no banco de dados
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao_criar_tabelas'])) {
    try {
        // Criar tabela de Usuários se não existir
        $conn->query("
            CREATE TABLE IF NOT EXISTS `usuarios` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nome` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) NOT NULL UNIQUE,
                `senha` VARCHAR(255) NOT NULL,
                `tipo` ENUM('admin', 'barbeiro', 'cliente') DEFAULT 'cliente',
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Criar tabela de Agendamentos se não existir (chave estrangeira para usuarios)
        $conn->query("
            CREATE TABLE IF NOT EXISTS `agendamentos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `usuario_id` INT NOT NULL,
                `nome` VARCHAR(100) NOT NULL,
                `telefone` VARCHAR(20) NOT NULL,
                `servico` VARCHAR(100) NOT NULL,
                `valor` DECIMAL(10,2) NOT NULL,
                `data_hora` DATETIME NOT NULL,
                `status` ENUM('pendente', 'confirmado', 'cancelado', 'concluido') DEFAULT 'pendente',
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Criar tabela de Assinaturas se não existir (chave estrangeira para usuarios)
        $conn->query("
            CREATE TABLE IF NOT EXISTS `assinaturas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `usuario_id` INT NOT NULL,
                `nome_cliente` VARCHAR(100) NOT NULL,
                `plano` VARCHAR(50) NOT NULL,
                `preco` VARCHAR(50) NOT NULL,
                `metodo_pagamento` VARCHAR(50) NOT NULL,
                `data_assinatura` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $mensagem_sucesso = "🎉 Estrutura de tabelas (incluindo <strong>assinaturas</strong>) configurada com sucesso no banco de dados!";
        
        // Atualizar as variáveis de existência das tabelas
        $usuarios_existe = true;
        $assinaturas_existe = true;
        $agendamentos_existe = true;
    } catch (Exception $e) {
        $mensagem_erro = "❌ Falha ao criar estrutura de tabelas: " . $e->getMessage();
    }
}

// Contas padrão de teste
$contas_alvo = [
    'admin' => [
        'email' => 'barbeiro@danilo.com',
        'nome_padrao' => 'Barbeiro Danilo (Admin)',
        'senha_padrao' => 'adminbarber',
        'tipo' => 'admin',
        'titulo' => '👑 Conta Administrador (Barbeiro)',
        'plano_padrao' => null
    ],
    'vip' => [
        'email' => 'vip@danilo.com',
        'nome_padrao' => 'Cliente VIP Danilo',
        'senha_padrao' => 'vipstyle',
        'tipo' => 'cliente',
        'titulo' => '✨ Cliente VIP Style',
        'plano_padrao' => [
            'plano' => 'Plano VIP Style',
            'preco' => 'R$ 129/mês',
            'metodo' => 'Pix Instantâneo'
        ]
    ],
    'cavalheiro' => [
        'email' => 'cavalheiro@danilo.com',
        'nome_padrao' => 'Cliente Cavalheiro Danilo',
        'senha_padrao' => 'cavalheiro',
        'tipo' => 'cliente',
        'titulo' => '💼 Cliente Cavalheiro',
        'plano_padrao' => [
            'plano' => 'Plano Cavalheiro',
            'preco' => 'R$ 69/mês',
            'metodo' => 'Cartão de Crédito'
        ]
    ],
    'lenhador' => [
        'email' => 'lenhador@danilo.com',
        'nome_padrao' => 'Cliente Lenhador Danilo',
        'senha_padrao' => 'lenhador',
        'tipo' => 'cliente',
        'titulo' => '🪓 Cliente Lenhador',
        'plano_padrao' => [
            'plano' => 'Plano Lenhador',
            'preco' => 'R$ 59/mês',
            'metodo' => 'Cartão de Crédito'
        ]
    ]
];

// Ações POST de Contas
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['acao_criar_tabelas'])) {
    if (!$usuarios_existe) {
        $mensagem_erro = "⚠️ Crie a estrutura de tabelas primeiro antes de gerenciar os usuários!";
    } else {
        // 1. Ação: Resetar para senha e plano padrão
        if (isset($_POST['acao_reset_padrao'])) {
            $chave = $_POST['chave_conta'];
            if (isset($contas_alvo[$chave])) {
                $conta = $contas_alvo[$chave];
                $senha_hash = password_hash($conta['senha_padrao'], PASSWORD_BCRYPT);
                
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmt->bind_param("s", $conta['email']);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    
                    $usuario_id = null;
                    if ($res->num_rows > 0) {
                        $row = $res->fetch_assoc();
                        $usuario_id = $row['id'];
                        $update = $conn->prepare("UPDATE usuarios SET senha = ?, tipo = ?, nome = ? WHERE id = ?");
                        $update->bind_param("sssi", $senha_hash, $conta['tipo'], $conta['nome_padrao'], $usuario_id);
                        $update->execute();
                    } else {
                        $insert = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
                        $insert->bind_param("ssss", $conta['nome_padrao'], $conta['email'], $senha_hash, $conta['tipo']);
                        $insert->execute();
                        $usuario_id = $insert->insert_id;
                    }
                    
                    if ($assinaturas_existe) {
                        if ($conta['plano_padrao'] !== null) {
                            $del_ass = $conn->prepare("DELETE FROM assinaturas WHERE usuario_id = ?");
                            $del_ass->bind_param("i", $usuario_id);
                            $del_ass->execute();
                            
                            $ins_ass = $conn->prepare("INSERT INTO assinaturas (usuario_id, nome_cliente, plano, preco, metodo_pagamento) VALUES (?, ?, ?, ?, ?)");
                            $ins_ass->bind_param("issss", $usuario_id, $conta['nome_padrao'], $conta['plano_padrao']['plano'], $conta['plano_padrao']['preco'], $conta['plano_padrao']['metodo']);
                            $ins_ass->execute();
                        } else {
                            $del_ass = $conn->prepare("DELETE FROM assinaturas WHERE usuario_id = ?");
                            $del_ass->bind_param("i", $usuario_id);
                            $del_ass->execute();
                        }
                    }
                    
                    $conn->commit();
                    $mensagem_sucesso = "🎉 Conta <strong>{$conta['email']}</strong> reconfigurada com sucesso! Senha padrão definida para: <code>{$conta['senha_padrao']}</code>";
                } catch (Exception $e) {
                    $conn->rollback();
                    $mensagem_erro = "❌ Ocorreu um erro ao restaurar a conta: " . $e->getMessage();
                }
            }
        }
        
        // 2. Ação: Definir senha personalizada
        if (isset($_POST['acao_senha_personalizada'])) {
            $chave = $_POST['chave_conta'];
            $nova_senha = $_POST['nova_senha'];
            
            if (isset($contas_alvo[$chave])) {
                $conta = $contas_alvo[$chave];
                if (strlen($nova_senha) < 6) {
                    $mensagem_erro = "⚠️ A senha deve ter no mínimo 6 caracteres.";
                } else {
                    $senha_hash = password_hash($nova_senha, PASSWORD_BCRYPT);
                    
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
                        $stmt->bind_param("s", $conta['email']);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        
                        if ($res->num_rows > 0) {
                            $row = $res->fetch_assoc();
                            $usuario_id = $row['id'];
                            
                            $update = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                            $update->bind_param("si", $senha_hash, $usuario_id);
                            $update->execute();
                        } else {
                            $insert = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
                            $insert->bind_param("ssss", $conta['nome_padrao'], $conta['email'], $senha_hash, $conta['tipo']);
                            $insert->execute();
                            $usuario_id = $insert->insert_id;
                            
                            if ($assinaturas_existe && $conta['plano_padrao'] !== null) {
                                $ins_ass = $conn->prepare("INSERT INTO assinaturas (usuario_id, nome_cliente, plano, preco, metodo_pagamento) VALUES (?, ?, ?, ?, ?)");
                                $ins_ass->bind_param("issss", $usuario_id, $conta['nome_padrao'], $conta['plano_padrao']['plano'], $conta['plano_padrao']['preco'], $conta['plano_padrao']['metodo']);
                                $ins_ass->execute();
                            }
                        }
                        $conn->commit();
                        $mensagem_sucesso = "🔒 Nova senha gravada com sucesso para <strong>{$conta['email']}</strong>!";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $mensagem_erro = "❌ Falha ao gravar nova senha: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Consultar o status de todas as contas no banco de dados de forma tolerante (sem quebrar se assinaturas ou usuarios não existirem)
$dados_render = [];
foreach ($contas_alvo as $chave => $info) {
    $item = [
        'chave' => $chave,
        'email' => $info['email'],
        'senha_padrao' => $info['senha_padrao'],
        'tipo_padrao' => $info['tipo'],
        'titulo' => $info['titulo'],
        'plano_padrao' => $info['plano_padrao'],
        'existe' => false,
        'id' => null,
        'nome' => null,
        'tipo' => null,
        'senha_hash' => null,
        'senha_padrao_valida' => false,
        'plano_atual' => null,
        'preco_atual' => null,
        'pagamento_atual' => null
    ];
    
    if ($usuarios_existe) {
        try {
            // Se a tabela de assinaturas existir, faz a query combinada completa
            if ($assinaturas_existe) {
                $q = $conn->prepare("
                    SELECT u.id, u.nome, u.email, u.senha, u.tipo, a.plano, a.preco, a.metodo_pagamento 
                    FROM usuarios u 
                    LEFT JOIN assinaturas a ON u.id = a.usuario_id 
                    WHERE u.email = ?
                ");
            } else {
                // Caso contrário, busca somente dados básicos dos usuários
                $q = $conn->prepare("
                    SELECT id, nome, email, senha, tipo 
                    FROM usuarios 
                    WHERE email = ?
                ");
            }
            
            $q->bind_param("s", $info['email']);
            $q->execute();
            $res = $q->get_result();
            
            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $item['existe'] = true;
                $item['id'] = $row['id'];
                $item['nome'] = $row['nome'];
                $item['tipo'] = $row['tipo'];
                $item['senha_hash'] = $row['senha'];
                $item['plano_atual'] = isset($row['plano']) ? $row['plano'] : null;
                $item['preco_atual'] = isset($row['preco']) ? $row['preco'] : null;
                $item['pagamento_atual'] = isset($row['metodo_pagamento']) ? $row['metodo_pagamento'] : null;
                
                $item['senha_padrao_valida'] = password_verify($info['senha_padrao'], $row['senha']);
            }
        } catch (Exception $e) {
            $mensagem_erro = "🚨 Erro ao consultar dados de " . $info['email'] . ": " . $e->getMessage();
        }
    }
    
    $dados_render[$chave] = $item;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Contas e Planos - Barbearia Danilo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-principal: #0a0b0d;
            --bg-card: #13151b;
            --bg-card-hover: #1b1e27;
            --cor-ouro: #cca43b;
            --cor-ouro-claro: #e5c060;
            --cor-texto: #f3f4f6;
            --cor-texto-mutado: #9ca3af;
            --cor-sucesso: #10b981;
            --cor-erro: #ef4444;
            --cor-alerta: #cca43b;
            --borda: rgba(204, 164, 59, 0.12);
            --transicao: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-principal);
            color: var(--cor-texto);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 20px;
            background-image: 
                radial-gradient(circle at 5% 15%, rgba(204, 164, 59, 0.04) 0%, transparent 40%),
                radial-gradient(circle at 95% 85%, rgba(204, 164, 59, 0.04) 0%, transparent 40%);
            background-attachment: fixed;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            animation: fadeIn 0.7s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            text-align: center;
            margin-bottom: 45px;
        }

        .logo-area {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--cor-ouro);
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: inline-block;
            position: relative;
        }

        .logo-area::after {
            content: '';
            display: block;
            width: 60%;
            height: 2px;
            background: var(--cor-ouro);
            margin: 8px auto 0 auto;
            border-radius: 2px;
        }

        .subtitle {
            color: var(--cor-texto-mutado);
            font-size: 1.1rem;
            font-weight: 300;
        }

        .alerta {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-size: 0.95rem;
            text-align: center;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alerta.sucesso {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: var(--cor-sucesso);
        }

        .alerta.erro {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: var(--cor-erro);
        }

        /* Card de Correção do Banco de Dados */
        .db-warning-card {
            background: rgba(204, 164, 59, 0.04);
            border: 1px solid rgba(204, 164, 59, 0.3);
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .db-warning-text {
            flex: 1;
            min-width: 280px;
        }

        .db-warning-text h3 {
            color: var(--cor-ouro-claro);
            font-size: 1.15rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .db-warning-text p {
            color: var(--cor-texto-mutado);
            font-size: 0.9rem;
        }

        /* Grid de Contas */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--borda);
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: var(--transicao);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.05);
            transition: var(--transicao);
        }

        .card:hover::before {
            background: var(--cor-ouro);
        }

        .card:hover {
            transform: translateY(-5px);
            border-color: rgba(204, 164, 59, 0.3);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            background: var(--bg-card-hover);
        }

        .card-header {
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--cor-ouro-claro);
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            padding-bottom: 10px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 130px 1fr;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.02);
            font-size: 0.9rem;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--cor-texto-mutado);
            font-weight: 500;
        }

        .info-val {
            font-weight: 600;
            color: var(--cor-texto);
            word-break: break-all;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.sucesso {
            background: rgba(16, 185, 129, 0.15);
            color: var(--cor-sucesso);
            border: 1px solid var(--cor-sucesso);
        }

        .badge.erro {
            background: rgba(239, 68, 68, 0.15);
            color: var(--cor-erro);
            border: 1px solid var(--cor-erro);
        }

        .badge.ouro {
            background: rgba(204, 164, 59, 0.15);
            color: var(--cor-ouro-claro);
            border: 1px solid var(--cor-ouro);
        }

        /* Seção Planos */
        .plano-box {
            background: rgba(204, 164, 59, 0.03);
            border: 1px dashed var(--borda);
            border-radius: 10px;
            padding: 12px;
            margin-top: 15px;
        }

        .plano-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--cor-ouro);
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        /* Ações e Formulários */
        .card-actions {
            margin-top: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 20px;
        }

        .form-custom-pass {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .input-group {
            display: flex;
            gap: 8px;
        }

        .input-text {
            flex: 1;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--cor-texto);
            font-family: inherit;
            font-size: 0.88rem;
            outline: none;
            transition: var(--transicao);
        }

        .input-text:focus {
            border-color: var(--cor-ouro);
            box-shadow: 0 0 0 3px rgba(204, 164, 59, 0.15);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transicao);
            border: none;
            text-decoration: none;
            gap: 6px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--cor-ouro) 0%, #a8832a 100%);
            color: #0d0e12;
            box-shadow: 0 4px 12px rgba(204, 164, 59, 0.15);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--cor-ouro-claro) 0%, var(--cor-ouro) 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(204, 164, 59, 0.25);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.04);
            color: var(--cor-texto);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(230, 126, 34, 0.2);
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(230, 126, 34, 0.35);
        }

        .btn-tertiary {
            width: 100%;
            background: transparent;
            color: var(--cor-ouro-claro);
            border: 1px solid var(--borda);
            margin-top: 15px;
            padding: 12px;
        }

        .btn-tertiary:hover {
            background: rgba(204, 164, 59, 0.04);
            border-color: var(--cor-ouro);
        }

        .footer-note {
            text-align: center;
            margin-top: 50px;
            font-size: 0.82rem;
            color: var(--cor-texto-mutado);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 25px;
        }

        .footer-note code {
            background: rgba(255, 255, 255, 0.05);
            padding: 2px 5px;
            border-radius: 4px;
            color: var(--cor-ouro-claro);
        }

        .footer-note a {
            color: var(--cor-ouro);
            text-decoration: none;
            transition: var(--transicao);
        }

        .footer-note a:hover {
            text-decoration: underline;
            color: var(--cor-ouro-claro);
        }

        /* Micro animações */
        .info-row:hover .info-label {
            color: var(--cor-ouro-claro);
            transition: var(--transicao);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="logo-area">Barbearia Danilo</div>
        <div class="subtitle">Central de Contas Chave, Planos e Credenciais</div>
    </div>

    <!-- Mensagens de Feedback -->
    <?php if (!empty($mensagem_sucesso)): ?>
        <div class="alerta sucesso">
            <span><?php echo $mensagem_sucesso; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($mensagem_erro)): ?>
        <div class="alerta erro">
            <span><?php echo $mensagem_erro; ?></span>
        </div>
    <?php endif; ?>

    <!-- CARD AUTO-CORRETOR DE BANCO DE DADOS (Se alguma tabela estiver faltando) -->
    <?php if (!$usuarios_existe || !$assinaturas_existe || !$agendamentos_existe): ?>
        <div class="db-warning-card">
            <div class="db-warning-text">
                <h3>⚠️ Estrutura do Banco de Dados Incompleta!</h3>
                <p>
                    Detectamos que algumas tabelas essenciais para o sistema não existem no seu banco de dados:
                    <?php
                    $faltando = [];
                    if (!$usuarios_existe) $faltando[] = "<strong>usuarios</strong>";
                    if (!$assinaturas_existe) $faltando[] = "<strong>assinaturas</strong> (Planos)";
                    if (!$agendamentos_existe) $faltando[] = "<strong>agendamentos</strong>";
                    echo implode(', ', $faltando);
                    ?>.
                    Você pode criá-las automaticamente agora clicando no botão ao lado.
                </p>
            </div>
            <form method="POST">
                <input type="hidden" name="acao_criar_tabelas" value="1">
                <button type="submit" class="btn btn-warning">🛠️ Criar Tabelas Faltantes</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Grid de Contas -->
    <div class="cards-grid">
        <?php foreach ($dados_render as $chave => $conta): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><?php echo $conta['titulo']; ?></div>
                    
                    <div class="info-row">
                        <div class="info-label">E-mail:</div>
                        <div class="info-val"><?php echo $conta['email']; ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Status no DB:</div>
                        <div class="info-val">
                            <?php if ($conta['existe']): ?>
                                <span class="badge sucesso">Configurada</span>
                            <?php else: ?>
                                <span class="badge erro">Ausente no DB</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($conta['existe']): ?>
                        <div class="info-row">
                            <div class="info-label">Nome Registrado:</div>
                            <div class="info-val"><?php echo htmlspecialchars($conta['nome']); ?></div>
                        </div>

                        <div class="info-row">
                            <div class="info-label">Permissão:</div>
                            <div class="info-val">
                                <span class="badge ouro"><?php echo htmlspecialchars($conta['tipo']); ?></span>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-label">Senha Padrão:</div>
                            <div class="info-val">
                                <code><?php echo $conta['senha_padrao']; ?></code>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-label">Teste Senha Padrão:</div>
                            <div class="info-val">
                                <?php if ($conta['senha_padrao_valida']): ?>
                                    <span class="badge sucesso">Válida</span>
                                <?php else: ?>
                                    <span class="badge erro">Inválida / Alterada</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Renderizar informações de Planos / Assinaturas se houver -->
                        <?php if ($assinaturas_existe && $conta['plano_atual'] !== null): ?>
                            <div class="plano-box">
                                <div class="plano-title">💎 Plano de Assinatura Ativo</div>
                                <div style="font-size: 0.85rem; display: flex; flex-direction: column; gap: 4px;">
                                    <div><strong>Nome do Plano:</strong> <span style="color:var(--cor-ouro-claro); font-weight:600;"><?php echo htmlspecialchars($conta['plano_atual']); ?></span></div>
                                    <div><strong>Preço:</strong> <?php echo htmlspecialchars($conta['preco_atual']); ?></div>
                                    <div><strong>Pagamento:</strong> <?php echo htmlspecialchars($conta['pagamento_atual']); ?></div>
                                </div>
                            </div>
                        <?php elseif ($conta['plano_padrao'] !== null): ?>
                            <div class="plano-box" style="border-color: rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.02);">
                                <div class="plano-title" style="color: var(--cor-erro);">
                                    <?php echo !$assinaturas_existe ? "⏳ Tabela Planos Faltando" : "⚠️ Sem Plano Associado"; ?>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--cor-texto-mutado);">
                                    <?php echo !$assinaturas_existe 
                                        ? "A tabela de assinaturas não existe no DB. Crie-a no botão superior." 
                                        : "Este usuário deveria ter o plano <strong>{$conta['plano_padrao']['plano']}</strong> associado. Clique no botão de reset para restaurar."; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Se a conta não existe -->
                        <div style="background: rgba(239, 68, 68, 0.05); padding: 12px; border-radius: 10px; border: 1px solid rgba(239, 68, 68, 0.15); margin-top: 15px; font-size: 0.85rem; color: var(--cor-texto-mutado);">
                            Esta conta padrão não foi encontrada no banco de dados. Utilize o botão abaixo para criá-la com o nome, senha e plano corretos de teste.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-actions">
                    <!-- Restaurar / Criar com padrões -->
                    <form method="POST">
                        <input type="hidden" name="acao_reset_padrao" value="1">
                        <input type="hidden" name="chave_conta" value="<?php echo $conta['chave']; ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%;" <?php echo !$usuarios_existe ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>
                            <?php echo $conta['existe'] ? "🔄 Restaurar Padrões & Senha" : "✨ Criar Conta Padrão"; ?>
                        </button>
                    </form>

                    <!-- Definir senha personalizada -->
                    <form method="POST" class="form-custom-pass">
                        <input type="hidden" name="acao_senha_personalizada" value="1">
                        <input type="hidden" name="chave_conta" value="<?php echo $conta['chave']; ?>">
                        <div class="input-group">
                            <input type="password" name="nova_senha" required placeholder="Nova senha" class="input-text" <?php echo !$usuarios_existe ? 'disabled' : ''; ?>>
                            <button type="submit" class="btn btn-secondary" <?php echo !$usuarios_existe ? 'disabled' : ''; ?>>Gravar</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Voltar ao login do sistema -->
    <a href="login.php" class="btn btn-tertiary">
        ← Ir para a Página de Login da Barbearia Danilo
    </a>

    <div class="footer-note">
        🛡️ Central de Auditoria do Administrador. <br>
        Recomendamos deletar ou renomear o arquivo <code>conferir_barbeiro.php</code> após a finalização dos seus testes.
    </div>
</div>

</body>
</html>
