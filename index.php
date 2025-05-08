<?php
/*
Integrantes do grupo:
- Allan Vogt     RGM: 40658597
- Vitor Emanuel  RGM: 40338207
- Eduardo Fragozo RGM: 42205492 
*/


session_start();

// Configurações gerais para cookies e sessões
$cookie_lifetime = 60 * 60 * 24 * 30; // 30 dias
$cookie_path = '/';
$cookie_domain = '';
$cookie_secure = false; // Altere para true em produção com HTTPS
$cookie_httponly = true;

// Variáveis para controle de estado da página
$usuario_logado = false;
$nome_usuario = '';

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

// Verifica se o usuário está logado pela sessão
if (isset($_SESSION['usuario_id'])) {
    $usuario_logado = true;
    $nome_usuario = $_SESSION['usuario_nome'] ?? 'Usuário';
}
// Verifica se existe cookie "lembrar-me"
else if (isset($_COOKIE['remember_me_id']) && isset($_COOKIE['remember_me_token'])) {
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

            $usuario_logado = true;
            $nome_usuario = $usuario['nome'];
            break;
        }
    }

    // Se chegou aqui e não autenticou, o token é inválido - limpar cookies
    if (!$usuario_logado) {
        setcookie('remember_me_id', '', time() - 3600, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
        setcookie('remember_me_token', '', time() - 3600, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
    }
}
?>
<?php include 'partials/head.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4">
        <!-- Navbar -->
        <nav class="bg-white shadow-md rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center">
                <a href="index.php" class="text-xl font-bold text-blue-600">Sistema de Tarefas</a>
                <button class="block lg:hidden text-gray-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                    </svg>
                </button>
                <ul class="hidden lg:flex space-x-4">
                    <?php if ($usuario_logado): ?>
                        <li class="text-gray-700 uppercase">Olá, <?= htmlspecialchars($nome_usuario); ?>!</li>
                        <li>
                            <a href="tarefas.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Minhas Tarefas</a>
                        </li>
                        <li>
                            <a href="logout.php" class="border border-gray-400 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-100">Sair</a>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="login.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Entrar</a>
                        </li>
                        <li>
                            <a href="registro.php" class="border border-gray-400 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-100">Registrar</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>

        <!-- Campo de adicionar tarefa -->
        <div class="mb-6">
            <form class="flex items-center bg-white shadow-md rounded-lg p-4">
                <input type="text" class="flex-grow border border-gray-300 rounded-lg px-4 py-2 mr-4 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Adicione uma nova tarefa">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Adicionar
                </button>
            </form>
        </div>

        <div class="flex flex-wrap -mx-4">
            <!-- Campo de filtrar tarefa -->
            <div class="w-full md:w-1/4 px-4 mb-6">
                <div class="bg-white shadow-md rounded-lg p-4">
                    <h5 class="text-lg font-bold mb-4">Filtrar Tarefas</h5>
                    <form>
                        <div class="mb-4">
                            <label for="status" class="block text-gray-700 font-medium mb-2">Status</label>
                            <select id="status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                                <option value="">Todos</option>
                                <option value="pendente">Pendente</option>
                                <option value="concluida">Concluída</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="prioridade" class="block text-gray-700 font-medium mb-2">Prioridade</label>
                            <select id="prioridade" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                                <option value="">Todas</option>
                                <option value="alta">Alta</option>
                                <option value="media">Média</option>
                                <option value="baixa">Baixa</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">Aplicar Filtros</button>
                    </form>
                </div>
            </div>

            <!-- Lista de tarefas -->
            <div class="w-full md:w-3/4 px-4">
                <div class="bg-white shadow-md rounded-lg p-4">
                    <h2 class="text-xl font-bold mb-4">Minhas Tarefas</h2>
                    <!-- Aqui você pode listar as tarefas -->
                    <div class="bg-gray-100 shadow-sm rounded-lg p-4 mb-4">
                        <h5 class="text-lg font-bold mb-2">Tarefa Exemplo</h5>
                        <p class="text-gray-600 mb-2">Descrição da tarefa...</p>
                        <span class="bg-blue-600 text-white text-sm px-2 py-1 rounded-lg">Pendente</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>