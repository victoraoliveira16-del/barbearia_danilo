<?php
header('Content-Type: text/html; charset=utf-8');
include('conexao.php');

echo "<h2>🔍 Diagnóstico de Acesso do Admin</h2>";
echo "<hr>";

try {
    // 1. Verificar se a tabela usuarios existe
    $result = $conn->query("SHOW TABLES LIKE 'usuarios'");
    if ($result->num_rows == 0) {
        die("<p style='color:red;'>❌ A tabela 'usuarios' não existe no banco de dados! Execute o setup_db.php.</p>");
    }
    echo "✅ Tabela 'usuarios' existe.<br>";

    // 2. Buscar dados da tabela usuarios
    $result_users = $conn->query("SELECT id, nome, email, senha, tipo FROM usuarios");
    
    if ($result_users->num_rows == 0) {
        echo "<p style='color:orange;'>⚠️ Nenhum usuário encontrado na tabela 'usuarios'!</p>";
    } else {
        echo "<h3>Usuários cadastrados no Banco de Dados:</h3>";
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%; text-align: left;'>";
        echo "<tr style='background: #eee;'><th>ID</th><th>Nome</th><th>E-mail</th><th>Hash da Senha</th><th>Tipo</th><th>Teste com 'adminbarber'</th><th>Teste com 'password'</th></tr>";
        
        while ($row = $result_users->fetch_assoc()) {
            $teste_adminbarber = password_verify('adminbarber', $row['senha']) ? "✅ Válido" : "❌ Inválido";
            $teste_password = password_verify('password', $row['senha']) ? "✅ Válido" : "❌ Inválido";
            
            // Verifica se é SHA-256 (64 caracteres hexadecimais)
            $tipo_hash = "Desconhecido";
            if (str_starts_with($row['senha'], '$2y$')) {
                $tipo_hash = "Bcrypt (Correto para PHP)";
            } elseif (strlen($row['senha']) == 64 && ctype_xdigit($row['senha'])) {
                $tipo_hash = "SHA-256 (Incompatível)";
            }
            
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>" . htmlspecialchars($row['nome']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td><code style='background:#f4f4f4; padding:2px 4px; display:block; word-break:break-all;'>{$row['senha']}</code> <small style='color:#666;'>($tipo_hash)</small></td>";
            echo "<td><strong>{$row['tipo']}</strong></td>";
            echo "<td style='font-weight:bold; color:" . ($teste_adminbarber[0] == '✅' ? 'green' : 'red') . "'>$teste_adminbarber</td>";
            echo "<td style='font-weight:bold; color:" . ($teste_password[0] == '✅' ? 'green' : 'red') . "'>$teste_password</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erro ao executar diagnóstico: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
