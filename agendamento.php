<?php
include('conexao.php');

// Endpoint AJAX para buscar horários livres de uma data
if (isset($_GET['obter_horarios']) && isset($_GET['data'])) {
    header('Content-Type: application/json');
    $data_selecionada = $_GET['data'];

    // Todos os horários de atendimento da barbearia
    $todos_horarios = ["09:00:00", "10:00:00", "11:00:00", "14:00:00", "15:00:00", "16:00:00", "17:00:00", "18:00:00"];

    // Buscar horários já ocupados nessa data
    $stmt = $conn->prepare("SELECT hora_agendamento FROM agendamentos WHERE data_agendamento = ?");
    $stmt->bind_param("s", $data_selecionada);
    $stmt->execute();
    $result = $stmt->get_result();

    $ocupados = [];
    while ($row = $result->fetch_assoc()) {
        $ocupados[] = $row['hora_agendamento'];
    }
    $stmt->close();

    // Filtrar apenas os horários livres (que não estão no array de ocupados)
    $disponiveis = array_values(array_diff($todos_horarios, $ocupados));

    echo json_encode($disponiveis);
    exit;
}

include('header.php');

$mensagem = "";
$classe_mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $telefone = $_POST['telefone'];
    $servico = $_POST['servico'];
    $data = $_POST['data'];
    $hora = $_POST['hora'];

    $verificar = $conn->prepare("SELECT id FROM agendamentos WHERE data_agendamento = ? AND hora_agendamento = ?");
    $verificar->bind_param("ss", $data, $hora);
    $verificar->execute();
    $resultado = $verificar->get_result();

    if ($resultado->num_rows > 0) {
        $mensagem = "❌ Desculpe, este horário já está reservado. Escolha outro!";
        $classe_mensagem = "erro";
    } else {
        $usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null;
        
        // Definir o valor correspondente ao serviço selecionado
        $valor = 0.00;
        if ($servico === 'Cabelo') {
            $valor = 40.00;
        } elseif ($servico === 'Barba') {
            $valor = 30.00;
        } elseif ($servico === 'Combo') {
            $valor = 65.00;
        }

        $salvar = $conn->prepare("INSERT INTO agendamentos (usuario_id, nome, telefone, servico, valor, data_agendamento, hora_agendamento) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $salvar->bind_param("isssdss", $usuario_id, $nome, $telefone, $servico, $valor, $data, $hora);

        if ($salvar->execute()) {
            $mensagem = "✅ Agendamento realizado com sucesso! Esperamos você.";
            $classe_mensagem = "sucesso";
        } else {
            $mensagem = "❌ Erro ao agendar. Tente novamente.";
            $classe_mensagem = "erro";
        }
        $salvar->close();
    }
    $verificar->close();
}
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

        <form action="agendamento.php" method="POST" onsubmit="return validarData()">
            <div class="form-group">
                <label for="nome">Seu Nome:</label>
                <input type="text" id="nome" name="nome" required placeholder="Digite seu nome completo" value="<?php 
                    if ($classe_mensagem == 'erro') {
                        echo htmlspecialchars($nome);
                    } elseif (isset($_SESSION['usuario_nome'])) {
                        echo htmlspecialchars($_SESSION['usuario_nome']);
                    }
                ?>">
            </div>

            <div class="form-group">
                <label for="telefone">WhatsApp:</label>
                <input type="tel" id="telefone" name="telefone" required placeholder="(00) 99999-9999" value="<?php echo ($classe_mensagem == 'erro') ? htmlspecialchars($telefone) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="servico">Serviço Desejado:</label>
                <select id="servico" name="servico" required>
                    <option value="">Selecione...</option>
                    <option value="Cabelo" <?php echo ($classe_mensagem == 'erro' && $servico == 'Cabelo') ? 'selected' : ''; ?>>Apenas Cabelo - R$ 40,00</option>
                    <option value="Barba" <?php echo ($classe_mensagem == 'erro' && $servico == 'Barba') ? 'selected' : ''; ?>>Apenas Barba - R$ 30,00</option>
                    <option value="Combo" <?php echo ($classe_mensagem == 'erro' && $servico == 'Combo') ? 'selected' : ''; ?>>Cabelo & Barba - R$ 65,00</option>
                </select>
            </div>

            <div class="form-group">
                <label for="data">Data:</label>
                <input type="date" id="data" name="data" required value="<?php echo ($classe_mensagem == 'erro') ? htmlspecialchars($data) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="hora">Horário:</label>
                <select id="hora" name="hora" required disabled>
                    <option value="">Selecione uma data primeiro...</option>
                </select>
            </div>

            <button type="submit" class="btn btn-full">CONFIRMAR AGENDAMENTO</button>
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

        // Impedir seleção de dias anteriores a hoje no calendário nativo
        const hoje = new Date().toISOString().split('T')[0];
        inputData.setAttribute('min', hoje);

        // Função para carregar dinamicamente os horários livres via AJAX
        function atualizarHorarios() {
            const dataSelecionada = inputData.value;
            
            if (!dataSelecionada) {
                selectHora.innerHTML = '<option value="">Selecione uma data primeiro...</option>';
                selectHora.disabled = true;
                return;
            }

            selectHora.innerHTML = '<option value="">Buscando horários disponíveis...</option>';
            selectHora.disabled = true;

            fetch(`agendamento.php?obter_horarios=1&data=${dataSelecionada}`)
                .then(response => {
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

        // Monitorar mudança na data
        inputData.addEventListener('change', atualizarHorarios);

        // Se a página for recarregada e já possuir uma data pré-selecionada
        if (inputData.value) {
            atualizarHorarios();
        }
    });

    function validarData() {
        const dataSelecionada = document.getElementById('data').value;
        const dataAtual = new Date();
        dataAtual.setHours(0, 0, 0, 0);

        const dataInserida = new Date(dataSelecionada + 'T00:00:00');

        if (dataInserida < dataAtual) {
            alert("Você não pode agendar em um dia que já passou!");
            return false;
        }
        return true;
    }
</script>
</body>

</html>