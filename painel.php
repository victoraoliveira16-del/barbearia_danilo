<?php
include('conexao.php');

// Garantir que a sessão está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Controle de Acesso: Apenas Administradores (Barbeiro) podem acessar
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$mensagem = "";
$classe_mensagem = "";

// Processar Ações (Concluir, Cancelar, Excluir)
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['acao']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $acao = $_GET['acao'];

    if ($acao === 'concluir') {
        // Obter o serviço para calcular e registrar o valor no momento da conclusão
        $stmt_serv = $conn->prepare("SELECT servico FROM agendamentos WHERE id = ?");
        $stmt_serv->bind_param("i", $id);
        $stmt_serv->execute();
        $res_serv = $stmt_serv->get_result()->fetch_assoc();
        $servico = $res_serv['servico'] ?? '';
        $stmt_serv->close();

        $valor = 0.00;
        if ($servico === 'Cabelo') $valor = 40.00;
        elseif ($servico === 'Barba') $valor = 30.00;
        elseif ($servico === 'Combo') $valor = 65.00;

        $stmt = $conn->prepare("UPDATE agendamentos SET status = 'concluido', valor = ? WHERE id = ?");
        $stmt->bind_param("di", $valor, $id);
        if ($stmt->execute()) {
            $mensagem = "⚡ Corte marcado como Concluído! O lucro foi atualizado.";
            $classe_mensagem = "sucesso";
        } else {
            $mensagem = "❌ Erro ao concluir agendamento.";
            $classe_mensagem = "erro";
        }
        $stmt->close();
    } 
    
    elseif ($acao === 'cancelar') {
        $stmt = $conn->prepare("UPDATE agendamentos SET status = 'cancelado' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $mensagem = "⚠️ Agendamento cancelado com sucesso.";
            $classe_mensagem = "alerta";
        } else {
            $mensagem = "❌ Erro ao cancelar agendamento.";
            $classe_mensagem = "erro";
        }
        $stmt->close();
    } 
    
    elseif ($acao === 'excluir') {
        $stmt = $conn->prepare("DELETE FROM agendamentos WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $mensagem = "🗑️ Agendamento excluído do sistema.";
            $classe_mensagem = "erro";
        } else {
            $mensagem = "❌ Erro ao excluir agendamento.";
            $classe_mensagem = "erro";
        }
        $stmt->close();
    }
}

// 1. QUERY: Lucro Total de cortes concluídos
$query_lucro = $conn->query("SELECT SUM(valor) AS total_lucro FROM agendamentos WHERE status = 'concluido'");
$row_lucro = $query_lucro->fetch_assoc();
$lucro_total = $row_lucro['total_lucro'] ?? 0.00;

// 2. QUERY: Total de cortes concluídos
$query_cortes = $conn->query("SELECT COUNT(*) AS total_cortes FROM agendamentos WHERE status = 'concluido'");
$row_cortes = $query_cortes->fetch_assoc();
$cortes_total = $row_cortes['total_cortes'] ?? 0;

// 3. QUERY: Agendamentos pendentes/futuros
$query_pendentes = $conn->query("SELECT COUNT(*) AS total_pendentes FROM agendamentos WHERE status = 'agendado'");
$row_pendentes = $query_pendentes->fetch_assoc();
$pendentes_total = $row_pendentes['total_pendentes'] ?? 0;

// 4. QUERY: Distribuição de serviços concluídos (para micro gráficos)
$query_dist = $conn->query("
    SELECT 
        SUM(CASE WHEN servico = 'Cabelo' THEN 1 ELSE 0 END) as cabelo,
        SUM(CASE WHEN servico = 'Barba' THEN 1 ELSE 0 END) as barba,
        SUM(CASE WHEN servico = 'Combo' THEN 1 ELSE 0 END) as combo
    FROM agendamentos WHERE status = 'concluido'
");
$row_dist = $query_dist->fetch_assoc();
$dist_cabelo = $row_dist['cabelo'] ?? 0;
$dist_barba = $row_dist['barba'] ?? 0;
$dist_combo = $row_dist['combo'] ?? 0;

// 5. QUERY: Listar todos os agendamentos cadastrados
$result_agendamentos = $conn->query("SELECT * FROM agendamentos ORDER BY data_agendamento DESC, hora_agendamento ASC");

include('header.php');
?>

<section class="secao-painel">
    <div class="painel-header">
        <div>
            <h1>Painel Administrativo</h1>
            <p class="painel-subtitle">Controle de lucros, faturamento e gerenciamento de cortes.</p>
        </div>
        <div class="user-badge-container">
            <span>Barbeiro Logado: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong></span>
        </div>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="alerta-painel <?php echo $classe_mensagem; ?>">
            <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <!-- Grid de Métricas Principais -->
    <div class="metrics-grid">
        <div class="metric-card gold-border">
            <div class="metric-icon"><span class="painel-emoji-icon">📈</span></div>
            <div class="metric-info">
                <h3>Lucro Total</h3>
                <p class="metric-value">R$ <?php echo number_format($lucro_total, 2, ',', '.'); ?></p>
                <span class="metric-desc">Soma de todos os cortes concluídos</span>
            </div>
        </div>

        <div class="metric-card gold-border">
            <div class="metric-icon"><span class="painel-emoji-icon">✂️</span></div>
            <div class="metric-info">
                <h3>Cortes Concluídos</h3>
                <p class="metric-value"><?php echo $cortes_total; ?></p>
                <span class="metric-desc">Atendimentos finalizados com sucesso</span>
            </div>
        </div>

        <div class="metric-card gold-border">
            <div class="metric-icon"><span class="painel-emoji-icon">⏳</span></div>
            <div class="metric-info">
                <h3>Agendados (Fila)</h3>
                <p class="metric-value"><?php echo $pendentes_total; ?></p>
                <span class="metric-desc">Clientes na fila de espera</span>
            </div>
        </div>
    </div>

    <!-- Seção de Distribuição de Serviços & Detalhes -->
    <div class="painel-row">
        <!-- Detalhamento de Serviços -->
        <div class="painel-card flex-1">
            <h2>Cortes por Categoria (Concluídos)</h2>
            <p class="card-subtitle">Entenda quais serviços estão gerando mais faturamento.</p>
            
            <div class="dist-list">
                <div class="dist-item">
                    <div class="dist-header">
                        <span>Apenas Cabelo (R$ 40,00)</span>
                        <strong><?php echo $dist_cabelo; ?> cortes</strong>
                    </div>
                    <div class="dist-bar-bg">
                        <div class="dist-bar-fill" style="width: <?php echo ($cortes_total > 0) ? ($dist_cabelo / $cortes_total * 100) : 0; ?>%; background: #cca43b;"></div>
                    </div>
                </div>

                <div class="dist-item">
                    <div class="dist-header">
                        <span>Apenas Barba (R$ 30,00)</span>
                        <strong><?php echo $dist_barba; ?> cortes</strong>
                    </div>
                    <div class="dist-bar-bg">
                        <div class="dist-bar-fill" style="width: <?php echo ($cortes_total > 0) ? ($dist_barba / $cortes_total * 100) : 0; ?>%; background: #cca43b;"></div>
                    </div>
                </div>

                <div class="dist-item">
                    <div class="dist-header">
                        <span>Combo Cabelo & Barba (R$ 65,00)</span>
                        <strong><?php echo $dist_combo; ?> cortes</strong>
                    </div>
                    <div class="dist-bar-bg">
                        <div class="dist-bar-fill" style="width: <?php echo ($cortes_total > 0) ? ($dist_combo / $cortes_total * 100) : 0; ?>%; background: #e5c060;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Gerenciamento de Agendamentos -->
    <div class="painel-card table-card">
        <div class="table-header-row">
            <h2>Todos os Agendamentos</h2>
            <p class="card-subtitle">Monitore, conclua ou cancele horários agendados.</p>
        </div>

        <div class="table-responsive">
            <table class="painel-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>WhatsApp</th>
                        <th>Serviço</th>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th style="text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_agendamentos->num_rows > 0): ?>
                        <?php while ($row = $result_agendamentos->fetch_assoc()): ?>
                            <tr>
                                <td class="font-bold"><?php echo htmlspecialchars($row['nome']); ?></td>
                                <td>
                                    <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $row['telefone']); ?>" target="_blank" class="whatsapp-link">
                                        <span class="painel-emoji-icon whatsapp">💬</span><?php echo htmlspecialchars($row['telefone']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="servico-tag"><?php echo htmlspecialchars($row['servico']); ?></span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($row['data_agendamento'])); ?></td>
                                <td class="font-bold"><?php echo substr($row['hora_agendamento'], 0, 5); ?></td>
                                <td>R$ <?php echo number_format($row['valor'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php 
                                    $status = $row['status'];
                                    if ($status === 'concluido') {
                                        echo '<span class="status-badge concluido">Concluído</span>';
                                    } elseif ($status === 'cancelado') {
                                        echo '<span class="status-badge cancelado">Cancelado</span>';
                                    } else {
                                        echo '<span class="status-badge agendado">Agendado</span>';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-buttons">
                                        <?php if ($status === 'agendado'): ?>
                                            <a href="painel.php?acao=concluir&id=<?php echo $row['id']; ?>" class="btn-action concluir" title="Marcar como Concluído">✓ Concluir</a>
                                            <a href="painel.php?acao=cancelar&id=<?php echo $row['id']; ?>" class="btn-action cancelar" title="Cancelar Agendamento">✕ Cancelar</a>
                                        <?php else: ?>
                                            <a href="painel.php?acao=excluir&id=<?php echo $row['id']; ?>" class="btn-action excluir" onclick="return confirm('Deseja realmente apagar este registro do histórico?')" title="Apagar Registro">🗑️ Apagar</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px 10px; color: #888;">
                                📭 Nenhum agendamento encontrado no banco de dados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const alerta = document.querySelector('.alerta-painel');
    if (alerta) {
        setTimeout(function() {
            alerta.classList.add('fade-out');
            setTimeout(function() {
                alerta.remove();
            }, 500);
        }, 4000);
    }
});
</script>

</body>
</html>
