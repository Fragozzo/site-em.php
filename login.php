<?php
session_start();

// Configurações gerais para cookies e sessões


// Definindo timeout da sessão (2 horas)


$error = '';

// Verifica se o usuário foi redirecionado por timeout
if (isset($_GET['timeout'])) {
    $error = "Sua sessão expirou por inatividade. Por favor, faça login novamente.";
}

// Função para logout
if (isset($_GET['logout'])) {
    // Remover cookies de "lembrar-me"
    if (isset($_COOKIE['remember_me_id']) && isset($_COOKIE['remember_me_token'])) {
        setcookie('remember_me_id', '', time() - 3600, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
        setcookie('remember_me_token', '', time() - 3600, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
    }

    // Destruir a sessão
    session_destroy();
    header('Location: login.php');
    exit;
}

// Função para gerar token aleatório
function gerarToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

// Função para carregar usuários
function carregarUsuarios()
{
    $usersFile = 'usuarios.json';
    if (file_exists($usersFile)) {
        $users = json_decode(file_get_contents($usersFile), true);
        if (!is_array($users)) {
            $users = [];
        }
        // Adiciona um ID a cada usuário se não existir
        foreach ($users as $key => $user) {
            if (!isset($user['id'])) {
                $users[$key]['id'] = $key + 1;
            }
        }
        return $users;
    }
    return [];
}

// Função para salvar usuários
function salvarUsuarios($usuarios)
{
    file_put_contents('usuarios.json', json_encode($usuarios, JSON_PRETTY_PRINT));
}

// Verificar se há cookies de "lembrar-me" e o usuário não está logado
if (!isset($_SESSION['usuario_id']) && isset($_COOKIE['remember_me_id']) && isset($_COOKIE['remember_me_token'])) {
    $user_id = $_COOKIE['remember_me_id'];
    $token = $_COOKIE['remember_me_token'];
    $usuarios = carregarUsuarios();

    foreach ($usuarios as $usuario) {
        if (
            isset($usuario['id']) && $usuario['id'] == $user_id &&
            isset($usuario['remember_token']) && $usuario['remember_token'] === $token
        ) {
            // Token válido, iniciar sessão
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];

            // Regenerar ID de sessão por segurança
            session_regenerate_id(true);

            // Redirecionar para a página de tarefas
            header("Location: tarefas.php");
            exit();
        }
    }

    // Se chegou aqui, o token é inválido - limpar cookies
    setcookie('remember_me_id', '', time() - 3600, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
    setcookie('remember_me_token', '', time() - 3600, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
}

// Verifica se o usuário já está logado
if (isset($_SESSION['usuario_id'])) {
    header('Location: tarefas.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']) ? true : false;

    $usuarios = carregarUsuarios();
    $autenticado = false;

    foreach ($usuarios as $key => &$usuario) {
        // Verifica se o email corresponde
        if ($usuario['email'] === $email) {
            // Verifica a senha usando password_verify para senhas com hash
            if (password_verify($password, $usuario['senha'])) {
                $autenticado = true;

                // Regenerar ID de sessão por segurança
                session_regenerate_id(true);

                // Configura as variáveis de sessão necessárias
                $_SESSION['usuario_id'] = $usuario['id'] ?? ($key + 1);
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['ultimo_acesso'] = time();

                // Se "lembrar-me" estiver marcado, configurar cookies
                if ($remember_me) {
                    $token = gerarToken();
                    $usuario['remember_token'] = $token;

                    // Definir cookies para 30 dias
                    setcookie(
                        'remember_me_id',
                        $_SESSION['usuario_id'],
                        time() + $cookie_lifetime,
                        $cookie_path,
                        $cookie_domain,
                        $cookie_secure,
                        $cookie_httponly
                    );

                    setcookie(
                        'remember_me_token',
                        $token,
                        time() + $cookie_lifetime,
                        $cookie_path,
                        $cookie_domain,
                        $cookie_secure,
                        $cookie_httponly
                    );

                    // Salvar o token no arquivo de usuários
                    salvarUsuarios($usuarios);
                }

                // Redireciona para a página de tarefas
                header("Location: tarefas.php");
                exit();
            }
        }
    }

    if (!$autenticado) {
        $error = "Email ou senha inválidos!";
    }
}
?>

<?php include 'partials/head.php'; ?>

<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-black">Planilha de Tarefas</h2>
                            <p class="text-white">Faça login para acessar sua conta</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"></span>
                                    <input type="email" class="form-control" id="username" name="username" placeholder="seu@email.com" required>
                                </div>
                                <div class="invalid-feedback">Por favor, informe um email válido.</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Digite sua senha" required>
                                </div>
                                <div class="invalid-feedback">Por favor, informe sua senha.</div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">Lembrar-me</label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary py-2">
                                    Entrar
                                </button>
                            </div>
                        </form>

                        <div class="register-link mt-4 text-center">
                            <p>Ainda não tem uma conta? <a href="registro.php" class="fw-bold">Registre-se</a></p>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="index.php" class="text-decoration-none">
                        Voltar para a Página Inicial
                    </a>
                </div>
            </div>
        </div>
        <?php include 'partials/footer.php'; ?>
    </div>

    <!-- Bootstrap JS Bundle com Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <?php
    // Validação do formulário em PHP
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $errors = [];

        // Valida o campo de email
        if (empty($_POST['username'])) {
            $errors['username'] = "Por favor, informe um email válido.";
        } elseif (!filter_var($_POST['username'], FILTER_VALIDATE_EMAIL)) {
            $errors['username'] = "O email informado não é válido.";
        }

        // Valida o campo de senha
        if (empty($_POST['password'])) {
            $errors['password'] = "Por favor, informe sua senha.";
        }

        // Se houver erros, exibe as mensagens
        if (!empty($errors)) {
            foreach ($errors as $field => $error) {
                echo "<div class='alert alert-danger'>$error</div>";
            }
        }
    }
    ?>
</body>

</html>