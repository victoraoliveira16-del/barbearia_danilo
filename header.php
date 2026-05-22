<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barbearia Danilo</title>
    
    <link rel="stylesheet" href="global.css">

    <?php
    $pagina_atual = basename($_SERVER['PHP_SELF']);
    if ($pagina_atual == 'index.php') {
        echo '<link rel="stylesheet" href="home.css">';
    } elseif ($pagina_atual == 'galeria.php') {
        echo '<link rel="stylesheet" href="galeria.css">';
    } elseif ($pagina_atual == 'agendamento.php') {
        echo '<link rel="stylesheet" href="agendamento.css">';
    } elseif ($pagina_atual == 'planos.php') {
        echo '<link rel="stylesheet" href="planos.css">';
    } elseif ($pagina_atual == 'login.php') {
        echo '<link rel="stylesheet" href="login.css">';
    } elseif ($pagina_atual == 'painel.php') {
        echo '<link rel="stylesheet" href="painel.css">';
    }
    ?>
</head>
<body>

    <header>
        <div class="logo-container">
            <img src="logo.png" alt="Logo Barbearia" class="logo-img" onerror="this.style.display='none'">
            <span class="logo-texto"><a href="index.php" style="color:var(--primary); text-decoration:none; margin:0;">BARBER STYLE</a></span>
        </div>
        <nav style="display: flex; align-items: center;">
            <a href="index.php">Home</a>
            <a href="galeria.php">Galeria</a>
            <a href="planos.php">Planos</a>
            <a href="agendamento.php">Agendar</a>
            <?php if (isset($_SESSION['usuario_nome'])): ?>
                <?php if (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin'): ?>
                    <a href="painel.php" style="background: var(--primary); color: #0f0f0f; padding: 6px 15px; border-radius: 4px; margin-left: 15px; font-weight: 600; transition: 0.3s;">Painel Admin</a>
                <?php endif; ?>
                <span class="user-welcome" style="color: var(--primary); margin-left: 20px; font-weight: 600; font-size: 0.95rem;">Olá, <?php echo htmlspecialchars(explode(' ', $_SESSION['usuario_nome'])[0]); ?>!</span>
                <a href="logout.php" style="color: #ff5252; margin-left: 15px; font-weight: 600;">Sair</a>
            <?php else: ?>
                <a href="login.php" style="border: 1px solid var(--primary); padding: 6px 15px; border-radius: 4px; color: var(--primary); margin-left: 15px; transition: 0.3s;">Entrar</a>
            <?php endif; ?>
        </nav>
    </header>