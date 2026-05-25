<?php
include('conexao.php');

// Garantir que a sessão está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Controle de Acesso: Apenas Administradores (Barbeiro) podem acessar
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['admin', 'barbeiro'])) {
    header("Location: login.php");
    exit;
}

$mensagem = $_SESSION['mensagem'] ?? "";
$classe_mensagem = $_SESSION['classe_mensagem'] ?? "";
unset($_SESSION['mensagem']);
unset($_SESSION['classe_mensagem']);

// Processar Ações (Confirmar, Concluir, Cancelar, Excluir)
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['acao']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $acao = $_GET['acao'];

    if ($acao === 'confirmar') {
        $stmt = $conn->prepare("UPDATE agendamentos SET status = 'confirmado' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['mensagem'] = "⚡ Agendamento Confirmado com sucesso!";
            $_SESSION['classe_mensagem'] = "sucesso";
        } else {
            $_SESSION['mensagem'] = "❌ Erro ao confirmar agendamento.";
            $_SESSION['classe_mensagem'] = "erro";
        }
        $stmt->close();
    }

    elseif ($acao === 'concluir') {
        // Obter o serviço e valor atual para calcular se não for plano
        $stmt_serv = $conn->prepare("SELECT servico, valor FROM agendamentos WHERE id = ?");
        $stmt_serv->bind_param("i", $id);
        $stmt_serv->execute();
        $res_serv = $stmt_serv->get_result()->fetch_assoc();
        $servico = $res_serv['servico'] ?? '';
        $valor_atual = $res_serv['valor'] ?? 0.00;
        $stmt_serv->close();

        // Se o valor já for 0.00 (plano de assinatura), mantém. Caso contrário, aplica valor cheio.
        $valor = $valor_atual;
        if ($valor_atual > 0.00) {
            if ($servico === 'Cabelo') $valor = 40.00;
            elseif ($servico === 'Barba') $valor = 30.00;
            elseif ($servico === 'Combo') $valor = 65.00;
        }

        $stmt = $conn->prepare("UPDATE agendamentos SET status = 'concluido', valor = ? WHERE id = ?");
        $stmt->bind_param("di", $valor, $id);
        if ($stmt->execute()) {
            $_SESSION['mensagem'] = "✂️ Agendamento marcado como Concluído! Lucro atualizado.";
            $_SESSION['classe_mensagem'] = "sucesso";
        } else {
            $_SESSION['mensagem'] = "❌ Erro ao concluir agendamento.";
            $_SESSION['classe_mensagem'] = "erro";
        }
        $stmt->close();
    } 
    
    elseif ($acao === 'cancelar') {
        $stmt = $conn->prepare("UPDATE agendamentos SET status = 'cancelado' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['mensagem'] = "⚠️ Agendamento cancelado com sucesso.";
            $_SESSION['classe_mensagem'] = "alerta";
        } else {
            $_SESSION['mensagem'] = "❌ Erro ao cancelar agendamento.";
            $_SESSION['classe_mensagem'] = "erro";
        }
        $stmt->close();
    } 
    
    elseif ($acao === 'excluir') {
        $stmt = $conn->prepare("DELETE FROM agendamentos WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['mensagem'] = "🗑️ Agendamento excluído do sistema.";
            $_SESSION['classe_mensagem'] = "erro";
        } else {
            $_SESSION['mensagem'] = "❌ Erro ao excluir agendamento.";
            $_SESSION['classe_mensagem'] = "erro";
        }
        $stmt->close();
    }

    // Redireciona de volta ao painel para evitar que a ação seja executada novamente ao atualizar a página
    header("Location: painel.php");
    exit;
}

// 1. QUERY: Lucro Total de cortes concluídos
$query_lucro = $conn->query("SELECT SUM(valor) AS total_lucro FROM agendamentos WHERE status = 'concluido'");
$row_lucro = $query_lucro->fetch_assoc();
$lucro_total = $row_lucro['total_lucro'] ?? 0.00;

// 2. QUERY: Total de cortes concluídos
$query_cortes = $conn->query("SELECT COUNT(*) AS total_cortes FROM agendamentos WHERE status = 'concluido'");
$row_cortes = $query_cortes->fetch_assoc();
$cortes_total = $row_cortes['total_cortes'] ?? 0;

// 3. QUERY: Agendamentos na Fila (pendentes ou confirmados)
$query_pendentes = $conn->query("SELECT COUNT(*) AS total_pendentes FROM agendamentos WHERE status IN ('pendente', 'confirmado')");
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

// 5. QUERY: Listar agendamentos ativos (a fazer) ordenados cronologicamente pela data_hora
$result_agendamentos = $conn->query("SELECT * FROM agendamentos WHERE status IN ('pendente', 'confirmado') ORDER BY data_hora ASC");

// Agrupar agendamentos ativos por dia em PHP
$agendamentos_por_dia = [];
if ($result_agendamentos && $result_agendamentos->num_rows > 0) {
    while ($row = $result_agendamentos->fetch_assoc()) {
        $data = date('Y-m-d', strtotime($row['data_hora']));
        $agendamentos_por_dia[$data][] = $row;
    }
}

// 5b. QUERY: Listar histórico de atendimentos concluídos ou cancelados (mais recentes primeiro)
$result_historico = $conn->query("SELECT * FROM agendamentos WHERE status IN ('concluido', 'cancelado') ORDER BY data_hora DESC LIMIT 50");

// 6. HELPER & CÁLCULOS: Lucros Periódicos (Semanal, Mensal, Anual)
function obterLucroNoPeriodo($conn, $data_inicio, $data_fim) {
    $stmt = $conn->prepare("SELECT SUM(valor) AS total FROM agendamentos WHERE status = 'concluido' AND data_hora BETWEEN ? AND ?");
    $stmt->bind_param("ss", $data_inicio, $data_fim);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return floatval($result['total'] ?? 0.00);
}

// Calcular os intervalos de tempo dinamicamente
$hoje = new DateTime('now');

// A. Semana Atual (Segunda-feira 00:00:00 a Domingo 23:59:59)
$inicio_sem_atual = (clone $hoje)->modify('Monday this week')->format('Y-m-d 00:00:00');
$fim_sem_atual = (clone $hoje)->modify('Sunday this week')->format('Y-m-d 23:59:59');

// B. Mês Atual
$inicio_mes_atual = (clone $hoje)->format('Y-m-01 00:00:00');
$fim_mes_atual = (clone $hoje)->format('Y-m-t 23:59:59');

// C. Ano Atual
$inicio_ano_atual = (clone $hoje)->format('Y-01-01 00:00:00');
$fim_ano_atual = (clone $hoje)->format('Y-12-31 23:59:59');

// Executar os cálculos nos intervalos correspondentes
$lucro_sem_atual = obterLucroNoPeriodo($conn, $inicio_sem_atual, $fim_sem_atual);
$lucro_mes_atual = obterLucroNoPeriodo($conn, $inicio_mes_atual, $fim_mes_atual);
$lucro_ano_atual = obterLucroNoPeriodo($conn, $inicio_ano_atual, $fim_ano_atual);

// 7. QUERY: Dados de Assinantes (pega a última assinatura de cada usuário)
$query_assinantes = $conn->query("
    SELECT a.plano, a.preco, a.nome_cliente, a.data_assinatura
    FROM assinaturas a
    INNER JOIN (
        SELECT usuario_id, MAX(data_assinatura) AS ultima
        FROM assinaturas
        GROUP BY usuario_id
    ) ult ON a.usuario_id = ult.usuario_id AND a.data_assinatura = ult.ultima
    ORDER BY a.data_assinatura DESC
");

$assinantes_lista = [];
$total_assinantes = 0;
$assinantes_cavalheiro = 0;
$assinantes_vip = 0;
$assinantes_lenhador = 0;
$receita_mensal = 0.0;

if ($query_assinantes && $query_assinantes->num_rows > 0) {
    while ($row_ass = $query_assinantes->fetch_assoc()) {
        $assinantes_lista[] = $row_ass;
        $total_assinantes++;
        
        // Contar por plano
        if ($row_ass['plano'] === 'Plano Cavalheiro') $assinantes_cavalheiro++;
        elseif ($row_ass['plano'] === 'Plano VIP Style') $assinantes_vip++;
        elseif ($row_ass['plano'] === 'Plano Lenhador') $assinantes_lenhador++;
        
        // Extrair valor numérico do preço (ex: "R$ 69/mês" -> 69)
        $preco_numerico = floatval(preg_replace('/[^0-9,.]/', '', str_replace(',', '.', $row_ass['preco'])));
        $receita_mensal += $preco_numerico;
    }
}

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

    <!-- Menu de Abas Premium -->
    <div class="painel-tabs">
        <button class="tab-btn active" data-tab="agendamentos">✂️ Agendamentos & Lucros</button>
        <button class="tab-btn" data-tab="assinantes">👥 Assinantes & Planos VIP</button>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="alerta-painel <?php echo $classe_mensagem; ?>">
            <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <!-- CONTEÚDO DA ABA: AGENDAMENTOS -->
    <div id="tab-agendamentos" class="tab-pane active">
        <!-- Nova Seção de Métricas de Lucro Periódico -->
        <h2 class="painel-secao-titulo">Fluxo de Caixa Periódico</h2>
        <p class="card-subtitle painel-secao-subtitle-custom">Faturamento detalhado por semana, mês e ano corrente.</p>
        <div class="lucros-grid">
            <div class="lucro-card gold-border">
                <div class="lucro-card-header">
                    <h3>Lucro Semanal</h3>
                </div>
                <p class="lucro-value">R$ <?php echo number_format($lucro_sem_atual, 2, ',', '.'); ?></p>
                <span class="lucro-desc">Faturamento da semana atual</span>
            </div>

            <div class="lucro-card gold-border">
                <div class="lucro-card-header">
                    <h3>Lucro Mensal</h3>
                </div>
                <p class="lucro-value">R$ <?php echo number_format($lucro_mes_atual, 2, ',', '.'); ?></p>
                <span class="lucro-desc">Faturamento do mês atual</span>
            </div>

            <div class="lucro-card gold-border">
                <div class="lucro-card-header">
                    <h3>Lucro Anual</h3>
                </div>
                <p class="lucro-value">R$ <?php echo number_format($lucro_ano_atual, 2, ',', '.'); ?></p>
                <span class="lucro-desc">Faturamento do ano atual</span>
            </div>
        </div>

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
                            <div class="dist-bar-fill dist-bar-fill-cabelo" style="width: <?php echo ($cortes_total > 0) ? ($dist_cabelo / $cortes_total * 100) : 0; ?>%;"></div>
                        </div>
                    </div>

                    <div class="dist-item">
                        <div class="dist-header">
                            <span>Apenas Barba (R$ 30,00)</span>
                            <strong><?php echo $dist_barba; ?> cortes</strong>
                        </div>
                        <div class="dist-bar-bg">
                            <div class="dist-bar-fill dist-bar-fill-barba" style="width: <?php echo ($cortes_total > 0) ? ($dist_barba / $cortes_total * 100) : 0; ?>%;"></div>
                        </div>
                    </div>

                    <div class="dist-item">
                        <div class="dist-header">
                            <span>Combo Cabelo & Barba (R$ 65,00)</span>
                            <strong><?php echo $dist_combo; ?> cortes</strong>
                        </div>
                        <div class="dist-bar-bg">
                            <div class="dist-bar-fill dist-bar-fill-combo" style="width: <?php echo ($cortes_total > 0) ? ($dist_combo / $cortes_total * 100) : 0; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Gerenciamento de Agendamentos -->
        <div class="painel-card table-card">
            <div class="table-header-row" style="margin-bottom: 25px;">
                <h2>Agenda de Atendimento por Dia</h2>
                <p class="card-subtitle">Visualize o que o barbeiro tem para fazer hoje e nos próximos dias, organizado por horário.</p>
            </div>

            <?php if (!empty($agendamentos_por_dia)): ?>
                <?php foreach ($agendamentos_por_dia as $data => $lista): ?>
                    <div class="dia-grupo" style="margin-bottom: 35px; border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <div class="dia-header" style="background: rgba(188, 150, 72, 0.1); border-bottom: 1px solid rgba(188, 150, 72, 0.15); padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="color: var(--primary); margin: 0; font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                📅 <?php echo date('d/m/Y', strtotime($data)); ?> 
                                <span style="font-size: 0.85rem; color: #888; font-weight: 500;">
                                    (<?php 
                                    $dias_semana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                                    echo $dias_semana[date('w', strtotime($data))]; 
                                    ?>)
                                </span>
                            </h3>
                            <span class="status-badge" style="background: rgba(255, 255, 255, 0.05); color: #ccc; border: 1px solid rgba(255, 255, 255, 0.08); font-weight: 600;">
                                <?php echo count($lista); ?> agendamento(s)
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="painel-table" style="margin-bottom: 0;">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Horário</th>
                                        <th>Cliente</th>
                                        <th>WhatsApp</th>
                                        <th>Serviço</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th style="text-align: center; width: 220px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista as $row): ?>
                                        <tr>
                                            <td class="font-bold" style="color: var(--primary); font-size: 1.15rem;"><?php echo date('H:i', strtotime($row['data_hora'])); ?></td>
                                            <td class="font-bold"><?php echo htmlspecialchars($row['nome']); ?></td>
                                            <td>
                                                <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $row['telefone']); ?>" target="_blank" class="whatsapp-link" style="color: #25d366; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                                                    <span class="painel-emoji-icon whatsapp">💬</span><?php echo htmlspecialchars($row['telefone']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="servico-tag"><?php echo htmlspecialchars($row['servico']); ?></span>
                                            </td>
                                            <td class="font-bold">R$ <?php echo number_format($row['valor'], 2, ',', '.'); ?></td>
                                            <td>
                                                <?php 
                                                $status = $row['status'];
                                                if ($status === 'confirmado') {
                                                    echo '<span class="status-badge confirmado">Confirmado</span>';
                                                } else {
                                                    echo '<span class="status-badge pendente">Pendente</span>';
                                                }
                                                ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <div class="action-buttons" style="display: flex; justify-content: center; gap: 8px;">
                                                    <a href="painel.php?acao=concluir&id=<?php echo $row['id']; ?>" class="btn-action concluir" title="Concluir e Finalizar Corte" style="padding: 5px 10px; font-size: 0.8rem;">✓ Concluir Atendimento</a>
                                                    <a href="painel.php?acao=cancelar&id=<?php echo $row['id']; ?>" class="btn-action cancelar" title="Cancelar Agendamento" style="padding: 5px 10px; font-size: 0.8rem;">✕ Cancelar</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: #888; font-size: 1rem;">
                    📭 Nenhum agendamento a fazer encontrado.
                </div>
            <?php endif; ?>
        </div>

        <!-- Tabela de Histórico (Concluídos e Cancelados) -->
        <div class="painel-card table-card" style="margin-top: 40px;">
            <div class="table-header-row" style="margin-bottom: 20px;">
                <h2>Histórico de Atendimentos (Últimos 50)</h2>
                <p class="card-subtitle">Registros passados de cortes concluídos e agendamentos cancelados.</p>
            </div>

            <div class="table-responsive">
                <table class="painel-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Serviço</th>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th style="text-align: center; width: 120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_historico && $result_historico->num_rows > 0): ?>
                            <?php while ($row = $result_historico->fetch_assoc()): ?>
                                <tr>
                                    <td class="font-bold"><?php echo htmlspecialchars($row['nome']); ?></td>
                                    <td>
                                        <span class="servico-tag"><?php echo htmlspecialchars($row['servico']); ?></span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($row['data_hora'])); ?></td>
                                    <td class="font-bold"><?php echo date('H:i', strtotime($row['data_hora'])); ?></td>
                                    <td>R$ <?php echo number_format($row['valor'], 2, ',', '.'); ?></td>
                                    <td>
                                        <?php 
                                        $status = $row['status'];
                                        if ($status === 'concluido') {
                                            echo '<span class="status-badge concluido">Concluído</span>';
                                        } else {
                                            echo '<span class="status-badge cancelado">Cancelado</span>';
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="action-buttons" style="display: flex; justify-content: center;">
                                            <a href="painel.php?acao=excluir&id=<?php echo $row['id']; ?>" class="btn-action excluir" onclick="return confirm('Deseja realmente apagar este registro do histórico?')" title="Apagar Registro" style="padding: 5px 10px; font-size: 0.8rem;">🗑️ Apagar</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="tabela-alerta-vazia" style="padding: 30px; text-align: center; color: #888;">
                                    📭 Nenhum histórico de atendimento registrado.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- CONTEÚDO DA ABA: ASSINANTES -->
    <div id="tab-assinantes" class="tab-pane">
        <h2 class="painel-secao-titulo">Painel do Clube VIP & Assinantes</h2>
        <p class="card-subtitle" style="margin-bottom: 25px;">Metrificação detalhada do clube, receita recorrente e controle de assinaturas.</p>

        <!-- Cards Estatísticos dos Assinantes -->
        <div class="assinantes-stats-grid">
            <div class="assinante-stat-card gold-border">
                <div class="assinante-stat-icon">👥</div>
                <div class="assinante-stat-info">
                    <span class="assinante-stat-label">Total de Assinantes</span>
                    <span class="assinante-stat-value"><?php echo $total_assinantes; ?></span>
                </div>
            </div>

            <div class="assinante-stat-card gold-border">
                <div class="assinante-stat-icon">💰</div>
                <div class="assinante-stat-info">
                    <span class="assinante-stat-label">Receita Mensal</span>
                    <span class="assinante-stat-value receita">R$ <?php echo number_format($receita_mensal, 2, ',', '.'); ?></span>
                </div>
            </div>

            <div class="assinante-stat-card">
                <div class="assinante-stat-icon">🎩</div>
                <div class="assinante-stat-info">
                    <span class="assinante-stat-label">Plano Cavalheiro</span>
                    <span class="assinante-stat-value"><?php echo $assinantes_cavalheiro; ?></span>
                    <span class="assinante-stat-pct"><?php echo $total_assinantes > 0 ? round($assinantes_cavalheiro / $total_assinantes * 100) : 0; ?>% dos assinantes</span>
                </div>
            </div>

            <div class="assinante-stat-card premium-glow">
                <div class="assinante-stat-icon">👑</div>
                <div class="assinante-stat-info">
                    <span class="assinante-stat-label">Plano VIP Style</span>
                    <span class="assinante-stat-value"><?php echo $assinantes_vip; ?></span>
                    <span class="assinante-stat-pct"><?php echo $total_assinantes > 0 ? round($assinantes_vip / $total_assinantes * 100) : 0; ?>% dos assinantes</span>
                </div>
            </div>

            <div class="assinante-stat-card">
                <div class="assinante-stat-icon">🪓</div>
                <div class="assinante-stat-info">
                    <span class="assinante-stat-label">Plano Lenhador</span>
                    <span class="assinante-stat-value"><?php echo $assinantes_lenhador; ?></span>
                    <span class="assinante-stat-pct"><?php echo $total_assinantes > 0 ? round($assinantes_lenhador / $total_assinantes * 100) : 0; ?>% dos assinantes</span>
                </div>
            </div>
        </div>

        <!-- Gráfico de Distribuição dos Assinantes -->
        <div class="painel-card painel-margem-baixo-card">
            <h2>Distribuição por Plano</h2>
            <p class="card-subtitle">Proporção de assinantes em cada nível.</p>
            <div class="dist-list">
                <div class="dist-item">
                    <div class="dist-header">
                        <span>🎩 Plano Cavalheiro (R$ 79,90/mês)</span>
                        <strong><?php echo $assinantes_cavalheiro; ?> assinante<?php echo $assinantes_cavalheiro !== 1 ? 's' : ''; ?></strong>
                    </div>
                    <div class="dist-bar-bg">
                        <div class="dist-bar-fill dist-bar-fill-plano-cavalheiro" style="width: <?php echo $total_assinantes > 0 ? ($assinantes_cavalheiro / $total_assinantes * 100) : 0; ?>%;"></div>
                    </div>
                </div>

                <div class="dist-item">
                    <div class="dist-header">
                        <span>👑 Plano VIP Style (R$ 129/mês)</span>
                        <strong><?php echo $assinantes_vip; ?> assinante<?php echo $assinantes_vip !== 1 ? 's' : ''; ?></strong>
                    </div>
                    <div class="dist-bar-bg">
                        <div class="dist-bar-fill dist-bar-fill-plano-vip" style="width: <?php echo $total_assinantes > 0 ? ($assinantes_vip / $total_assinantes * 100) : 0; ?>%;"></div>
                    </div>
                </div>

                <div class="dist-item">
                    <div class="dist-header">
                        <span>🪓 Plano Lenhador (R$ 59/mês)</span>
                        <strong><?php echo $assinantes_lenhador; ?> assinante<?php echo $assinantes_lenhador !== 1 ? 's' : ''; ?></strong>
                    </div>
                    <div class="dist-bar-bg">
                        <div class="dist-bar-fill dist-bar-fill-plano-lenhador" style="width: <?php echo $total_assinantes > 0 ? ($assinantes_lenhador / $total_assinantes * 100) : 0; ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Assinantes -->
        <div class="painel-card table-card">
            <div class="table-header-row">
                <h2>Lista de Assinantes Ativos</h2>
                <p class="card-subtitle">Todos os membros ativos com seus respectivos planos.</p>
            </div>

            <div class="table-responsive">
                <table class="painel-table">
                    <thead>
                        <tr>
                            <th>Assinante</th>
                            <th>Plano</th>
                            <th>Valor Mensal</th>
                            <th>Data de Assinatura</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($assinantes_lista) > 0): ?>
                            <?php foreach ($assinantes_lista as $ass): ?>
                                <tr>
                                    <td class="font-bold"><?php echo htmlspecialchars($ass['nome_cliente']); ?></td>
                                    <td>
                                        <?php
                                        $plano_class = 'pendente';
                                        if ($ass['plano'] === 'Plano VIP Style') $plano_class = 'confirmado';
                                        elseif ($ass['plano'] === 'Plano Cavalheiro') $plano_class = 'concluido';
                                        elseif ($ass['plano'] === 'Plano Lenhador') $plano_class = 'pendente';
                                        ?>
                                        <span class="status-badge <?php echo $plano_class; ?>"><?php echo htmlspecialchars($ass['plano']); ?></span>
                                    </td>
                                    <td class="font-bold"><?php echo htmlspecialchars($ass['preco']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($ass['data_assinatura'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="tabela-alerta-vazia">
                                    📭 Nenhum assinante encontrado.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Alertas Fade-Out
    const alerta = document.querySelector('.alerta-painel');
    if (alerta) {
        setTimeout(function() {
            alerta.classList.add('fade-out');
            setTimeout(function() {
                alerta.remove();
            }, 500);
        }, 4000);
    }

    // Gerenciamento de Abas
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');

            // Remover active de todos
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));

            // Adicionar active ao correto
            this.classList.add('active');
            document.getElementById(`tab-${targetTab}`).classList.add('active');
        });
    });
});
</script>

</body>
</html>
