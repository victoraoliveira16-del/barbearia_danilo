<?php
$host = "localhost";
$usuario = "root";
$senha = "mysql"; 
$banco = "barbearia_db";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $usuario, $senha, $banco);
    $conn->set_charset("utf8mb4"); 
} catch (mysqli_sql_exception $e) {
    echo "<div style='background:#ffdde1; color:#c0392b; padding:20px; font-family:sans-serif; border-radius:5px; margin:20px;'>";
    echo "<h3>🚨 Erro de Conexão com o Banco de Dados:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
    exit;
}
?>