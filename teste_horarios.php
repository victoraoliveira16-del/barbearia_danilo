<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnóstico do Agendamento</h2><hr>";

// 1. Testar conexão
echo "<h3>1. Conexão com o Banco</h3>";
try {
    include('conexao.php');
    echo "✅ Conexão OK<br>";
} catch (Exception $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "<br>";
    exit;
}

// 2. Testar sessão
echo "<h3>2. Sessão</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session ID: " . session_id() . "<br>";
echo "usuario_id: " . (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : '❌ NÃO DEFINIDO') . "<br>";
echo "usuario_nome: " . (isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : '❌ NÃO DEFINIDO') . "<br>";

// 3. Testar tabela agendamentos
echo "<h3>3. Tabela agendamentos</h3>";
try {
    $result = $conn->query("SHOW TABLES LIKE 'agendamentos'");
    if ($result->num_rows == 0) {
        echo "❌ Tabela 'agendamentos' NÃO EXISTE!<br>";
        echo "<strong>Execute o setup_db.php primeiro.</strong><br>";
    } else {
        echo "✅ Tabela 'agendamentos' existe<br>";
        
        // Mostrar estrutura
        $cols = $conn->query("DESCRIBE agendamentos");
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse; margin:10px 0;'>";
        echo "<tr style='background:#eee;'><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($col = $cols->fetch_assoc()) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

// 4. Testar a query de horários (simular AJAX)
echo "<h3>4. Teste da Query de Horários</h3>";
$data_teste = date('Y-m-d'); // hoje
echo "Data de teste: <strong>$data_teste</strong><br>";

$todos_horarios = ["09:00:00", "10:00:00", "11:00:00", "14:00:00", "15:00:00", "16:00:00", "17:00:00", "18:00:00"];
echo "Horários configurados: " . implode(', ', $todos_horarios) . "<br>";

try {
    $stmt = $conn->prepare("SELECT TIME(data_hora) as hora_ocupada FROM agendamentos WHERE DATE(data_hora) = ? AND status != 'cancelado'");
    $stmt->bind_param("s", $data_teste);
    $stmt->execute();
    $result = $stmt->get_result();

    $ocupados = [];
    while ($row = $result->fetch_assoc()) {
        $ocupados[] = $row['hora_ocupada'];
    }
    $stmt->close();

    echo "Horários ocupados hoje: " . (empty($ocupados) ? "Nenhum" : implode(', ', $ocupados)) . "<br>";
    
    $disponiveis = array_values(array_diff($todos_horarios, $ocupados));
    echo "Horários disponíveis: " . implode(', ', $disponiveis) . "<br>";
    echo "JSON retornado: <code>" . json_encode($disponiveis) . "</code><br>";
    echo "✅ Query OK<br>";
} catch (Exception $e) {
    echo "❌ Erro na query: " . $e->getMessage() . "<br>";
}

// 5. Testar AJAX endpoint diretamente
echo "<h3>5. Teste direto do endpoint AJAX</h3>";
$url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/agendamento.php?obter_horarios=1&data=$data_teste";
echo "URL do endpoint: <code>$url</code><br>";
echo "<button onclick=\"testarAjax()\">🔄 Testar AJAX agora</button>";
echo "<pre id='resultado-ajax' style='background:#222; color:#0f0; padding:15px; margin:10px 0; border-radius:5px;'>Clique no botão para testar...</pre>";

// 6. Verificar agendamentos existentes
echo "<h3>6. Agendamentos existentes</h3>";
try {
    $result = $conn->query("SELECT id, nome, servico, data_hora, status, valor FROM agendamentos ORDER BY data_hora DESC LIMIT 10");
    if ($result->num_rows == 0) {
        echo "📭 Nenhum agendamento no banco.<br>";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr style='background:#eee;'><th>ID</th><th>Nome</th><th>Serviço</th><th>Data/Hora</th><th>Status</th><th>Valor</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['nome']}</td><td>{$row['servico']}</td><td>{$row['data_hora']}</td><td>{$row['status']}</td><td>R$ " . number_format($row['valor'], 2, ',', '.') . "</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

$conn->close();
?>

<script>
function testarAjax() {
    const resultado = document.getElementById('resultado-ajax');
    resultado.textContent = '⏳ Fazendo requisição AJAX...';
    
    fetch('agendamento.php?obter_horarios=1&data=<?php echo date("Y-m-d"); ?>')
        .then(response => {
            resultado.textContent = `Status HTTP: ${response.status} ${response.statusText}\n`;
            resultado.textContent += `Content-Type: ${response.headers.get('Content-Type')}\n`;
            resultado.textContent += `Redirected: ${response.redirected}\n`;
            if (response.redirected) {
                resultado.textContent += `Redirected URL: ${response.url}\n`;
            }
            return response.text();
        })
        .then(text => {
            resultado.textContent += `\nResposta (primeiros 500 chars):\n${text.substring(0, 500)}`;
            
            // Tentar parsear como JSON
            try {
                const json = JSON.parse(text);
                resultado.textContent += `\n\n✅ JSON válido! Horários: ${JSON.stringify(json)}`;
            } catch(e) {
                resultado.textContent += `\n\n❌ NÃO é JSON válido! Erro: ${e.message}`;
            }
        })
        .catch(error => {
            resultado.textContent += `\n❌ Erro no fetch: ${error.message}`;
        });
}
</script>
