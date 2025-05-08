<?php include 'partials/head.php'; ?>

<body>
    <div class="container">
        <?php
        session_start();

        // Configurações gerais para cookies
        $cookie_lifetime = 60 * 60 * 24 * 30; // 30 dias
        $cookie_path = '/';
        $cookie_domain = '';
        $cookie_secure = false; // Altere para true em produção com HTTPS
        $cookie_httponly = true;

        // Remover cookies de "lembrar-me"
        if (isset($_COOKIE['remember_me_id']) && isset($_COOKIE['remember_me_token'])) {
            setcookie('remember_me_id', '', time() - 3600, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
            setcookie('remember_me_token', '', time() - 3600, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
        }

        // Destruir a sessão
        session_destroy();
        header('Location: login.php');
        exit;
        ?>
        <?php include 'partials/footer.php'; ?>
    </div>
</body>