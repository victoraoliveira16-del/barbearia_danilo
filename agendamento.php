<?php
include('conexao.php');

// Iniciar sessão se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Endpoint AJAX para buscar horários livres de uma data
// IMPORTANTE: Deve ficar ANTES do redirect de autenticação para não quebrar o fetch JS
if (isset($_GET['obter_horarios']) && isset($_GET['data'])) {
    header('Content-Type: application/json');

    // Verificar autenticação no AJAX também
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autenticado']);
        exit;
    }

    $data_selecionada = $_GET['data'];
    $servico = $_GET['servico'] ?? '';

    // Todos os horários de atendimento da barbearia e duração do serviço atual
    if ($servico === 'Barba') {
        $todos_horarios = ["09:00:00", "09:30:00", "10:00:00", "10:30:00", "11:00:00", "11:30:00", "14:00:00", "14:30:00", "15:00:00", "15:30:00", "16:00:00", "16:30:00", "17:00:00", "17:30:00", "18:00:00", "18:30:00"];
        $duracao_atual = 30;
    } else {
        $todos_horarios = ["09:00:00", "10:00:00", "11:00:00", "14:00:00", "15:00:00", "16:00:00", "17:00:00", "18:00:00"];
        $duracao_atual = 60;
    }

    // Buscar agendamentos e seus serviços correspondentes na data selecionada
    $stmt = $conn->prepare("SELECT TIME(data_hora) as hora_ocupada, servico as servico_ocupado FROM agendamentos WHERE DATE(data_hora) = ? AND status != 'cancelado'");
    $stmt->bind_param("s", $data_selecionada);
    $stmt->execute();
    $result = $stmt->get_result();

    $ocupados = [];
    while ($row = $result->fetch_assoc()) {
        $ocupados[] = [
            'inicio' => intval(substr($row['hora_ocupada'], 0, 2)) * 60 + intval(substr($row['hora_ocupada'], 3, 2)),
            'servico' => $row['servico_ocupado']
        ];
    }
    $stmt->close();

    // Filtrar apenas os horários livres (que não conflitam)
    $disponiveis = [];
    foreach ($todos_horarios as $hora) {
        $h_inicio = intval(substr($hora, 0, 2)) * 60 + intval(substr($hora, 3, 2));
        $h_fim = $h_inicio + $duracao_atual;

        $conflito = false;
        foreach ($ocupados as $ocupado) {
            $duracao_ocupado = ($ocupado['servico'] === 'Barba') ? 30 : 60;
            $o_inicio = $ocupado['inicio'];
            $o_fim = $o_inicio + $duracao_ocupado;

            // Dois intervalos [A, B] e [C, D] se sobrepõem se A < D e C < B
            if ($h_inicio < $o_fim && $o_inicio < $h_fim) {
                $conflito = true;
                break;
            }
        }

        if (!$conflito) {
            $disponiveis[] = $hora;
        }
    }

    echo json_encode($disponiveis);
    exit;
}

// Bloquear acesso não autenticado (redirecionar para cadastro com aviso)
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php?aba=cadastro&aviso=agendamento");
    exit;
}

// Processar formulário POST ANTES de qualquer inclusão de HTML (PRG)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $telefone = $_POST['telefone'];
    $servico = $_POST['servico'];
    $data = $_POST['data'];
    $hora = $_POST['hora'];

    $data_hora_agendamento = "$data $hora";

    // Verificar se o horário já está reservado ou entra em conflito com outro agendamento (sobreposição de horários)
    $duracao_atual = ($servico === 'Barba') ? 30 : 60;
    $h_inicio = intval(substr($hora, 0, 2)) * 60 + intval(substr($hora, 3, 2));
    $h_fim = $h_inicio + $duracao_atual;

    $verificar = $conn->prepare("SELECT TIME(data_hora) as hora_ocupada, servico FROM agendamentos WHERE DATE(data_hora) = ? AND status != 'cancelado'");
    $verificar->bind_param("s", $data);
    $verificar->execute();
    $resultado = $verificar->get_result();

    $conflito = false;
    while ($row = $resultado->fetch_assoc()) {
        $duracao_ocupado = ($row['servico'] === 'Barba') ? 30 : 60;
        $o_inicio = intval(substr($row['hora_ocupada'], 0, 2)) * 60 + intval(substr($row['hora_ocupada'], 3, 2));
        $o_fim = $o_inicio + $duracao_ocupado;

        // Dois intervalos [A, B] e [C, D] se sobrepõem se A < D e C < B
        if ($h_inicio < $o_fim && $o_inicio < $h_fim) {
            $conflito = true;
            break;
        }
    }
    $verificar->close();

    if ($conflito) {
        $_SESSION['msg_agendamento'] = "❌ Desculpe, este horário já está reservado ou entra em conflito com outro agendamento. Escolha outro!";
        $_SESSION['classe_agendamento'] = "erro";
        $_SESSION['form_data'] = $_POST;
        header("Location: agendamento.php");
        exit;
    }

    $usuario_id = $_SESSION['usuario_id'];

    // 1. Obter o plano de assinatura ativo do usuário
    $plano_ativo = null;
    $stmt_plano = $conn->prepare("SELECT plano FROM assinaturas WHERE usuario_id = ? ORDER BY data_assinatura DESC LIMIT 1");
    $stmt_plano->bind_param("i", $usuario_id);
    $stmt_plano->execute();
    $res_plano = $stmt_plano->get_result();
    if ($res_plano->num_rows > 0) {
        $plano_ativo = $res_plano->fetch_assoc()['plano'];
    }
    $stmt_plano->close();

    // 2. Definir o intervalo do mês atual
    $inicio_mes = date('Y-m-01 00:00:00');
    $fim_mes = date('Y-m-t 23:59:59');

    // 3. Contabilizar agendamentos gratuitos (valor = 0.00) consumidos no mês atual
    $total_cabelo_mes = 0;
    $total_barba_mes = 0;

    $stmt_uso = $conn->prepare("
        SELECT servico, COUNT(*) as total 
        FROM agendamentos 
        WHERE usuario_id = ? 
          AND status != 'cancelado' 
          AND data_hora BETWEEN ? AND ?
        GROUP BY servico
    ");
    $stmt_uso->bind_param("iss", $usuario_id, $inicio_mes, $fim_mes);
    $stmt_uso->execute();
    $res_uso = $stmt_uso->get_result();
    while ($row = $res_uso->fetch_assoc()) {
        if ($row['servico'] === 'Cabelo') {
            $total_cabelo_mes += $row['total'];
        } elseif ($row['servico'] === 'Barba') {
            $total_barba_mes += $row['total'];
        } elseif ($row['servico'] === 'Combo') {
            $total_cabelo_mes += $row['total'];
            $total_barba_mes += $row['total'];
        }
    }
    $stmt_uso->close();

    // 4. Aplicar regras de preços e limites por plano
    $valor = 0.00;
    $msg_adicional = "";

    if ($plano_ativo === 'Plano Cavalheiro') {
        if ($servico === 'Cabelo') {
            if ($total_cabelo_mes >= 3) {
                // Limite excedido: cobra valor cheio!
                $valor = 40.00;
                $msg_adicional = " ⚠️ Atenção: Você excedeu seu limite de 3 cortes mensais inclusos no Plano Cavalheiro. Este agendamento foi cobrado como serviço avulso no valor de R$ 40,00.";
            } else {
                $valor = 0.00;
            }
        } elseif ($servico === 'Barba') {
            if ($total_barba_mes >= 3) {
                // Limite excedido: cobra valor cheio!
                $valor = 30.00;
                $msg_adicional = " ⚠️ Atenção: Você excedeu seu limite de 3 manutenções de barba inclusas no Plano Cavalheiro. Este agendamento foi cobrado como serviço avulso no valor de R$ 30,00.";
            } else {
                $valor = 0.00;
            }
        } elseif ($servico === 'Combo') {
            if ($total_cabelo_mes >= 3 && $total_barba_mes >= 3) {
                $valor = 65.00; // Ambos limites excedidos
                $msg_adicional = " ⚠️ Atenção: Você excedeu seus limites mensais de corte e barba inclusos no Plano Cavalheiro. Este agendamento foi cobrado no valor avulso de R$ 65,00.";
            } elseif ($total_cabelo_mes >= 3) {
                $valor = 40.00; // Apenas corte excedido
                $msg_adicional = " ⚠️ Atenção: Você excedeu seu limite de cortes inclusos no Plano Cavalheiro. A barba foi coberta pelo plano, cobrando apenas o valor avulso do corte (R$ 40,00).";
            } elseif ($total_barba_mes >= 3) {
                $valor = 30.00; // Apenas barba excedida
                $msg_adicional = " ⚠️ Atenção: Você excedeu seu limite de barbas inclusas no Plano Cavalheiro. O corte foi coberto pelo plano, cobrando apenas o valor avulso da barba (R$ 30,00).";
            } else {
                $valor = 0.00; // Ambos cobertos!
            }
        }
    } elseif ($plano_ativo === 'Plano Lenhador') {
        if ($servico === 'Barba') {
            if ($total_barba_mes >= 3) {
                // Limite excedido: cobra valor cheio!
                $valor = 30.00;
                $msg_adicional = " ⚠️ Atenção: Você excedeu seu limite de 3 manutenções de barba inclusas no Plano Lenhador. Este agendamento foi cobrado como serviço avulso no valor de R$ 30,00.";
            } else {
                $valor = 0.00;
            }
        } else {
            // Cabelo ou Combo não cobertos -> Cobrança cheia
            $valor = ($servico === 'Cabelo') ? 40.00 : 65.00;
            $msg_adicional = " ⚠️ Nota: Este serviço não é coberto pelo Plano Lenhador e será cobrado no valor de R$ " . number_format($valor, 2, ',', '.') . ".";
        }
    } elseif ($plano_ativo === 'Plano VIP Style') {
        // Tudo grátis e ilimitado
        if ($servico === 'Cabelo' || $servico === 'Barba' || $servico === 'Combo') {
            $valor = 0.00;
        }
    } else {
        // Sem plano -> Valores padrão
        if ($servico === 'Cabelo') $valor = 40.00;
        elseif ($servico === 'Barba') $valor = 30.00;
        elseif ($servico === 'Combo') $valor = 65.00;
    }

    // Salvar no banco (status padrão 'pendente', data_hora unificada)
    $salvar = $conn->prepare("INSERT INTO agendamentos (usuario_id, nome, telefone, servico, valor, data_hora, status) VALUES (?, ?, ?, ?, ?, ?, 'pendente')");
    $salvar->bind_param("isssds", $usuario_id, $nome, $telefone, $servico, $valor, $data_hora_agendamento);

    if ($salvar->execute()) {
        $_SESSION['msg_agendamento'] = "✅ Agendamento realizado com sucesso! Aguarde a confirmação do barbeiro." . $msg_adicional;
        $_SESSION['classe_agendamento'] = "sucesso";
    } else {
        $_SESSION['msg_agendamento'] = "❌ Erro ao agendar. Tente novamente.";
        $_SESSION['classe_agendamento'] = "erro";
        $_SESSION['form_data'] = $_POST;
    }
    $salvar->close();

    header("Location: agendamento.php");
    exit;
}

// PRG: Recuperar mensagens e dados preenchidos da sessão
$mensagem = $_SESSION['msg_agendamento'] ?? "";
$classe_mensagem = $_SESSION['classe_agendamento'] ?? "";
unset($_SESSION['msg_agendamento']);
unset($_SESSION['classe_agendamento']);

$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Auto-preenchimento: buscar telefone do último agendamento do usuário
$telefone_anterior = "";
$plano_ativo = null;
$total_cabelo_mes = 0;
$total_barba_mes = 0;

if (isset($_SESSION['usuario_id'])) {
    $usuario_id = $_SESSION['usuario_id'];
    
    // 1. Telefone anterior
    $stmt_tel = $conn->prepare("SELECT telefone FROM agendamentos WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT 1");
    $stmt_tel->bind_param("i", $usuario_id);
    $stmt_tel->execute();
    $res_tel = $stmt_tel->get_result();
    if ($res_tel->num_rows > 0) {
        $telefone_anterior = $res_tel->fetch_assoc()['telefone'];
    }
    $stmt_tel->close();
    
    // 2. Plano de assinatura ativo
    $stmt_plano = $conn->prepare("SELECT plano FROM assinaturas WHERE usuario_id = ? ORDER BY data_assinatura DESC LIMIT 1");
    $stmt_plano->bind_param("i", $usuario_id);
    $stmt_plano->execute();
    $res_plano = $stmt_plano->get_result();
    if ($res_plano->num_rows > 0) {
        $plano_ativo = $res_plano->fetch_assoc()['plano'];
    }
    $stmt_plano->close();
    
    // 3. Consumo gratuito do mês atual
    $inicio_mes = date('Y-m-01 00:00:00');
    $fim_mes = date('Y-m-t 23:59:59');
    
    $stmt_uso = $conn->prepare("
        SELECT servico, COUNT(*) as total 
        FROM agendamentos 
        WHERE usuario_id = ? 
          AND status != 'cancelado' 
          AND data_hora BETWEEN ? AND ?
        GROUP BY servico
    ");
    $stmt_uso->bind_param("iss", $usuario_id, $inicio_mes, $fim_mes);
    $stmt_uso->execute();
    $res_uso = $stmt_uso->get_result();
    while ($row = $res_uso->fetch_assoc()) {
        if ($row['servico'] === 'Cabelo') {
            $total_cabelo_mes += $row['total'];
        } elseif ($row['servico'] === 'Barba') {
            $total_barba_mes += $row['total'];
        } elseif ($row['servico'] === 'Combo') {
            $total_cabelo_mes += $row['total'];
            $total_barba_mes += $row['total'];
        }
    }
    $stmt_uso->close();
}

include('header.php');
?>

<section id="agendar" class="secao-escura secao-interna">
    <h2>Faça seu Agendamento</h2>
    <p>Rápido, prático e sem filas de espera.</p>

    <div class="container-agenda">
        <?php if (!empty($mensagem)): ?>
            <div class="alerta <?php echo $classe_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <form action="agendamento.php" method="POST" id="form-agendamento">
            <div class="form-group">
                <label for="nome">Seu Nome:</label>
                <input type="text" id="nome" name="nome" required placeholder="Digite seu nome completo" value="<?php
                    if (isset($form_data['nome'])) {
                        echo htmlspecialchars($form_data['nome']);
                    } elseif (isset($_SESSION['usuario_nome'])) {
                        echo htmlspecialchars($_SESSION['usuario_nome']);
                    }
                ?>">
            </div>

            <div class="form-group">
                <label for="telefone">WhatsApp:</label>
                <input
                    type="tel"
                    id="telefone"
                    name="telefone"
                    required
                    placeholder="(00) 99999-9999"
                    maxlength="15"
                    value="<?php
                        if (isset($form_data['telefone'])) {
                            echo htmlspecialchars($form_data['telefone']);
                        } elseif (!empty($telefone_anterior)) {
                            echo htmlspecialchars($telefone_anterior);
                        }
                    ?>">
            </div>

            <div class="form-group">
                <label for="servico">Serviço Desejado:</label>
                <select id="servico" name="servico" required>
                    <option value="">Selecione...</option>
                    <option value="Cabelo" <?php echo (isset($form_data['servico']) && $form_data['servico'] == 'Cabelo') ? 'selected' : ''; ?>>Apenas Cabelo - R$ 40,00</option>
                    <option value="Barba" <?php echo (isset($form_data['servico']) && $form_data['servico'] == 'Barba') ? 'selected' : ''; ?>>Apenas Barba - R$ 30,00</option>
                    <option value="Combo" <?php echo (isset($form_data['servico']) && $form_data['servico'] == 'Combo') ? 'selected' : ''; ?>>Cabelo & Barba - R$ 65,00</option>
                </select>
            </div>

            <!-- Visualizador de Preço Dinâmico (VIP/Upgrade/Excedente) -->
            <div class="form-group" id="visualizador-preco" style="display: none; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 6px; border-left: 4px solid var(--primary); text-align: left; margin-bottom: 20px;">
                <span style="color: #ccc; font-size: 0.9rem; font-weight: 600;">Preço do Serviço:</span>
                <strong id="preco-estimado-valor" style="color: var(--primary); font-size: 1.25rem; margin-left: 8px;">R$ 0,00</strong>
                <span id="preco-estimado-status" style="display: block; font-size: 0.8rem; color: #888; margin-top: 6px; font-weight: 500;">Incluso no seu plano</span>
            </div>

            <div class="form-group">
                <label for="data">Data:</label>
                <input type="date" id="data" name="data" required value="<?php echo isset($form_data['data']) ? htmlspecialchars($form_data['data']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="hora">Horário:</label>
                <select id="hora" name="hora" required disabled>
                    <option value="">Selecione uma data primeiro...</option>
                </select>
            </div>

            <button type="submit" id="btn-confirmar-agendamento" class="btn btn-full">CONFIRMAR AGENDAMENTO</button>
        </form>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fazer a mensagem de alerta sumir suavemente após 4 segundos
        const alerta = document.querySelector('.alerta');
        if (alerta) {
            setTimeout(function() {
                alerta.classList.add('fade-out');
                // Remover o elemento do layout após a transição terminar
                setTimeout(function() {
                    alerta.remove();
                }, 500);
            }, 4000);
        }

        const inputData = document.getElementById('data');
        const selectHora = document.getElementById('hora');
        const selectServico = document.getElementById('servico');
        const form = document.getElementById('form-agendamento');
        const btnConfirmar = document.getElementById('btn-confirmar-agendamento');

        // Dados do Plano e Limites passados pelo PHP para precificação em tempo real
        const planoAtivo = <?php echo json_encode($plano_ativo); ?>;
        const totalCabeloMes = <?php echo intval($total_cabelo_mes); ?>;
        const totalBarbaMes = <?php echo intval($total_barba_mes); ?>;

        // Função para atualizar dinamicamente o valor exibido na tela
        function atualizarPrecoEstimado() {
            const servico = selectServico.value;
            const visualizador = document.getElementById('visualizador-preco');
            const precoValor = document.getElementById('preco-estimado-valor');
            const precoStatus = document.getElementById('preco-estimado-status');
            
            if (!servico) {
                visualizador.style.display = 'none';
                return;
            }
            
            let valor = 0;
            let status = "";
            let corBorda = "var(--primary)"; // cor dourada padrão
            
            if (planoAtivo === 'Plano VIP Style') {
                valor = 0;
                status = "Incluso no seu plano VIP Style (Ilimitado) — R$ 0,00";
            } else if (planoAtivo === 'Plano Cavalheiro') {
                if (servico === 'Cabelo') {
                    if (totalCabeloMes >= 3) {
                        valor = 40;
                        status = `⚠️ Limite do plano atingido (${totalCabeloMes}/3 usados no mês) — Valor avulso`;
                        corBorda = "#ff9800"; // Laranja para aviso/cobrança
                    } else {
                        valor = 0;
                        status = `Incluso no seu plano Cavalheiro (${totalCabeloMes}/3 usados no mês) — R$ 0,00`;
                    }
                } else if (servico === 'Barba') {
                    if (totalBarbaMes >= 3) {
                        valor = 30;
                        status = `⚠️ Limite do plano atingido (${totalBarbaMes}/3 usados no mês) — Valor avulso`;
                        corBorda = "#ff9800"; // Laranja para aviso/cobrança
                    } else {
                        valor = 0;
                        status = `Incluso no seu plano Cavalheiro (${totalBarbaMes}/3 usados no mês) — R$ 0,00`;
                    }
                } else if (servico === 'Combo') {
                    if (totalCabeloMes >= 3 && totalBarbaMes >= 3) {
                        valor = 65;
                        status = `⚠️ Limites de Cabelo (${totalCabeloMes}/3) e Barba (${totalBarbaMes}/3) atingidos — Valor avulso`;
                        corBorda = "#ff9800";
                    } else if (totalCabeloMes >= 3) {
                        valor = 40;
                        status = `⚠️ Limite de Cabelo atingido (${totalCabeloMes}/3). Barba inclusa pelo plano — Paga apenas o corte`;
                        corBorda = "#ff9800";
                    } else if (totalBarbaMes >= 3) {
                        valor = 30;
                        status = `⚠️ Limite de Barba atingido (${totalBarbaMes}/3). Cabelo incluso pelo plano — Paga apenas a barba`;
                        corBorda = "#ff9800";
                    } else {
                        valor = 0;
                        status = `Combo incluso no seu plano Cavalheiro (Cabelo: ${totalCabeloMes}/3, Barba: ${totalBarbaMes}/3 usados) — R$ 0,00`;
                    }
                }
            } else if (planoAtivo === 'Plano Lenhador') {
                if (servico === 'Barba') {
                    if (totalBarbaMes >= 3) {
                        valor = 30;
                        status = `⚠️ Limite do plano atingido (${totalBarbaMes}/3 usados no mês) — Valor avulso`;
                        corBorda = "#ff9800"; // Laranja para aviso/cobrança
                    } else {
                        valor = 0;
                        status = `Incluso no seu plano Lenhador (${totalBarbaMes}/3 usados no mês) — R$ 0,00`;
                    }
                } else if (servico === 'Cabelo') {
                    valor = 40;
                    status = "Serviço não coberto pelo Plano Lenhador — Valor padrão";
                    corBorda = "#ff9800";
                } else if (servico === 'Combo') {
                    valor = 65;
                    status = "Combo não coberto pelo Plano Lenhador — Valor padrão";
                    corBorda = "#ff9800";
                }
            } else {
                // Sem plano
                if (servico === 'Cabelo') {
                    valor = 40;
                } else if (servico === 'Barba') {
                    valor = 30;
                } else if (servico === 'Combo') {
                    valor = 65;
                }
                status = "Sem assinatura ativa — Valor avulso";
                corBorda = "#ff9800";
            }
            
            // Exibir valores na tela formatados em PT-BR
            precoValor.textContent = `R$ ${valor.toFixed(2).replace('.', ',')}`;
            precoStatus.textContent = status;
            visualizador.style.borderLeftColor = corBorda;
            visualizador.style.display = 'block';
        }

        // Adicionar listener de mudança no serviço e executar imediatamente para inicialização
        selectServico.addEventListener('change', atualizarPrecoEstimado);
        atualizarPrecoEstimado();

        // Impedir seleção de dias anteriores a hoje no calendário nativo
        const hoje = new Date().toISOString().split('T')[0];
        inputData.setAttribute('min', hoje);

        // Guardar o horário selecionado anteriormente para recuperação em AJAX/F5
        const horaSelecionadaAnterior = "<?php echo isset($form_data['hora']) ? htmlspecialchars($form_data['hora']) : ''; ?>";

        // Função para carregar dinamicamente os horários livres via AJAX
        function atualizarHorarios() {
            const dataSelecionada = inputData.value;
            const servicoSelecionado = selectServico.value;

            // Se o serviço não estiver selecionado
            if (!servicoSelecionado) {
                selectHora.innerHTML = '<option value="">Selecione o serviço primeiro...</option>';
                selectHora.disabled = true;
                return;
            }

            // Se a data não estiver selecionada
            if (!dataSelecionada) {
                selectHora.innerHTML = '<option value="">Selecione uma data primeiro...</option>';
                selectHora.disabled = true;
                return;
            }

            selectHora.innerHTML = '<option value="">Buscando horários disponíveis...</option>';
            selectHora.disabled = true;

            fetch(`agendamento.php?obter_horarios=1&data=${dataSelecionada}&servico=${encodeURIComponent(servicoSelecionado)}`)
                .then(response => {
                    if (response.status === 401) {
                        window.location.href = 'login.php?aba=cadastro&aviso=agendamento';
                        throw new Error('Não autenticado');
                    }
                    if (!response.ok) throw new Error('Erro na requisição');
                    return response.json();
                })
                .then(horariosLivres => {
                    selectHora.innerHTML = '';

                    if (horariosLivres.length === 0) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = '❌ Nenhum horário livre para este dia';
                        selectHora.appendChild(option);
                        selectHora.disabled = true;
                    } else {
                        const optionDefault = document.createElement('option');
                        optionDefault.value = '';
                        optionDefault.textContent = 'Selecione o horário...';
                        selectHora.appendChild(optionDefault);

                        horariosLivres.forEach(hora => {
                            const option = document.createElement('option');
                            option.value = hora;
                            // Formata o horário de "14:00:00" para "14:00"
                            option.textContent = hora.substring(0, 5);
                            if (hora === horaSelecionadaAnterior) {
                                option.selected = true;
                            }
                            selectHora.appendChild(option);
                        });

                        selectHora.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar horários:', error);
                    selectHora.innerHTML = '<option value="">Erro ao carregar horários</option>';
                    selectHora.disabled = true;
                });
        }

        // Monitorar mudança na data ou no serviço
        inputData.addEventListener('change', atualizarHorarios);
        selectServico.addEventListener('change', atualizarHorarios);

        // Definir estado inicial correto e atualizar se houver valores pré-preenchidos
        atualizarHorarios();

        // Validação de data + prevenção de clique duplo no botão de envio
        let formSubmetido = false;
        form.addEventListener('submit', function(e) {
            if (formSubmetido) {
                e.preventDefault();
                return;
            }
            if (!validarData()) {
                e.preventDefault();
                return;
            }

            formSubmetido = true;
            btnConfirmar.disabled = true;
            btnConfirmar.textContent = 'PROCESSANDO...';
        });

        function validarData() {
            const dataSelecionada = inputData.value;
            const dataAtual = new Date();
            dataAtual.setHours(0, 0, 0, 0);

            const dataInserida = new Date(dataSelecionada + 'T00:00:00');

            if (dataInserida < dataAtual) {
                alert("Você não pode agendar em um dia que já passou!");
                return false;
            }
            return true;
        }

        // Máscara e formatação para o telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let sistema = e.target.value;

            // Remove tudo o que não for número
            sistema = sistema.replace(/\D/g, "");

            // Se não houver nada digitado, limpa o campo
            if (sistema.length === 0) {
                e.target.value = "";
                return;
            }

            // Aplica a formatação dinamicamente
            if (sistema.length <= 2) {
                e.target.value = "(" + sistema;
            } else if (sistema.length <= 6) {
                e.target.value = "(" + sistema.substring(0, 2) + ") " + sistema.substring(2);
            } else {
                e.target.value = "(" + sistema.substring(0, 2) + ") " + sistema.substring(2, 7) + "-" + sistema.substring(7, 11);
            }
        });
    });
</script>
</body>

</html>