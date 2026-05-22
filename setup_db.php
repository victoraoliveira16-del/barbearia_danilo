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
  `tipo` VARCHAR(20) NOT NULL DEFAULT 'cliente',
  `data_cadastro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_table_users) === TRUE) {
    echo "✅ Tabela `usuarios` configurada com sucesso.<br>";
} else {
    die("❌ Erro ao criar tabela usuarios: " . $conn->error);
}

// 3. Criar Tabela agendamentos
$sql_table_agendamentos = "CREATE TABLE IF NOT EXISTS `agendamentos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `telefone` VARCHAR(20) NOT NULL,
  `servico` VARCHAR(50) NOT NULL,
  `valor` DECIMAL(10,2) NOT NULL,
  `data_agendamento` DATE NOT NULL,
  `hora_agendamento` TIME NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'agendado',
  `data_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_table_agendamentos) === TRUE) {
    echo "✅ Tabela `agendamentos` configurada com sucesso.<br>";
} else {
    die("❌ Erro ao criar tabela agendamentos: " . $conn->error);
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
echo "<p>Agora você já pode se logar como barbeiro em <a href='login.php' style='color:#cca43b; font-weight:bold;'>login.php</a> usando:</p>";
echo "<ul>";
echo "<li><strong>E-mail:</strong> <code style='font-size:1.1em;'>barbeiro@danilo.com</code></li>";
echo "<li><strong>Senha:</strong> <code style='font-size:1.1em;'>adminbarber</code></li>";
echo "</ul>";

$conn->close();
?>
