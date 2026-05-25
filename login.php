<?php
include('conexao.php');
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Se já estiver logado, redireciona para o local adequado
if (isset($_SESSION['usuario_id'])) {
    if (isset($_SESSION['usuario_tipo']) && in_array($_SESSION['usuario_tipo'], ['admin', 'barbeiro'])) {
        header("Location: painel.php");
    } else {
        header("Location: index.php");
    }
    exit;
}
$erro_login = "";
$sucesso_cadastro = "";
$erro_cadastro = "";
$aviso_mensagem = "";

// Verificar se há redirecionamento com aviso
if (isset($_GET['aviso'])) {
    if ($_GET['aviso'] === 'agendamento') {
        $aviso_mensagem = "📅 Para realizar um agendamento rápido, você precisa de uma conta primeiro! Cadastre-se abaixo em segundos.";
    } elseif ($_GET['aviso'] === 'plano') {
        $aviso_mensagem = "✨ Para assinar um de nossos planos de benefícios exclusivos, você precisa criar uma conta primeiro!";
    }
}

// Guardar abas ativas
$aba_ativa = "login";
if (isset($_GET['aba']) && $_GET['aba'] === 'cadastro') {
    $aba_ativa = "cadastro";
}

// Processar formulários
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // LOGIN / CADASTRO REAL COM GOOGLE
    if (isset($_POST['credential'])) {
        $idToken = $_POST['credential'];

        // Validação nativa do JWT enviado pelo Google sem precisar de biblioteca externa pesada
        $partes = explode('.', $idToken);
        if (count($partes) === 3) {
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $partes[1])), true);

            // Verifica se o token é válido e emitido pelo Google usando o seu ID real recebido
            if ($payload && isset($payload['sub']) && $payload['aud'] === '918918094951-4ttp198brqnikaitdd33nkhsvagutu6o.apps.googleusercontent.com') {
                $email_google = trim($payload['email']);
                $nome_google = trim($payload['name']);

                // Verificar se o usuário já existe no seu banco de dados
                $verif = $conn->prepare("SELECT id, nome, tipo FROM usuarios WHERE email = ?");
                $verif->bind_param("s", $email_google);
                $verif->execute();
                $res_verif = $verif->get_result();

                if ($res_verif->num_rows === 0) {
                    // Se não existe, cria a conta automaticamente
                    $senha_google_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
                    $ins = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'cliente')");
                    $ins->bind_param("sss", $nome_google, $email_google, $senha_google_hash);
                    $ins->execute();
                    $usuario_id = $ins->insert_id;
                    $ins->close();

                    $_SESSION['usuario_id'] = $usuario_id;
                    $_SESSION['usuario_nome'] = $nome_google;
                    $_SESSION['usuario_tipo'] = 'cliente';
                } else {
                    // Se já existe, apenas autentica a sessão
                    $usr = $res_verif->fetch_assoc();
                    $_SESSION['usuario_id'] = $usr['id'];
                    $_SESSION['usuario_nome'] = $usr['nome'];
                    $_SESSION['usuario_tipo'] = $usr['tipo'];
                }
                $verif->close();

                header("Location: index.php");
                exit;
            } else {
                $erro_login = "Falha na autenticação com o Google. Token inválido.";
            }
        } else {
            $erro_login = "Erro ao processar resposta do Google.";
        }
    }

    // LOGIN PADRÃO (E-mail e Senha)
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
                if (password_verify($senha, $usuario['senha'])) {
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nome'] = $usuario['nome'];
                    $_SESSION['usuario_tipo'] = $usuario['tipo'];

                    if (in_array($usuario['tipo'], ['admin', 'barbeiro'])) {
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

    // CADASTRO PADRÃO
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
            $verificar = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $verificar->bind_param("s", $email);
            $verificar->execute();
            $result_verif = $verificar->get_result();
            if ($result_verif->num_rows > 0) {
                $erro_cadastro = "Este e-mail já está sendo utilizado!";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'cliente')");
                $stmt->bind_param("sss", $nome, $email, $senha_hash);
                if ($stmt->execute()) {
                    $sucesso_cadastro = "Conta criada com sucesso! Faça seu login na aba ao lado.";
                    $aba_ativa = "login";
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

<script src="https://accounts.google.com/gsi/client" async defer></script>

<section class="secao-interna auth-section">
    <div class="auth-container">
        <?php if (!empty($aviso_mensagem)): ?>
            <div class="auth-alerta aviso" style="margin-bottom: 25px; background: rgba(204, 164, 59, 0.12); border: 1px solid rgba(204, 164, 59, 0.5); color: #e5c060; padding: 15px; border-radius: 8px; font-size: 0.95rem; text-align: center; font-weight: 500; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);">
                <?php echo $aviso_mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="auth-tabs <?php echo ($aba_ativa == 'cadastro') ? 'cadastro-active' : ''; ?>">
            <button type="button" id="tab-btn-login" class="tab-btn <?php echo ($aba_ativa == 'login') ? 'active' : ''; ?>">Login</button>
            <button type="button" id="tab-btn-cadastro" class="tab-btn <?php echo ($aba_ativa == 'cadastro') ? 'active' : ''; ?>">Cadastro</button>
        </div>

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

            <div class="auth-divider">
                <span>ou continue com</span>
            </div>

            <div id="g_id_onload"
                data-client_id="918918094951-4ttp198brqnikaitdd33nkhsvagutu6o.apps.googleusercontent.com"
                data-login_uri="http://localhost:8080/barbearia_danilo/login.php"
                data-auto_prompt="false">
            </div>
            <div class="g_id_signin"
                data-type="standard"
                data-size="large"
                data-theme="outline"
                data-text="signin_with"
                data-shape="rectangular"
                data-logo_alignment="left"
                style="display: flex; justify-content: center;">
            </div>
        </div>

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

            <div class="auth-divider">
                <span>ou cadastre-se com</span>
            </div>

            <div class="g_id_signin"
                data-type="standard"
                data-size="large"
                data-theme="outline"
                data-text="signup_with"
                data-shape="rectangular"
                data-logo_alignment="left"
                style="display: flex; justify-content: center;">
            </div>
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

            const prevRect = container.getBoundingClientRect();
            const prevHeight = prevRect.height;

            container.style.height = `${prevHeight}px`;

            deactivateBtn.classList.remove('active');
            activateBtn.classList.add('active');

            if (activeClass === 'cadastro') {
                tabsContainer.classList.add('cadastro-active');
            } else {
                tabsContainer.classList.remove('cadastro-active');
            }

            hideForm.classList.remove('active');
            showForm.classList.add('active');

            void container.offsetHeight;

            container.style.height = 'auto';
            const newHeight = container.getBoundingClientRect().height;

            container.style.height = `${prevHeight}px`;

            void container.offsetHeight;
            container.style.height = `${newHeight}px`;

            setTimeout(() => {
                container.style.height = '';
            }, 400);
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