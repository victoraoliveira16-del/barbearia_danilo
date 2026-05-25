<?php
include('conexao.php');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buscar assinatura ativa do usuário logado
$assinatura_atual = null;
if (isset($_SESSION['usuario_id'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $stmt_busca = $conn->prepare("SELECT plano, preco FROM assinaturas WHERE usuario_id = ? ORDER BY data_assinatura DESC LIMIT 1");
    $stmt_busca->bind_param("i", $usuario_id);
    $stmt_busca->execute();
    $result_busca = $stmt_busca->get_result();
    if ($result_busca->num_rows > 0) {
        $assinatura_atual = $result_busca->fetch_assoc();
    }
    $stmt_busca->close();
}

// Endpoint POST para salvar a assinatura no banco de dados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_assinatura'])) {
    header('Content-Type: application/json');

    $nome = $_POST['nome'] ?? '';
    $plano = $_POST['plano'] ?? '';
    $preco = $_POST['preco'] ?? '';
    $metodo = $_POST['metodo'] ?? '';
    $usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null;

    if (!$usuario_id) {
        echo json_encode(["status" => "erro", "mensagem" => "Você precisa criar uma conta antes de assinar um plano!"]);
        exit;
    }

    if (empty($nome) || empty($plano)) {
        echo json_encode(["status" => "erro", "mensagem" => "Nome e Plano são obrigatórios!"]);
        exit;
    }

    // Validar se o usuário já possui o mesmo plano ativo
    if ($assinatura_atual && $assinatura_atual['plano'] === $plano) {
        echo json_encode(["status" => "erro", "mensagem" => "Você já possui uma assinatura ativa para este plano!"]);
        exit;
    }

    // REGRA DE HIERARQUIA: Lenhador < Cavalheiro < VIP (só upgrades)
    if ($assinatura_atual) {
        $hierarquia = ['Plano Lenhador' => 1, 'Plano Cavalheiro' => 2, 'Plano VIP Style' => 3];
        $nivel_atual = $hierarquia[$assinatura_atual['plano']] ?? 0;
        $nivel_novo = $hierarquia[$plano] ?? 0;

        if ($nivel_novo <= $nivel_atual) {
            echo json_encode(["status" => "erro", "mensagem" => "Você só pode fazer upgrade para um plano superior! Não é possível voltar para um plano inferior."]);
            exit;
        }
    }

    // Calcular preço correto com base no plano atual (diferença de preço para upgrades)
    $preco_final = $preco;
    if ($assinatura_atual) {
        if ($assinatura_atual['plano'] === 'Plano Lenhador' && $plano === 'Plano Cavalheiro') {
            // Cavalheiro (79.90) - Lenhador (59) = 20.90
            $preco_final = 'R$ 20,90/mês';
        } elseif ($assinatura_atual['plano'] === 'Plano Lenhador' && $plano === 'Plano VIP Style') {
            // VIP (129) - Lenhador (59) = 70
            $preco_final = 'R$ 70/mês';
        } elseif ($assinatura_atual['plano'] === 'Plano Cavalheiro' && $plano === 'Plano VIP Style') {
            // VIP (129) - Cavalheiro (79.90) = 49.10
            $preco_final = 'R$ 49,10/mês';
        }
    } else {
        // Primeira assinatura: preço cheio
        if ($plano === 'Plano Cavalheiro') {
            $preco_final = 'R$ 79,90/mês';
        } elseif ($plano === 'Plano VIP Style') {
            $preco_final = 'R$ 129/mês';
        } elseif ($plano === 'Plano Lenhador') {
            $preco_final = 'R$ 59/mês';
        }
    }

    $stmt = $conn->prepare("INSERT INTO assinaturas (usuario_id, nome_cliente, plano, preco, metodo_pagamento) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $usuario_id, $nome, $plano, $preco_final, $metodo);

    if ($stmt->execute()) {
        echo json_encode(["status" => "sucesso", "mensagem" => "Assinatura registrada com sucesso!"]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => "Erro ao registrar assinatura: " . $conn->error]);
    }

    $stmt->close();
    exit;
}

// Função auxiliar para renderizar botões de planos de acordo com as regras de negócio
// HIERARQUIA: Lenhador (1) < Cavalheiro (2) < VIP Style (3) — só upgrades permitidos
function renderBotaoPlano($plano_nome, $preco_padrao, $assinatura_atual)
{
    $hierarquia = ['Plano Lenhador' => 1, 'Plano Cavalheiro' => 2, 'Plano VIP Style' => 3];

    if (!$assinatura_atual) {
        // Sem plano ativo: pode assinar qualquer plano normalmente
        $label = ($plano_nome === 'Plano VIP Style') ? 'Assinar VIP' : 'Assinar Agora';
        $class = ($plano_nome === 'Plano VIP Style') ? 'btn-plano btn-premium btn-assinar-action' : 'btn-plano btn-assinar-action';
        $disabled = '';
        $preco_data = $preco_padrao;
    } else if ($assinatura_atual['plano'] === $plano_nome) {
        // Plano atual: mostra como ativo e desabilitado
        $label = 'Plano Ativo';
        $class = 'btn-plano btn-assinar-action btn-plano-ativo';
        $disabled = 'disabled';
        $preco_data = $preco_padrao;
    } else if (($hierarquia[$plano_nome] ?? 0) > ($hierarquia[$assinatura_atual['plano']] ?? 0)) {
        // Upgrade permitido: plano destino é superior ao atual
        $preco_upgrade = $preco_padrao;
        if ($assinatura_atual['plano'] === 'Plano Lenhador' && $plano_nome === 'Plano Cavalheiro') {
            $preco_upgrade = 'R$ 20,90/mês';
        } elseif ($assinatura_atual['plano'] === 'Plano Lenhador' && $plano_nome === 'Plano VIP Style') {
            $preco_upgrade = 'R$ 70/mês';
        } elseif ($assinatura_atual['plano'] === 'Plano Cavalheiro' && $plano_nome === 'Plano VIP Style') {
            $preco_upgrade = 'R$ 49,10/mês';
        }
        $label = 'Fazer Upgrade';
        $class = 'btn-plano btn-premium btn-assinar-action';
        $disabled = '';
        $preco_data = $preco_upgrade;
    } else {
        // Plano inferior ou lateral: bloqueado (downgrade não permitido)
        $label = 'Indisponível';
        $class = 'btn-plano btn-plano-indisponivel';
        $disabled = 'disabled';
        $preco_data = $preco_padrao;
    }
    echo "<button class='{$class}' data-plano='{$plano_nome}' data-preco='{$preco_data}' {$disabled}>{$label}</button>";
}
?>
<?php include('header.php'); ?>

<section id="planos" class="secao-interna">
    <h2>Nossos Planos de Assinatura</h2>
    <p>Seja um membro do nosso clube exclusivo e mantenha seu visual impecável com economia, praticidade e benefícios incríveis todos os meses.</p>

    <div class="grid-planos">
        <!-- Plano 1 -->
        <div class="plano-card">
            <div class="plano-tag">Clássico</div>
            <h3>Plano Cavalheiro</h3>
            <div class="plano-preco">
                <span class="cifra">R$</span>
                <span class="valor"><?php echo ($assinatura_atual && $assinatura_atual['plano'] === 'Plano Lenhador') ? '20,90' : '79,90'; ?></span>
                <span class="periodo">/mês<?php echo ($assinatura_atual && $assinatura_atual['plano'] === 'Plano Lenhador') ? ' (Upgrade)' : ''; ?></span>
            </div>
            <p class="plano-desc">Perfeito para o homem que faz questão de manter o corte sempre impecável.</p>
            <ul class="plano-beneficios">
                <li>✂️ Até 3 cortes de cabelo por mês</li>
                <li>🧔 Até 3 manutenções de barba por mês</li>
                <li>🚿 Lavagem especial inclusa</li>
                <li>📅 Agendamento prioritário no site</li>
                <li>☕ Café expresso cortesia em cada visita</li>
            </ul>
            <?php renderBotaoPlano('Plano Cavalheiro', 'R$ 79,90/mês', $assinatura_atual); ?>
        </div>

        <!-- Plano 2 -->
        <div class="plano-card premium">
            <div class="plano-tag premium-tag">Recomendado</div>
            <h3>Plano VIP Style</h3>
            <div class="plano-preco">
                <span class="cifra">R$</span>
                <span class="valor"><?php
                                    if ($assinatura_atual && $assinatura_atual['plano'] !== 'Plano VIP Style') {
                                        if ($assinatura_atual['plano'] === 'Plano Lenhador') {
                                            echo '70';
                                        } elseif ($assinatura_atual['plano'] === 'Plano Cavalheiro') {
                                            echo '49,10';
                                        } else {
                                            echo '129';
                                        }
                                    } else {
                                        echo '129';
                                    }
                                    ?></span>
                <span class="periodo">/mês<?php
                                            if (
                                                $assinatura_atual && $assinatura_atual['plano'] !== 'Plano VIP Style' &&
                                                ($assinatura_atual['plano'] === 'Plano Lenhador' || $assinatura_atual['plano'] === 'Plano Cavalheiro')
                                            ) {
                                                echo ' (Upgrade)';
                                            }
                                            ?></span>
            </div>
            <p class="plano-desc">O combo completo e ilimitado para quem exige o melhor em cuidados pessoais.</p>
            <ul class="plano-beneficios">
                <li>✂️ Cortes de cabelo ILIMITADOS</li>
                <li>🧔 Manutenções de barba ILIMITADAS</li>
                <li>🧴 Selagem e produtos finalizadores inclusos</li>
                <li>🍺 1 Cerveja gelada cortesia por visita</li>
                <li>❌ Sem fidelidade ou taxas de cancelamento</li>
            </ul>
            <?php renderBotaoPlano('Plano VIP Style', 'R$ 129/mês', $assinatura_atual); ?>
        </div>

        <!-- Plano 3 -->
        <div class="plano-card">
            <div class="plano-tag">Alinhado</div>
            <h3>Plano Lenhador</h3>
            <div class="plano-preco">
                <span class="cifra">R$</span>
                <span class="valor">59</span>
                <span class="periodo">/mês</span>
            </div>
            <p class="plano-desc">Focado nos cuidados essenciais para manter a barba macia, limpa e alinhada.</p>
            <ul class="plano-beneficios">
                <li>🧔 Até 3 manutenções de barba por mês</li>
                <li>🔥 Terapia de toalha quente e essências</li>
                <li>✂️ Alinhamento e limpeza do pezinho cortesia</li>
                <li>📅 Flexibilidade de dias e horários</li>
            </ul>
            <?php renderBotaoPlano('Plano Lenhador', 'R$ 59/mês', $assinatura_atual); ?>
        </div>
    </div>
</section>

<!-- Modal de Pagamento -->
<div id="modal-pagamento" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>

        <div class="checkout-header">
            <h3>Finalizar Assinatura</h3>
            <div class="plano-selecionado-info">
                <span id="nome-plano-checkout">Plano VIP Style</span>
                <span id="preco-plano-checkout">R$ 129/mês</span>
            </div>
        </div>

        <!-- Nome do Assinante para o Banco de Dados -->
        <div class="input-group checkout-input-nome-group">
            <label for="checkout-nome-cliente">Nome Completo do Assinante:</label>
            <input type="text" id="checkout-nome-cliente" placeholder="Digite seu nome completo" required class="checkout-input-nome" value="<?php echo isset($_SESSION['usuario_nome']) ? htmlspecialchars($_SESSION['usuario_nome']) : ''; ?>">
        </div>

        <div class="checkout-body">
            <!-- Métodos de Pagamento (Painel Esquerdo) -->
            <div class="payment-methods">
                <button type="button" class="method-btn active" data-method="credito">
                    💳 Cartão de Crédito
                </button>
                <button type="button" class="method-btn" data-method="debito">
                    🏦 Cartão de Débito
                </button>
                <button type="button" class="method-btn" data-method="pix">
                    ⚡ Pix Instantâneo
                </button>
                <button type="button" class="method-btn" data-method="boleto">
                    📄 Boleto Bancário
                </button>
                <button type="button" class="method-btn" data-method="paypal">
                    PayPal
                </button>
            </div>

            <!-- Formulários de Pagamento (Painel Direito) -->
            <div class="payment-details">
                <!-- Cartão de Crédito -->
                <div id="form-credito" class="method-form active">
                    <h4>Dados do Cartão de Crédito</h4>
                    <div class="input-group">
                        <label>Número do Cartão</label>
                        <input type="text" id="credito-numero" class="card-number-input" placeholder="0000 0000 0000 0000" maxlength="19">
                    </div>
                    <div class="input-group">
                        <label>Nome Impresso no Cartão</label>
                        <input type="text" id="credito-nome" placeholder="EX: DANILO S SOUZA">
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Validade</label>
                            <input type="text" id="credito-validade" class="card-expiry-input" placeholder="MM/AA" maxlength="5">
                        </div>
                        <div class="input-group">
                            <label>CVV</label>
                            <input type="text" id="credito-cvv" class="card-cvv-input" placeholder="123" maxlength="4">
                        </div>
                    </div>
                </div>

                <!-- Cartão de Débito -->
                <div id="form-debito" class="method-form">
                    <h4>Dados do Cartão de Débito</h4>
                    <div class="input-group">
                        <label>Número do Cartão</label>
                        <input type="text" id="debito-numero" class="card-number-input" placeholder="0000 0000 0000 0000" maxlength="19">
                    </div>
                    <div class="input-group">
                        <label>Nome Impresso no Cartão</label>
                        <input type="text" id="debito-nome" placeholder="EX: DANILO S SOUZA">
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Validade</label>
                            <input type="text" id="debito-validade" class="card-expiry-input" placeholder="MM/AA" maxlength="5">
                        </div>
                        <div class="input-group">
                            <label>CVV</label>
                            <input type="text" id="debito-cvv" class="card-cvv-input" placeholder="123" maxlength="4">
                        </div>
                    </div>
                </div>

                <!-- Pix -->
                <div id="form-pix" class="method-form">
                    <h4>Pagamento via Pix</h4>
                    <p class="pix-instruction">Escaneie o QR Code abaixo ou copie a chave Pix para realizar o pagamento instantâneo em sua conta bancária.</p>
                    <div class="pix-qr-container">
                        <img src="galeria/qrcode-pix.png" alt="QR Code Pix para pagamento" class="pix-qr-image">
                    </div>
                    <div class="pix-copiar-container">
                        <input type="text" readonly value="00020101021226870014br.gov.bcb.pix2565pix.barberstyle.com.br/assinaturas" id="pix-key-input">
                        <button type="button" id="btn-copiar-pix" class="btn-plano btn-copiar-checkout">Copiar Chave</button>
                    </div>
                </div>

                <!-- Boleto -->
                <div id="form-boleto" class="method-form">
                    <h4>Boleto Bancário</h4>
                    <p class="boleto-instruction">O boleto será gerado no vencimento e enviado mensalmente para o seu e-mail. Copie o código de barras abaixo para pagar agora.</p>
                    <div class="boleto-barcode-mock">
                        <div class="barcode-line"></div>
                        <div class="barcode-line w-2"></div>
                        <div class="barcode-line w-1"></div>
                        <div class="barcode-line w-3"></div>
                        <div class="barcode-line w-1"></div>
                        <div class="barcode-line w-2"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line w-2"></div>
                        <div class="barcode-line w-1"></div>
                        <div class="barcode-line w-3"></div>
                        <div class="barcode-line w-1"></div>
                        <div class="barcode-line"></div>
                    </div>
                    <div class="boleto-codigo-container">
                        <input type="text" readonly value="34191.79001 01043.513184 91020.150008 7 90220000012900" id="boleto-code-input">
                        <button type="button" id="btn-copiar-boleto" class="btn-plano btn-copiar-checkout">Copiar Linha</button>
                    </div>
                </div>

                <!-- PayPal -->
                <div id="form-paypal" class="method-form">
                    <h4>PayPal</h4>
                    <p class="paypal-instruction">Ao clicar em confirmar, você será redirecionado para o PayPal para autorizar o débito recorrente.</p>
                    <div class="paypal-btn-container">
                        <div class="paypal-action-btn">
                            <span class="pay-yellow">Pay</span><span class="pay-blue">Pal</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="checkout-footer">
            <button type="button" id="btn-cancelar-checkout" class="btn-plano btn-checkout-cancelar">Cancelar</button>
            <button type="button" id="btn-confirmar-checkout" class="btn-plano btn-premium btn-checkout-confirmar">Confirmar Pagamento</button>
        </div>

        <!-- Tela de Status (Loading / Sucesso) -->
        <div id="checkout-status-overlay" class="status-overlay">
            <div class="status-content">
                <div class="spinner" id="checkout-spinner"></div>
                <div class="success-icon" id="checkout-success-icon">✓</div>
                <div class="checkout-error-icon-custom" id="checkout-error-icon">✗</div>
                <h4 id="status-title">Processando Pagamento...</h4>
                <p id="status-desc">Aguarde enquanto conectamos com o provedor de pagamento seguro.</p>
                <a href="agendamento.php" id="btn-status-concluido" class="btn-plano btn-premium btn-status-concluido-custom">Agendar Meu Horário</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modal-pagamento');
        const closeBtn = document.querySelector('.close-btn');
        const cancelBtn = document.getElementById('btn-cancelar-checkout');
        const confirmBtn = document.getElementById('btn-confirmar-checkout');
        const btnsAssinar = document.querySelectorAll('.btn-assinar-action');

        const nomePlanoCheckout = document.getElementById('nome-plano-checkout');
        const precoPlanoCheckout = document.getElementById('preco-plano-checkout');

        const methodBtns = document.querySelectorAll('.method-btn');
        const methodForms = document.querySelectorAll('.method-form');

        const statusOverlay = document.getElementById('checkout-status-overlay');
        const spinner = document.getElementById('checkout-spinner');
        const successIcon = document.getElementById('checkout-success-icon');
        const statusTitle = document.getElementById('status-title');
        const statusDesc = document.getElementById('status-desc');
        const btnConcluido = document.getElementById('btn-status-concluido');
        const errorIcon = document.getElementById('checkout-error-icon');

        let planoAtual = "";
        let precoAtual = "";
        let checkoutSucesso = false;

        const isLogged = <?php echo isset($_SESSION['usuario_id']) ? 'true' : 'false'; ?>;

        // Abrir Modal e Popular Informações
        btnsAssinar.forEach(btn => {
            btn.addEventListener('click', function() {
                if (!isLogged) {
                    window.location.href = "login.php?aba=cadastro&aviso=plano";
                    return;
                }

                planoAtual = this.getAttribute('data-plano');
                precoAtual = this.getAttribute('data-preco');

                nomePlanoCheckout.textContent = planoAtual;
                precoPlanoCheckout.textContent = precoAtual;

                // Resetar estados do overlay de status
                statusOverlay.classList.remove('active');
                spinner.style.display = 'block';
                successIcon.style.display = 'none';
                errorIcon.style.display = 'none';
                statusTitle.textContent = "Processando Pagamento...";
                statusDesc.textContent = "Aguarde enquanto conectamos com o provedor de pagamento seguro.";
                btnConcluido.style.display = 'none';
                confirmBtn.style.display = 'inline-block';
                cancelBtn.style.display = 'inline-block';

                // Abrir o modal
                modal.style.display = 'block';
            });
        });

        // Alternar Métodos de Pagamento
        methodBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                methodBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const targetMethod = this.getAttribute('data-method');

                methodForms.forEach(form => {
                    form.classList.remove('active');
                    if (form.id === `form-${targetMethod}`) {
                        form.classList.add('active');
                    }
                });
            });
        });

        // Fechar Modal (recarrega a página se o checkout foi um sucesso)
        function fecharModal() {
            modal.style.display = 'none';
            if (checkoutSucesso) {
                window.location.reload();
            }
        }

        closeBtn.addEventListener('click', fecharModal);
        cancelBtn.addEventListener('click', fecharModal);

        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                fecharModal();
            }
        });

        // Copiar Chave Pix
        document.getElementById('btn-copiar-pix').addEventListener('click', function() {
            const input = document.getElementById('pix-key-input');
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value);

            const originalText = this.textContent;
            this.textContent = "Copiado! ✓";
            this.style.background = "#2e7d32";
            this.style.borderColor = "#2e7d32";
            this.style.color = "white";

            setTimeout(() => {
                this.textContent = originalText;
                this.style.background = "transparent";
                this.style.borderColor = "var(--primary)";
                this.style.color = "white";
            }, 2000);
        });

        // Copiar Linha Digitável Boleto
        document.getElementById('btn-copiar-boleto').addEventListener('click', function() {
            const input = document.getElementById('boleto-code-input');
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value);

            const originalText = this.textContent;
            this.textContent = "Copiado! ✓";
            this.style.background = "#2e7d32";
            this.style.borderColor = "#2e7d32";
            this.style.color = "white";

            setTimeout(() => {
                this.textContent = originalText;
                this.style.background = "transparent";
                this.style.borderColor = "var(--primary)";
                this.style.color = "white";
            }, 2000);
        });

        // Função auxiliar: Exibir overlay de erro temporário (3 segundos)
        function exibirErroOverlay(titulo, mensagem) {
            spinner.style.display = 'none';
            successIcon.style.display = 'none';
            errorIcon.style.display = 'block';
            statusTitle.textContent = titulo;
            statusDesc.textContent = mensagem;
            btnConcluido.style.display = 'none';
            statusOverlay.classList.add('active');
            confirmBtn.style.display = 'none';
            cancelBtn.style.display = 'none';

            setTimeout(() => {
                statusOverlay.classList.remove('active');
                errorIcon.style.display = 'none';
                spinner.style.display = 'block';
                statusTitle.textContent = 'Processando Pagamento...';
                statusDesc.textContent = 'Aguarde enquanto conectamos com o provedor de pagamento seguro.';
                confirmBtn.style.display = 'inline-block';
                cancelBtn.style.display = 'inline-block';
            }, 3000);
        }

        // Confirmar Pagamento com Integração Real no Banco de Dados
        confirmBtn.addEventListener('click', function() {
            const inputNomeCliente = document.getElementById('checkout-nome-cliente');
            const nomeCliente = inputNomeCliente.value.trim();

            if (!nomeCliente) {
                exibirErroOverlay('Erro no Pagamento ❌', 'Por favor, preencha o Nome Completo do Assinante.');
                return;
            }

            const activeMethodBtn = document.querySelector('.method-btn.active');
            const activeMethod = activeMethodBtn.getAttribute('data-method');
            const metodoNome = activeMethodBtn.textContent.trim();

            // Validar campos do cartão de crédito ou débito
            if (activeMethod === 'credito' || activeMethod === 'debito') {
                const prefix = activeMethod === 'credito' ? 'credito' : 'debito';
                const numero = document.getElementById(prefix + '-numero').value.trim();
                const nome = document.getElementById(prefix + '-nome').value.trim();
                const validade = document.getElementById(prefix + '-validade').value.trim();
                const cvv = document.getElementById(prefix + '-cvv').value.trim();

                const camposFaltando = [];
                if (!numero) camposFaltando.push('Número do Cartão');
                if (!nome) camposFaltando.push('Nome Impresso no Cartão');
                if (!validade) camposFaltando.push('Validade');
                if (!cvv) camposFaltando.push('CVV');

                if (camposFaltando.length > 0) {
                    exibirErroOverlay(
                        'Erro no Pagamento ❌',
                        'Preencha os campos obrigatórios: ' + camposFaltando.join(', ') + '.'
                    );
                    return;
                }
            }

            // Exibir loading
            statusOverlay.classList.add('active');
            confirmBtn.style.display = 'none';
            cancelBtn.style.display = 'none';

            // Preparar dados para salvar no banco
            const formData = new FormData();
            formData.append('criar_assinatura', '1');
            formData.append('nome', nomeCliente);
            formData.append('plano', planoAtual);
            formData.append('preco', precoAtual);
            formData.append('metodo', metodoNome);

            // Enviar via AJAX POST para registrar no banco de dados
            fetch('planos.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('Erro de conexão');
                    return response.json();
                })
                .then(res => {
                    setTimeout(() => {
                        spinner.style.display = 'none';

                        if (res.status === 'sucesso') {
                            checkoutSucesso = true;
                            successIcon.style.display = 'inline-block';
                            statusTitle.textContent = "Assinatura Ativada! 🎉";
                            statusDesc.innerHTML = `Tudo certo! Seu pagamento foi aprovado e o <strong>${planoAtual}</strong> foi registrado para <strong>${nomeCliente}</strong>.<br>Agora você já pode agendar o seu primeiro serviço!`;
                            btnConcluido.style.display = 'inline-block';

                            // Exibir o toast premium de sucesso na tela principal com tempo estendido (8 segundos) para não sumir rápido
                            showToast(`Assinatura do <strong>${planoAtual}</strong> ativada com sucesso para <strong>${nomeCliente}</strong>! 🎉`, 'sucesso', 8000);

                            inputNomeCliente.value = '';
                        } else {
                            exibirErroOverlay('Erro na Assinatura ❌', res.mensagem || 'Não foi possível registrar seu plano no sistema.');
                        }
                    }, 2000);
                })
                .catch(error => {
                    console.error('Erro de requisição:', error);
                    setTimeout(() => {
                        exibirErroOverlay('Erro de Conexão ❌', 'Houve uma falha ao enviar os dados de pagamento. Tente novamente.');
                    }, 2000);
                });
        });

        // Formatação simples para campos de cartão
        const numberInputs = document.querySelectorAll('.card-number-input');
        numberInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                let val = e.target.value.replace(/\D/g, '');
                let formatted = val.match(/.{1,4}/g);
                e.target.value = formatted ? formatted.join(' ') : '';
            });
        });

        const expiryInputs = document.querySelectorAll('.card-expiry-input');
        expiryInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                let val = e.target.value.replace(/\D/g, '');
                if (val.length > 2) {
                    e.target.value = val.substring(0, 2) + '/' + val.substring(2, 4);
                } else {
                    e.target.value = val;
                }
            });
        });

        const cvvInputs = document.querySelectorAll('.card-cvv-input');
        cvvInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        });

        // Função para exibir mensagem flutuante (Toast) com fadeIn e fadeOut
        function showToast(mensagem, tipo = 'sucesso', duracao = 8000) {
            const toast = document.createElement('div');
            toast.className = `toast-custom ${tipo}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <span class="toast-icon">${tipo === 'sucesso' ? '🎉' : '❌'}</span>
                    <span class="toast-text">${mensagem}</span>
                </div>
            `;
            document.body.appendChild(toast);
            
            // Ativar fadeIn com delay mínimo para transição suave
            setTimeout(() => {
                toast.classList.add('show');
            }, 50);
            
            // Programar fadeOut e remoção do DOM
            setTimeout(() => {
                toast.classList.remove('show');
                toast.classList.add('hide');
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }, duracao);
        }
    });
</script>
</body>

</html>