<?php
include('conexao.php');
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Se já estiver logado, redireciona para o local adequado
if (isset($_SESSION['usuario_id'])) {
    if (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin') {
        header("Location: painel.php");
    } else {
        header("Location: index.php");
    }
    exit;
}
$erro_login = "";
$sucesso_cadastro = "";
$erro_cadastro = "";
// Guardar abas ativas
$aba_ativa = "login";
// Processar formulários
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // LOGIN
    if (isset($_POST['acao_login'])) {
        $aba_ativa = "login";
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];
        if (empty($email) || empty($senha)) {
            $erro_login = "Por favor, preencha todos os campos!";
        } else {
            $stmt = $conn->prepare("SELECT id, nome, senha, tipo FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $usuario = $result->fetch_assoc();
                // Validar a senha com password_verify (segurança máxima)
                if (password_verify($senha, $usuario['senha'])) {
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nome'] = $usuario['nome'];
                    $_SESSION['usuario_tipo'] = $usuario['tipo'];
                    
                    if ($usuario['tipo'] === 'admin') {
                        header("Location: painel.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                } else {
                    $erro_login = "E-mail ou senha incorretos!";
                }
            } else {
                $erro_login = "E-mail ou senha incorretos!";
            }
            $stmt->close();
        }
    }

    // CADASTRO
    elseif (isset($_POST['acao_cadastro'])) {
        $aba_ativa = "cadastro";
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
            $erro_cadastro = "Por favor, preencha todos os campos!";
        } elseif ($senha !== $confirmar_senha) {
            $erro_cadastro = "As senhas não coincidem!";
        } elseif (strlen($senha) < 6) {
            $erro_cadastro = "A senha deve ter no mínimo 6 caracteres por segurança!";
        } else {
            // Verificar se o email já existe
            $verificar = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $verificar->bind_param("s", $email);
            $verificar->execute();
            $result_verif = $verificar->get_result();
            if ($result_verif->num_rows > 0) {
                $erro_cadastro = "Este e-mail já está sendo utilizado!";
            } else {
                // Criptografar a senha usando BCRYPT
                $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $nome, $email, $senha_hash);
                if ($stmt->execute()) {
                    $sucesso_cadastro = "Conta criada com sucesso! Faça seu login na aba ao lado.";
                    $aba_ativa = "login"; // Redireciona para aba de login com sucesso
                } else {
                    $erro_cadastro = "Ocorreu um erro ao criar a conta. Tente novamente.";
                }
                $stmt->close();
            }
            $verificar->close();
        }
    }
}
include('header.php');
?>
<section class="secao-interna auth-section">
    <div class="auth-container">
        <!-- Alternadores de Abas -->
        <div class="auth-tabs <?php echo ($aba_ativa == 'cadastro') ? 'cadastro-active' : ''; ?>">
            <button type="button" id="tab-btn-login" class="tab-btn <?php echo ($aba_ativa == 'login') ? 'active' : ''; ?>">Login</button>
            <button type="button" id="tab-btn-cadastro" class="tab-btn <?php echo ($aba_ativa == 'cadastro') ? 'active' : ''; ?>">Cadastro</button>
        </div>
        <!-- Formulário de LOGIN -->
        <div id="auth-form-login" class="auth-card <?php echo ($aba_ativa == 'login') ? 'active' : ''; ?>">
            <h3>Acessar Minha Conta</h3>
            <p class="auth-subtitle">Entre para gerenciar seus planos e agendar horários.</p>
            <?php if (!empty($erro_login)): ?>
                <div class="auth-alerta erro"><?php echo $erro_login; ?></div>
            <?php endif; ?>
            <?php if (!empty($sucesso_cadastro)): ?>
                <div class="auth-alerta sucesso"><?php echo $sucesso_cadastro; ?></div>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <input type="hidden" name="acao_login" value="1">

                <div class="form-group">
                    <label for="login-email">E-mail:</label>
                    <input type="email" id="login-email" name="email" required placeholder="seuemail@exemplo.com" value="<?php echo isset($_POST['email']) && isset($_POST['acao_login']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="login-senha">Senha:</label>
                    <input type="password" id="login-senha" name="senha" required placeholder="Digite sua senha">
                </div>
                <button type="submit" class="btn btn-full" style="margin-top: 10px;">ENTRAR</button>
            </form>
        </div>
        <!-- Formulário de CADASTRO -->
        <div id="auth-form-cadastro" class="auth-card <?php echo ($aba_ativa == 'cadastro') ? 'active' : ''; ?>">
            <h3>Criar Nova Conta</h3>
            <p class="auth-subtitle">Cadastre-se para aproveitar todos os benefícios do nosso clube.</p>
            <?php if (!empty($erro_cadastro)): ?>
                <div class="auth-alerta erro"><?php echo $erro_cadastro; ?></div>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <input type="hidden" name="acao_cadastro" value="1">
                <div class="form-group">
                    <label for="cadastro-nome">Nome Completo:</label>
                    <input type="text" id="cadastro-nome" name="nome" required placeholder="Digite seu nome completo" value="<?php echo isset($_POST['nome']) && isset($_POST['acao_cadastro']) ? htmlspecialchars($_POST['nome']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="cadastro-email">E-mail:</label>
                    <input type="email" id="cadastro-email" name="email" required placeholder="seuemail@exemplo.com" value="<?php echo isset($_POST['email']) && isset($_POST['acao_cadastro']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="cadastro-senha">Escolha uma Senha (mín. 6 caracteres):</label>
                    <input type="password" id="cadastro-senha" name="senha" required placeholder="Crie uma senha segura">
                </div>
                <div class="form-group">
                    <label for="cadastro-confirmar-senha">Confirme a Senha:</label>
                    <input type="password" id="cadastro-confirmar-senha" name="confirmar_senha" required placeholder="Repita a senha criada">
                </div>
                <button type="submit" class="btn btn-full" style="margin-top: 10px;">CRIAR CONTA</button>
            </form>
        </div>
    </div>
</section>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnLogin = document.getElementById('tab-btn-login');
        const btnCadastro = document.getElementById('tab-btn-cadastro');
        const formLogin = document.getElementById('auth-form-login');
        const formCadastro = document.getElementById('auth-form-cadastro');
        const container = document.querySelector('.auth-container');
        const tabsContainer = document.querySelector('.auth-tabs');

        function switchTab(showForm, hideForm, activateBtn, deactivateBtn, activeClass) {
            if (showForm.classList.contains('active')) return;

            // Mede a altura atual com precisão decimal
            const prevRect = container.getBoundingClientRect();
            const prevHeight = prevRect.height;
            
            // Fixa a altura do contêiner antes de alterar o estado dos elementos
            container.style.height = `${prevHeight}px`;

            // Alterna os botões e abas ativos
            deactivateBtn.classList.remove('active');
            activateBtn.classList.add('active');

            if (activeClass === 'cadastro') {
                tabsContainer.classList.add('cadastro-active');
            } else {
                tabsContainer.classList.remove('cadastro-active');
            }

            // Alterna o formulário visível
            hideForm.classList.remove('active');
            showForm.classList.add('active');

            // Força o reflow para aplicar as alterações de layout
            void container.offsetHeight;

            // Mede temporariamente a nova altura necessária
            container.style.height = 'auto';
            const newHeight = container.getBoundingClientRect().height;

            // Restaura para a altura original
            container.style.height = `${prevHeight}px`;

            // Força novo reflow e define a nova altura para a animação do CSS acontecer
            void container.offsetHeight;
            container.style.height = `${newHeight}px`;

            // Remove a propriedade height após a animação de transição acabar para manter a responsividade
            setTimeout(() => {
                container.style.height = '';
            }, 500); // Deve bater com o tempo de 0.5s definido no CSS
        }

        btnLogin.addEventListener('click', function() {
            switchTab(formLogin, formCadastro, btnLogin, btnCadastro, 'login');
        });

        btnCadastro.addEventListener('click', function() {
            switchTab(formCadastro, formLogin, btnCadastro, btnLogin, 'cadastro');
        });
    });
</script>
</body>

</html>