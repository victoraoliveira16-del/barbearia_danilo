<?php
header('Content-Type: text/html; charset=utf-8');
include('conexao.php');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$erro = "";
$sucesso = false;
$msg_status = "";

$email_vip = "vip@danilo.com";
$senha_raw = "vipstyle";
$nome_vip = "Cliente VIP Danilo";
$plano_nome = "Plano VIP Style";
$plano_preco = "R$ 129/mês";
$metodo_pagto = "Pix Instantâneo";

try {
    // 1. Verificar se o usuário já existe
    $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt_check->bind_param("s", $email_vip);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    
    if ($res_check->num_rows > 0) {
        // Usuário já existe, obter ID
        $user_id = $res_check->fetch_assoc()['id'];
        $msg_status = "A conta <code>$email_vip</code> já existia e foi identificada.";
    } else {
        // Criar novo usuário
        $senha_hash = password_hash($senha_raw, PASSWORD_BCRYPT);
        $tipo = "cliente";
        
        $stmt_insert = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("ssss", $nome_vip, $email_vip, $senha_hash, $tipo);
        
        if ($stmt_insert->execute()) {
            $user_id = $stmt_insert->insert_id;
            $msg_status = "Nova conta criada com sucesso!";
        } else {
            throw new Exception("Erro ao inserir novo usuário: " . $conn->error);
        }
        $stmt_insert->close();
    }
    $stmt_check->close();

    // 2. Verificar se já possui a assinatura ativa para esse plano
    $stmt_ass_check = $conn->prepare("SELECT id FROM assinaturas WHERE usuario_id = ? AND plano = ?");
    $stmt_ass_check->bind_param("is", $user_id, $plano_nome);
    $stmt_ass_check->execute();
    $res_ass_check = $stmt_ass_check->get_result();

    if ($res_ass_check->num_rows == 0) {
        // Criar a assinatura no banco de dados
        $stmt_ass = $conn->prepare("INSERT INTO assinaturas (usuario_id, nome_cliente, plano, preco, metodo_pagamento) VALUES (?, ?, ?, ?, ?)");
        $stmt_ass->bind_param("issss", $user_id, $nome_vip, $plano_nome, $plano_preco, $metodo_pagto);
        
        if ($stmt_ass->execute()) {
            $msg_status .= " Plano VIP Style assinado com sucesso!";
        } else {
            throw new Exception("Erro ao assinar plano: " . $conn->error);
        }
        $stmt_ass->close();
    } else {
        $msg_status .= " O plano VIP Style já estava ativo nesta conta!";
    }
    $stmt_ass_check->close();
    
    $sucesso = true;

} catch (Exception $e) {
    $erro = $e->getMessage();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criador de Conta VIP - Barbearia Danilo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #121214;
            --card-bg: #1c1c1f;
            --primary: #cca43b;
            --primary-hover: #e5c060;
            --text: #f3f4f6;
            --text-muted: #9ca3af;
            --success: #10b981;
            --error: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 520px;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(204, 164, 59, 0.08) 0%, transparent 60%);
            z-index: 1;
            pointer-events: none;
        }

        .content {
            position: relative;
            z-index: 2;
        }

        .icon-box {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 25px auto;
            font-size: 2.5rem;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .icon-box.success {
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid var(--success);
            color: var(--success);
        }

        .icon-box.error {
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid var(--error);
            color: var(--error);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
        }

        h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .status-msg {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .status-msg code {
            background: rgba(255, 255, 255, 0.06);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--primary);
            font-family: monospace;
        }

        .credentials-card {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 35px;
            text-align: left;
        }

        .cred-item {
            margin-bottom: 15px;
        }

        .cred-item:last-child {
            margin-bottom: 0;
        }

        .cred-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 5px;
        }

        .cred-value {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .badge {
            background: rgba(204, 164, 59, 0.15);
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .btn {
            display: block;
            width: 100%;
            background: var(--primary);
            color: #0f0f0f;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(204, 164, 59, 0.2);
            margin-bottom: 15px;
            cursor: pointer;
        }

        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(204, 164, 59, 0.3);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text);
            box-shadow: none;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            box-shadow: none;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="content">
            <?php if ($sucesso): ?>
                <div class="icon-box success">✓</div>
                <h2>Conta VIP Pronta!</h2>
                <p class="status-msg"><?php echo $msg_status; ?></p>
                
                <div class="credentials-card">
                    <div class="cred-item">
                        <div class="cred-label">Nome Completo</div>
                        <div class="cred-value"><?php echo htmlspecialchars($nome_vip); ?></div>
                    </div>
                    <div class="cred-item">
                        <div class="cred-label">E-mail de Acesso</div>
                        <div class="cred-value"><?php echo htmlspecialchars($email_vip); ?></div>
                    </div>
                    <div class="cred-item">
                        <div class="cred-label">Senha Padrão</div>
                        <div class="cred-value" style="font-family: monospace; letter-spacing: 1px;"><?php echo htmlspecialchars($senha_raw); ?></div>
                    </div>
                    <div class="cred-item">
                        <div class="cred-label">Plano Ativo</div>
                        <div class="cred-value">
                            <?php echo htmlspecialchars($plano_nome); ?>
                            <span class="badge">Ativo (Ilimitado)</span>
                        </div>
                    </div>
                </div>

                <a href="login.php" class="btn">Fazer Login Agora</a>
                <a href="index.php" class="btn btn-secondary">Ir para a Home</a>
            <?php else: ?>
                <div class="icon-box error">✕</div>
                <h2>Falha na Operação</h2>
                <p class="status-msg">Não foi possível configurar a conta teste VIP.</p>
                <div class="credentials-card" style="border-color: var(--error); background: rgba(239, 68, 68, 0.03);">
                    <div class="cred-label" style="color: var(--error);">Erro Técnico</div>
                    <div style="color: var(--text); font-size: 0.9rem; line-height: 1.4;"><?php echo htmlspecialchars($erro); ?></div>
                </div>
                <a href="index.php" class="btn">Voltar para a Home</a>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
