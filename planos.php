<?php
include('conexao.php');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Endpoint POST para salvar a assinatura no banco de dados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_assinatura'])) {
    header('Content-Type: application/json');
    
    $nome = $_POST['nome'] ?? '';
    $plano = $_POST['plano'] ?? '';
    $preco = $_POST['preco'] ?? '';
    $metodo = $_POST['metodo'] ?? '';
    $usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null;
    
    if (empty($nome) || empty($plano)) {
        echo json_encode(["status" => "erro", "mensagem" => "Nome e Plano são obrigatórios!"]);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO assinaturas (usuario_id, nome_cliente, plano, preco, metodo_pagamento) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $usuario_id, $nome, $plano, $preco, $metodo);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "sucesso", "mensagem" => "Assinatura registrada com sucesso!"]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => "Erro ao registrar assinatura: " . $conn->error]);
    }
    
    $stmt->close();
    exit;
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
                <span class="valor">69</span>
                <span class="periodo">/mês</span>
            </div>
            <p class="plano-desc">Perfeito para o homem que faz questão de manter o corte sempre impecável.</p>
            <ul class="plano-beneficios">
                <li>✂️ Até 2 cortes de cabelo por mês</li>
                <li>🚿 Lavagem especial inclusa</li>
                <li>📅 Agendamento prioritário no site</li>
                <li>☕ Café expresso cortesia em cada visita</li>
            </ul>
            <button class="btn-plano btn-assinar-action" data-plano="Plano Cavalheiro" data-preco="R$ 69/mês">Assinar Agora</button>
        </div>

        <!-- Plano 2 -->
        <div class="plano-card premium">
            <div class="plano-tag premium-tag">Recomendado</div>
            <h3>Plano VIP Style</h3>
            <div class="plano-preco">
                <span class="cifra">R$</span>
                <span class="valor">129</span>
                <span class="periodo">/mês</span>
            </div>
            <p class="plano-desc">O combo completo e ilimitado para quem exige o melhor em cuidados pessoais.</p>
            <ul class="plano-beneficios">
                <li>✂️ Cortes de cabelo ILIMITADOS</li>
                <li>🧔 Manutenções de barba ILIMITADAS</li>
                <li>🧴 Selagem e produtos finalizadores inclusos</li>
                <li>🍺 1 Cerveja gelada cortesia por visita</li>
                <li>❌ Sem fidelidade ou taxas de cancelamento</li>
            </ul>
            <button class="btn-plano btn-premium btn-assinar-action" data-plano="Plano VIP Style" data-preco="R$ 129/mês">Assinar VIP</button>
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
            <button class="btn-plano btn-assinar-action" data-plano="Plano Lenhador" data-preco="R$ 59/mês">Assinar Agora</button>
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
        <div class="input-group" style="margin-bottom: 25px;">
            <label for="checkout-nome-cliente">Nome Completo do Assinante:</label>
            <input type="text" id="checkout-nome-cliente" placeholder="Digite seu nome completo" required style="width: 100%; padding: 12px; background: #252528; border: 1px solid rgba(255, 255, 255, 0.08); color: white; border-radius: 6px; font-size: 0.95rem; outline: none;" value="<?php echo isset($_SESSION['usuario_nome']) ? htmlspecialchars($_SESSION['usuario_nome']) : ''; ?>">
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
                        <input type="text" class="card-number-input" placeholder="0000 0000 0000 0000" maxlength="19">
                    </div>
                    <div class="input-group">
                        <label>Nome Impresso no Cartão</label>
                        <input type="text" placeholder="EX: DANILO S SOUZA">
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Validade</label>
                            <input type="text" class="card-expiry-input" placeholder="MM/AA" maxlength="5">
                        </div>
                        <div class="input-group">
                            <label>CVV</label>
                            <input type="text" class="card-cvv-input" placeholder="123" maxlength="4">
                        </div>
                    </div>
                </div>

                <!-- Cartão de Débito -->
                <div id="form-debito" class="method-form">
                    <h4>Dados do Cartão de Débito</h4>
                    <div class="input-group">
                        <label>Número do Cartão</label>
                        <input type="text" class="card-number-input" placeholder="0000 0000 0000 0000" maxlength="19">
                    </div>
                    <div class="input-group">
                        <label>Nome Impresso no Cartão</label>
                        <input type="text" placeholder="EX: DANILO S SOUZA">
                    </div>
                    <div class="row">
                        <div class="input-group">
                            <label>Validade</label>
                            <input type="text" class="card-expiry-input" placeholder="MM/AA" maxlength="5">
                        </div>
                        <div class="input-group">
                            <label>CVV</label>
                            <input type="text" class="card-cvv-input" placeholder="123" maxlength="4">
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
                        <button type="button" id="btn-copiar-pix" class="btn-plano" style="padding: 10px; font-size: 0.8rem;">Copiar Chave</button>
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
                        <button type="button" id="btn-copiar-boleto" class="btn-plano" style="padding: 10px; font-size: 0.8rem;">Copiar Linha</button>
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
            <button type="button" id="btn-cancelar-checkout" class="btn-plano" style="max-width: 150px; background: transparent; border: 1px solid #444;">Cancelar</button>
            <button type="button" id="btn-confirmar-checkout" class="btn-plano btn-premium" style="max-width: 250px;">Confirmar Pagamento</button>
        </div>

        <!-- Tela de Status (Loading / Sucesso) -->
        <div id="checkout-status-overlay" class="status-overlay">
            <div class="status-content">
                <div class="spinner" id="checkout-spinner"></div>
                <div class="success-icon" id="checkout-success-icon">✓</div>
                <h4 id="status-title">Processando Pagamento...</h4>
                <p id="status-desc">Aguarde enquanto conectamos com o provedor de pagamento seguro.</p>
                <a href="agendamento.php" id="btn-status-concluido" class="btn-plano btn-premium" style="display:none; text-decoration: none; margin-top: 10px;">Agendar Meu Horário</a>
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
        
        let planoAtual = "";
        let precoAtual = "";

        // Abrir Modal e Popular Informações
        btnsAssinar.forEach(btn => {
            btn.addEventListener('click', function() {
                planoAtual = this.getAttribute('data-plano');
                precoAtual = this.getAttribute('data-preco');
                
                nomePlanoCheckout.textContent = planoAtual;
                precoPlanoCheckout.textContent = precoAtual;
                
                // Resetar estados do overlay de status
                statusOverlay.classList.remove('active');
                spinner.style.display = 'block';
                successIcon.style.display = 'none';
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

        // Fechar Modal
        function fecharModal() {
            modal.style.display = 'none';
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

        // Confirmar Pagamento com Integração Real no Banco de Dados
        confirmBtn.addEventListener('click', function() {
            const inputNomeCliente = document.getElementById('checkout-nome-cliente');
            const nomeCliente = inputNomeCliente.value.trim();

            if (!nomeCliente) {
                alert("Por favor, preencha o Nome Completo do Assinante!");
                inputNomeCliente.focus();
                return;
            }

            const activeMethodBtn = document.querySelector('.method-btn.active');
            const activeMethod = activeMethodBtn.getAttribute('data-method');
            const metodoNome = activeMethodBtn.textContent.trim();

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
                // Simulação suave de validação financeira (2 segundos)
                setTimeout(() => {
                    spinner.style.display = 'none';
                    
                    if (res.status === 'sucesso') {
                        successIcon.style.display = 'inline-block';
                        statusTitle.textContent = "Assinatura Ativada! 🎉";
                        statusDesc.innerHTML = `Tudo certo! Seu pagamento foi aprovado e o <strong>${planoAtual}</strong> foi registrado para <strong>${nomeCliente}</strong>.<br>Agora você já pode agendar o seu primeiro serviço!`;
                        btnConcluido.style.display = 'inline-block';
                        
                        // Limpar campo de identificação
                        inputNomeCliente.value = '';
                    } else {
                        statusTitle.textContent = "Erro na Assinatura ❌";
                        statusDesc.textContent = res.mensagem || "Não foi possível registrar seu plano no sistema.";
                        confirmBtn.style.display = 'inline-block';
                        cancelBtn.style.display = 'inline-block';
                        statusOverlay.classList.remove('active');
                        alert("Erro do Servidor: " + res.mensagem);
                    }
                }, 2000);
            })
            .catch(error => {
                console.error('Erro de requisição:', error);
                setTimeout(() => {
                    spinner.style.display = 'none';
                    statusTitle.textContent = "Erro de Conexão ❌";
                    statusDesc.textContent = "Houve uma falha ao enviar os dados de pagamento. Tente novamente.";
                    confirmBtn.style.display = 'inline-block';
                    cancelBtn.style.display = 'inline-block';
                    statusOverlay.classList.remove('active');
                    alert("Erro de conexão com o banco de dados. Verifique a tabela 'assinaturas'.");
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
    });
</script>
</body>
</html>
