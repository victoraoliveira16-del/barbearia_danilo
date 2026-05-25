<?php
include('conexao.php');

// Gera o hash bcrypt correto para "adminbarber"
$nova_senha_hash = password_hash('adminbarber', PASSWORD_BCRYPT);

// Atualiza a senha do admin para o hash bcrypt correto
$stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE email = 'barbeiro@danilo.com'");
$stmt->bind_param("s", $nova_senha_hash);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo "<div style='font-family:sans-serif; background:#d4edda; color:#155724; padding:20px; border-radius:8px; margin:20px;'>";
    echo "<h3>✅ Senha corrigida com sucesso!</h3>";
    echo "<p>O hash da senha foi atualizado para o formato correto (bcrypt).</p>";
    echo "<p><strong>E-mail:</strong> barbeiro@danilo.com</p>";
    echo "<p><strong>Senha:</strong> adminbarber</p>";
    echo "<p><a href='login.php' style='color:#155724; font-weight:bold;'>→ Ir para o Login</a></p>";
    echo "<hr><p style='color:#856404; background:#fff3cd; padding:10px; border-radius:4px;'>⚠️ <strong>APAGUE este arquivo após o login!</strong> Ele não deve ficar no servidor.</p>";
    echo "</div>";
} else {
    // Usuário não existe, cria do zero
    $stmt2 = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES ('Danilo Admin', 'barbeiro@danilo.com', ?, 'admin')");
    $stmt2->bind_param("s", $nova_senha_hash);
    if ($stmt2->execute()) {
        echo "<div style='font-family:sans-serif; background:#d4edda; color:#155724; padding:20px; border-radius:8px; margin:20px;'>";
        echo "<h3>✅ Admin criado com sucesso!</h3>";
        echo "<p><strong>E-mail:</strong> barbeiro@danilo.com</p>";
        echo "<p><strong>Senha:</strong> adminbarber</p>";
        echo "<p><a href='login.php' style='color:#155724; font-weight:bold;'>→ Ir para o Login</a></p>";
        echo "<hr><p style='color:#856404; background:#fff3cd; padding:10px; border-radius:4px;'>⚠️ <strong>APAGUE este arquivo após o login!</strong></p>";
        echo "</div>";
    } else {
        echo "<div style='background:#ffdde1; color:#c0392b; padding:20px; border-radius:8px; margin:20px;'>";
        echo "<h3>❌ Erro ao criar admin</h3>";
        echo "<p>" . $conn->error . "</p>";
        echo "</div>";
    }
}

$stmt->close();
$conn->close();
?>
