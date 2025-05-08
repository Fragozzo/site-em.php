<?php
// Configurações gerais para cookies e sessões
$cookie_lifetime = 60 * 60 * 24 * 30; // 30 dias
$cookie_path = '/';
$cookie_domain = '';
$cookie_secure = false; // Altere para true em produção com HTTPS
$cookie_httponly = true;

// Definindo timeout da sessão (2 horas)
ini_set('session.gc_maxlifetime', 7200);
session_set_cookie_params(7200, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);

session_start();

// Verifica se há cookies de "lembrar-me" e o usuário não está logado
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
            $_SESSION['ultimo_acesso'] = time();

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

$error = '';
$success = '';

// Função para carregar usuários
function carregarUsuarios()
{
    $usersFile = 'usuarios.json';
    if (file_exists($usersFile)) {
        $users = json_decode(file_get_contents($usersFile), true);
        if (!is_array($users)) {
            $users = [];
        }
        return $users;
    }
    return [];
}

// Função para salvar usuários
function salvarUsuarios($users)
{
    file_put_contents('usuarios.json', json_encode($users, JSON_PRETTY_PRINT));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    // Validações básicas
    if (empty($nome) || empty($email) || empty($senha)) {
        $error = "Todos os campos são obrigatórios!";
    } elseif (strlen($senha) < 6) {
        $error = "A senha deve ter pelo menos 6 caracteres!";
    } elseif ($senha !== $confirmarSenha) {
        $error = "As senhas não coincidem!";
    } else {
        $usuarios = carregarUsuarios();

        // Verificar se o email já está cadastrado
        $emailExiste = false;
        foreach ($usuarios as $usuario) {
            if ($usuario['email'] === $email) {
                $emailExiste = true;
                break;
            }
        }

        if ($emailExiste) {
            $error = "Este email já está cadastrado!";
        } else {
            // Cria novo usuário
            $novoUsuario = [
                'id' => count($usuarios) + 1,
                'nome' => $nome,
                'email' => $email,
                'senha' => password_hash($senha, PASSWORD_DEFAULT)
            ];

            // Adiciona ao array e salva
            $usuarios[] = $novoUsuario;
            salvarUsuarios($usuarios);

            $success = "Cadastro realizado com sucesso! <a href='login.php'>Faça login</a>";
        }
    }
}
?>

<?php include 'partials/head.php'; ?>

<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark"></>Registrar Nova Conta</h2>
                            <p class="text-muted">Preencha os campos abaixo para se registrar</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                </i><?= $success ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php else: ?>
                            <form method="post" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="nome" class="form-label">Nome</label>
                                    <div class="input-group">
                                        <span class="input-group-text"></span>
                                        <input type="text" class="form-control" id="nome" name="nome" placeholder="Digite seu nome completo" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                                    </div>
                                    <div class="invalid-feedback">Por favor, informe seu nome.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Digite um email válido" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="invalid-feedback">Por favor, informe um email válido.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="senha" class="form-label">Senha</label>
                                    <div class="input-group">
                                        <span class="input-group-text"></i></span>
                                        <input type="password" class="form-control" id="senha" name="senha" placeholder="Crie uma senha segura" minlength="6" required>
                                    </div>
                                    <div class="form-text text-muted"><small>A senha deve ter pelo menos 6 caracteres</small></div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
                                    <div class="input-group">
                                        <span class="input-group-text"></span>
                                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a senha criada" required>
                                    </div>
                                    <div class="invalid-feedback">Por favor, confirme sua senha.</div>
                                </div>

                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary py-2">
                                        Registrar
                                    </button>
                                </div>
                            </form>

                            <div class="login-link mt-4 text-center">
                                <p>Já tem uma conta? <a href="login.php" class="fw-bold">Faça login</a></p>
                            </div>
                        <?php endif; ?>
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

    <script>
        // Script para validação de formulário
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function(form) {
                    form.addEventListener('submit', function(event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }

                        // Validação personalizada para senhas coincidentes
                        var senha = document.getElementById('senha')
                        var confirmarSenha = document.getElementById('confirmar_senha')

                        if (senha.value !== confirmarSenha.value) {
                            confirmarSenha.setCustomValidity('As senhas não coincidem!')
                            event.preventDefault()
                            event.stopPropagation()
                        } else {
                            confirmarSenha.setCustomValidity('')
                        }

                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>

</html>