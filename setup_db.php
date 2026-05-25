<?php
// Configurações de Conexão (vamos carregar os dados de conexao.php, mas fazer a conexão inicial sem definir o banco)
$host = "localhost";
$usuario = "root";
$senha = "mysql"; // padrão do seu arquivo

// Tentar se conectar com o MySQL sem especificar o banco de dados inicialmente
try {
    $conn = new mysqli($host, $usuario, $senha);
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
} catch (Exception $e) {
    // Se falhar com a senha "mysql", tenta com a senha vazia "" (padrão do XAMPP)
    try {
        $senha = "";
        $conn = new mysqli($host, $usuario, $senha);
        if ($conn->connect_error) {
            throw new Exception($conn->connect_error);
        }
    } catch (Exception $ex) {
        die("<h3>🚨 Erro ao conectar ao MySQL Server:</h3><p>" . $ex->getMessage() . "</p><p>Verifique se o XAMPP MySQL está ativo e se os dados de usuário/senha estão corretos.</p>");
    }
}

$conn->set_charset("utf8mb4");

echo "<h2>⚙️ Instalador Automático do Banco de Dados - Barbearia Danilo</h2>";
echo "<hr>";

// 1. Criar Banco de Dados
$sql_db = "CREATE DATABASE IF NOT EXISTS `barbearia_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql_db) === TRUE) {
    echo "✅ Banco de dados `barbearia_db` criado ou já existente.<br>";
} else {
    die("❌ Erro ao criar banco de dados: " . $conn->error);
}

// Selecionar o banco
$conn->select_db("barbearia_db");

// 2. Criar Tabela usuarios
$sql_table_users = "CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `tipo` ENUM('admin', 'barbeiro', 'cliente') DEFAULT 'cliente',
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_table_users) === TRUE) {
    echo "✅ Tabela `usuarios` configurada com sucesso.<br>";
} else {
    die("❌ Erro ao criar tabela usuarios: " . $conn->error);
}

// 3. Criar Tabela agendamentos
$sql_table_agendamentos = "CREATE TABLE IF NOT EXISTS `agendamentos` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_table_agendamentos) === TRUE) {
    echo "✅ Tabela `agendamentos` configurada com sucesso.<br>";
} else {
    die("❌ Erro ao criar tabela agendamentos: " . $conn->error);
}

// 3.5. Criar Tabela assinaturas
$sql_table_assinaturas = "CREATE TABLE IF NOT EXISTS `assinaturas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NOT NULL,
  `nome_cliente` VARCHAR(100) NOT NULL,
  `plano` VARCHAR(50) NOT NULL,
  `preco` VARCHAR(50) NOT NULL,
  `metodo_pagamento` VARCHAR(50) NOT NULL,
  `data_assinatura` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_table_assinaturas) === TRUE) {
    echo "✅ Tabela `assinaturas` configurada com sucesso.<br>";
} else {
    die("❌ Erro ao criar tabela assinaturas: " . $conn->error);
}

// 4. Inserir o Barbeiro (Admin) se não existir
$admin_email = "barbeiro@danilo.com";
$admin_check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$admin_check->bind_param("s", $admin_email);
$admin_check->execute();
$admin_result = $admin_check->get_result();

if ($admin_result->num_rows == 0) {
    $admin_nome = "Barbeiro Danilo (Admin)";
    $admin_senha_raw = "adminbarber";
    $admin_senha_hash = password_hash($admin_senha_raw, PASSWORD_BCRYPT);
    $admin_tipo = "admin";

    $admin_insert = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
    $admin_insert->bind_param("ssss", $admin_nome, $admin_email, $admin_senha_hash, $admin_tipo);
    if ($admin_insert->execute()) {
        echo "⭐️ <strong>Administrador Barbeiro criado com sucesso!</strong><br>";
        echo "🔹 <strong>E-mail de Login:</strong> <code style='background:#eee;padding:2px 5px;'>barbeiro@danilo.com</code><br>";
        echo "🔹 <strong>Senha de Acesso:</strong> <code style='background:#eee;padding:2px 5px;'>adminbarber</code><br>";
    } else {
        echo "❌ Erro ao criar administrador: " . $conn->error . "<br>";
    }
    $admin_insert->close();
} else {
    echo "ℹ️ Usuário administrador `barbeiro@danilo.com` já existe no banco.<br>";
}
$admin_check->close();

// 4.5. Inserir Clientes Teste de Planos se não existirem
// Conta VIP
$vip_email = "vip@danilo.com";
$vip_check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$vip_check->bind_param("s", $vip_email);
$vip_check->execute();
$vip_result = $vip_check->get_result();

if ($vip_result->num_rows == 0) {
    $vip_nome = "Cliente VIP Danilo";
    $vip_senha_raw = "vipstyle";
    $vip_senha_hash = password_hash($vip_senha_raw, PASSWORD_BCRYPT);
    $vip_tipo = "cliente";

    $vip_insert = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
    $vip_insert->bind_param("ssss", $vip_nome, $vip_email, $vip_senha_hash, $vip_tipo);
    if ($vip_insert->execute()) {
        $vip_id = $vip_insert->insert_id;
        echo "⭐️ <strong>Conta de Teste VIP criada com sucesso!</strong><br>";
        echo "🔹 <strong>E-mail:</strong> <code style='background:#eee;padding:2px 5px;'>vip@danilo.com</code> | <strong>Senha:</strong> <code style='background:#eee;padding:2px 5px;'>vipstyle</code><br>";
        
        // Assinar plano VIP
        $plano_nome = "Plano VIP Style";
        $plano_preco = "R$ 129/mês";
        $metodo_pagto = "Pix Instantâneo";
        $stmt_ass = $conn->prepare("INSERT INTO assinaturas (usuario_id, nome_cliente, plano, preco, metodo_pagamento) VALUES (?, ?, ?, ?, ?)");
        $stmt_ass->bind_param("issss", $vip_id, $vip_nome, $plano_nome, $plano_preco, $metodo_pagto);
        $stmt_ass->execute();
        $stmt_ass->close();
    }
    $vip_insert->close();
} else {
    echo "ℹ️ Usuário teste VIP `vip@danilo.com` já existe.<br>";
}
$vip_check->close();

// Conta Cavalheiro
$cav_email = "cavalheiro@danilo.com";
$cav_check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$cav_check->bind_param("s", $cav_email);
$cav_check->execute();
$cav_result = $cav_check->get_result();

if ($cav_result->num_rows == 0) {
    $cav_nome = "Cliente Cavalheiro Danilo";
    $cav_senha_raw = "cavalheiro";
    $cav_senha_hash = password_hash($cav_senha_raw, PASSWORD_BCRYPT);
    $cav_tipo = "cliente";

    $cav_insert = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
    $cav_insert->bind_param("ssss", $cav_nome, $cav_email, $cav_senha_hash, $cav_tipo);
    if ($cav_insert->execute()) {
        $cav_id = $cav_insert->insert_id;
        echo "⭐️ <strong>Conta de Teste Cavalheiro criada com sucesso!</strong><br>";
        echo "🔹 <strong>E-mail:</strong> <code style='background:#eee;padding:2px 5px;'>cavalheiro@danilo.com</code> | <strong>Senha:</strong> <code style='background:#eee;padding:2px 5px;'>cavalheiro</code><br>";
        
        // Assinar plano Cavalheiro
        $plano_nome = "Plano Cavalheiro";
        $plano_preco = "R$ 69/mês";
        $metodo_pagto = "Cartão de Crédito";
        $stmt_ass = $conn->prepare("INSERT INTO assinaturas (usuario_id, nome_cliente, plano, preco, metodo_pagamento) VALUES (?, ?, ?, ?, ?)");
        $stmt_ass->bind_param("issss", $cav_id, $cav_nome, $plano_nome, $plano_preco, $metodo_pagto);
        $stmt_ass->execute();
        $stmt_ass->close();
    }
    $cav_insert->close();
} else {
    echo "ℹ️ Usuário teste Cavalheiro `cavalheiro@danilo.com` já existe.<br>";
}
$cav_check->close();

// Conta Lenhador
$len_email = "lenhador@danilo.com";
$len_check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$len_check->bind_param("s", $len_email);
$len_check->execute();
$len_result = $len_check->get_result();

if ($len_result->num_rows == 0) {
    $len_nome = "Cliente Lenhador Danilo";
    $len_senha_raw = "lenhador";
    $len_senha_hash = password_hash($len_senha_raw, PASSWORD_BCRYPT);
    $len_tipo = "cliente";

    $len_insert = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
    $len_insert->bind_param("ssss", $len_nome, $len_email, $len_senha_hash, $len_tipo);
    if ($len_insert->execute()) {
        $len_id = $len_insert->insert_id;
        echo "⭐️ <strong>Conta de Teste Lenhador criada com sucesso!</strong><br>";
        echo "🔹 <strong>E-mail:</strong> <code style='background:#eee;padding:2px 5px;'>lenhador@danilo.com</code> | <strong>Senha:</strong> <code style='background:#eee;padding:2px 5px;'>lenhador</code><br>";
        
        // Assinar plano Lenhador
        $plano_nome = "Plano Lenhador";
        $plano_preco = "R$ 59/mês";
        $metodo_pagto = "Cartão de Crédito";
        $stmt_ass = $conn->prepare("INSERT INTO assinaturas (usuario_id, nome_cliente, plano, preco, metodo_pagamento) VALUES (?, ?, ?, ?, ?)");
        $stmt_ass->bind_param("issss", $len_id, $len_nome, $plano_nome, $plano_preco, $metodo_pagto);
        $stmt_ass->execute();
        $stmt_ass->close();
    }
    $len_insert->close();
} else {
    echo "ℹ️ Usuário teste Lenhador `lenhador@danilo.com` já existe.<br>";
}
$len_check->close();

// 5. Inserir dados fakes para teste do Dashboard se estiver vazio
$agendamentos_check = $conn->query("SELECT COUNT(*) as total FROM agendamentos");
$total_row = $agendamentos_check->fetch_assoc();


// 6. Atualizar a senha do conexao.php se necessário
// Se a conexão original com a senha "mysql" deu erro e funcionou com ""
if ($senha === "") {
    $conexao_content = file_get_contents("conexao.php");
    // Substituir $senha = "mysql"; por $senha = "";
    $new_content = preg_replace('/\$senha\s*=\s*["\']mysql["\']\s*;/', '$senha = "";', $conexao_content);
    if ($conexao_content !== $new_content) {
        file_put_contents("conexao.php", $new_content);
        echo "🔧 <strong>Conexão Atualizada:</strong> A senha de banco no arquivo `conexao.php` foi corrigida para vazia (`\"\"`) para corresponder à configuração do seu XAMPP local.<br>";
    }
}

echo "<hr>";
echo "<h3>🎉 Banco de dados configurado com total sucesso!</h3>";
echo "<p>Agora você já tem as seguintes contas de teste configuradas para uso imediato:</p>";
echo "<ul>";
echo "<li><strong>1. Barbeiro (Administrador):</strong><br>";
echo "🔹 E-mail: <code>barbeiro@danilo.com</code> | Senha: <code>adminbarber</code></li><br>";
echo "<li><strong>2. Cliente VIP Style (Ilimitado):</strong><br>";
echo "🔹 E-mail: <code>vip@danilo.com</code> | Senha: <code>vipstyle</code></li><br>";
echo "<li><strong>3. Cliente Cavalheiro (Limite de 2 cortes):</strong><br>";
echo "🔹 E-mail: <code>cavalheiro@danilo.com</code> | Senha: <code>cavalheiro</code></li><br>";
echo "<li><strong>4. Cliente Lenhador (Limite de 3 barbas):</strong><br>";
echo "🔹 E-mail: <code>lenhador@danilo.com</code> | Senha: <code>lenhador</code></li>";
echo "</ul>";

$conn->close();
?>
