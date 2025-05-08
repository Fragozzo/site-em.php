<?php
// Inclua o arquivo head.php, que já inicia a sessão
include 'partials/head.php';

// Verifica timeout de inatividade (30 minutos)
$timeout_duration = 30 * 60;
if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

$_SESSION['ultimo_acesso'] = time();

if (!isset($_SESSION['ultima_regeneracao']) || (time() - $_SESSION['ultima_regeneracao']) > 1800) {
    session_regenerate_id(true);
    $_SESSION['ultima_regeneracao'] = time();
}

if (!isset($_SESSION['usuario_id'])) {
    if (isset($_COOKIE['remember_me_id'], $_COOKIE['remember_me_token'])) {
        $user_id = $_COOKIE['remember_me_id'];
        $token = $_COOKIE['remember_me_token'];
        $usuarios = carregarUsuarios();

        $autenticado = false;
        foreach ($usuarios as $usuario) {
            if (
                isset($usuario['id'], $usuario['remember_token']) &&
                $usuario['id'] == $user_id &&
                $usuario['remember_token'] === $token
            ) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['ultimo_acesso'] = time();

                session_regenerate_id(true);
                $_SESSION['ultima_regeneracao'] = time();

                $autenticado = true;
                break;
            }
        }

        if (!$autenticado) {
            setcookie('remember_me_id', '', time() - 3600, '/', '', false, true);
            setcookie('remember_me_token', '', time() - 3600, '/', '', false, true);
            header('Location: login.php');
            exit;
        }
    } else {
        header('Location: login.php');
        exit;
    }
}

// Definir o arquivo de tarefas
define('TAREFAS_FILE', 'tarefas.json');
define('COMENTARIOS_FILE', 'comentarios.json');
define('HISTORICO_FILE', 'historico.json');

// Funções para gerenciar tarefas
function carregarTarefas()
{
    if (file_exists(TAREFAS_FILE)) {
        $tarefasJson = file_get_contents(TAREFAS_FILE);
        $tarefas = json_decode($tarefasJson, true);
        return is_array($tarefas) ? $tarefas : [];
    }
    return [];
}

function salvarTarefas($tarefas)
{
    file_put_contents(TAREFAS_FILE, json_encode($tarefas, JSON_PRETTY_PRINT));
}

// Funções para gerenciar comentários
function carregarComentarios()
{
    if (file_exists(COMENTARIOS_FILE)) {
        $comentariosJson = file_get_contents(COMENTARIOS_FILE);
        $comentarios = json_decode($comentariosJson, true);
        return is_array($comentarios) ? $comentarios : [];
    }
    return [];
}

function salvarComentarios($comentarios)
{
    file_put_contents(COMENTARIOS_FILE, json_encode($comentarios, JSON_PRETTY_PRINT));
}

// Funções para gerenciar histórico
function carregarHistorico()
{
    if (file_exists(HISTORICO_FILE)) {
        $historicoJson = file_get_contents(HISTORICO_FILE);
        $historico = json_decode($historicoJson, true);
        return is_array($historico) ? $historico : [];
    }
    return [];
}

function salvarHistorico($historico)
{
    file_put_contents(HISTORICO_FILE, json_encode($historico, JSON_PRETTY_PRINT));
}

// Função para registrar uma alteração no histórico
function registrarAlteracao($tarefa_id, $tipo_alteracao, $valor_antigo, $valor_novo, $usuario_id, $usuario_nome)
{
    $historico = carregarHistorico();

    $alteracao = [
        'id' => uniqid(),
        'tarefa_id' => $tarefa_id,
        'tipo' => $tipo_alteracao,
        'valor_antigo' => $valor_antigo,
        'valor_novo' => $valor_novo,
        'usuario_id' => $usuario_id,
        'usuario_nome' => $usuario_nome,
        'data' => date('Y-m-d H:i:s')
    ];

    $historico[] = $alteracao;
    salvarHistorico($historico);

    return $alteracao;
}

// Carregar usuários do sistema
function carregarUsuarios()
{
    $usuariosJson = file_get_contents('usuarios.json');
    return json_decode($usuariosJson, true) ?: [];
}

$usuarios = carregarUsuarios();
$usuario_valido = false;

foreach ($usuarios as $usuario) {
    if ($usuario['id'] == $_SESSION['usuario_id']) {
        $usuario_valido = true;
        $_SESSION['usuario_nome'] = $usuario['nome'];
        break;
    }
}

if (!$usuario_valido) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Carregar todas as tarefas do arquivo
$todas_tarefas = carregarTarefas();

// Atualizar tarefas antigas para usar o novo formato de status
foreach ($todas_tarefas as $key => $tarefa) {
    if (!isset($tarefa['status'])) {
        // Converter do formato antigo para o novo
        $todas_tarefas[$key]['status'] = $tarefa['concluida'] ? 'concluida' : 'pendente';
    }
}
salvarTarefas($todas_tarefas); // Salvar as atualizações

// Processa o formulário de adição de tarefa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_tarefa'])) {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $prioridade = $_POST['prioridade'];
    $data_limite = !empty($_POST['data_limite']) ? $_POST['data_limite'] : null;
    $responsavel_id = isset($_POST['responsavel']) ? $_POST['responsavel'] : $_SESSION['usuario_id'];

    // Encontrar nome do responsável
    $responsavel_nome = '';
    foreach ($usuarios as $usuario) {
        if ($usuario['id'] == $responsavel_id) {
            $responsavel_nome = $usuario['nome'];
            break;
        }
    }

    if (!empty($titulo)) {
        // Cria nova tarefa com ID único
        $nova_tarefa = [
            'id' => uniqid(),
            'titulo' => $titulo,
            'descricao' => $descricao,
            'prioridade' => $prioridade,
            'data_criacao' => date('Y-m-d H:i:s'),
            'data_limite' => $data_limite,
            'responsavel_id' => $responsavel_id,
            'responsavel_nome' => $responsavel_nome,
            'criador_id' => $_SESSION['usuario_id'],
            'criador_nome' => $_SESSION['usuario_nome'],
            'status' => 'pendente',
            'concluida' => false // Mantém para compatibilidade
        ];

        // Adiciona a tarefa ao array
        $todas_tarefas[] = $nova_tarefa;

        // Salva no arquivo JSON
        salvarTarefas($todas_tarefas);

        // Mensagem de sucesso
        $mensagem_sucesso = "Tarefa adicionada com sucesso!";
    } else {
        $mensagem_erro = "Por favor, informe um título para a tarefa.";
    }
}

// Processar edição de tarefa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_tarefa'])) {
    $tarefa_id = $_POST['tarefa_id'];
    $novo_titulo = trim($_POST['titulo']);
    $nova_descricao = trim($_POST['descricao']);
    $nova_prioridade = $_POST['prioridade'];
    $nova_data_limite = !empty($_POST['data_limite']) ? $_POST['data_limite'] : null;
    $novo_responsavel_id = $_POST['responsavel'];

    // Encontrar nome do responsável
    $responsavel_nome = '';
    foreach ($usuarios as $usuario) {
        if ($usuario['id'] == $novo_responsavel_id) {
            $responsavel_nome = $usuario['nome'];
            break;
        }
    }

    $tarefa_alterada = false;

    foreach ($todas_tarefas as $key => $tarefa) {
        if ($tarefa['id'] === $tarefa_id) {
            // Verificar permissões (apenas criador ou responsável pode editar)
            if ($tarefa['criador_id'] == $_SESSION['usuario_id'] || $tarefa['responsavel_id'] == $_SESSION['usuario_id']) {

                // Registrar alterações no histórico
                if ($tarefa['titulo'] !== $novo_titulo) {
                    registrarAlteracao(
                        $tarefa_id,
                        'titulo',
                        $tarefa['titulo'],
                        $novo_titulo,
                        $_SESSION['usuario_id'],
                        $_SESSION['usuario_nome']
                    );
                }

                if ($tarefa['descricao'] !== $nova_descricao) {
                    registrarAlteracao(
                        $tarefa_id,
                        'descricao',
                        $tarefa['descricao'],
                        $nova_descricao,
                        $_SESSION['usuario_id'],
                        $_SESSION['usuario_nome']
                    );
                }

                if ($tarefa['prioridade'] !== $nova_prioridade) {
                    registrarAlteracao(
                        $tarefa_id,
                        'prioridade',
                        $tarefa['prioridade'],
                        $nova_prioridade,
                        $_SESSION['usuario_id'],
                        $_SESSION['usuario_nome']
                    );
                }

                if ($tarefa['data_limite'] !== $nova_data_limite) {
                    registrarAlteracao(
                        $tarefa_id,
                        'data_limite',
                        $tarefa['data_limite'],
                        $nova_data_limite,
                        $_SESSION['usuario_id'],
                        $_SESSION['usuario_nome']
                    );
                }

                if ($tarefa['responsavel_id'] !== $novo_responsavel_id) {
                    registrarAlteracao(
                        $tarefa_id,
                        'responsavel',
                        $tarefa['responsavel_nome'],
                        $responsavel_nome,
                        $_SESSION['usuario_id'],
                        $_SESSION['usuario_nome']
                    );
                }

                // Atualizar os valores da tarefa
                $todas_tarefas[$key]['titulo'] = $novo_titulo;
                $todas_tarefas[$key]['descricao'] = $nova_descricao;
                $todas_tarefas[$key]['prioridade'] = $nova_prioridade;
                $todas_tarefas[$key]['data_limite'] = $nova_data_limite;
                $todas_tarefas[$key]['responsavel_id'] = $novo_responsavel_id;
                $todas_tarefas[$key]['responsavel_nome'] = $responsavel_nome;

                $tarefa_alterada = true;
            } else {
                $mensagem_erro = "Você não tem permissão para editar esta tarefa.";
            }
            break;
        }
    }

    if ($tarefa_alterada) {
        // Salvar as alterações no arquivo JSON
        salvarTarefas($todas_tarefas);
        $mensagem_sucesso = "Tarefa atualizada com sucesso!";

        // Redirecionar para evitar reenvio do formulário
        header('Location: tarefas.php');
        exit;
    }
}

// Função para atualizar status da tarefa
if (isset($_POST['atualizar_status']) && !empty($_POST['tarefa_id']) && !empty($_POST['novo_status'])) {
    $tarefa_id = $_POST['tarefa_id'];
    $novo_status = $_POST['novo_status'];
    $tarefa_alterada = false;

    foreach ($todas_tarefas as $key => $tarefa) {
        if ($tarefa['id'] === $tarefa_id) {
            $status_antigo = $tarefa['status'];
            $todas_tarefas[$key]['status'] = $novo_status;

            // Atualiza o campo concluida para compatibilidade
            $todas_tarefas[$key]['concluida'] = ($novo_status === 'concluida');

            // Registrar data e usuário que alterou o status
            if ($novo_status === 'concluida') {
                $todas_tarefas[$key]['data_conclusao'] = date('Y-m-d H:i:s');
                $todas_tarefas[$key]['concluido_por'] = $_SESSION['usuario_id'];
            }

            // Registrar alteração no histórico
            registrarAlteracao(
                $tarefa_id,
                'status',
                $status_antigo,
                $novo_status,
                $_SESSION['usuario_id'],
                $_SESSION['usuario_nome']
            );

            $tarefa_alterada = true;
            break;
        }
    }

    if ($tarefa_alterada) {
        // Salva as alterações no arquivo JSON
        salvarTarefas($todas_tarefas);
    }

    // Redireciona para evitar reenvio do formulário
    header('Location: tarefas.php');
    exit;
}

// Função para marcar tarefa como concluída (compatibilidade)
if (isset($_GET['concluir']) && !empty($todas_tarefas)) {
    $tarefa_id = $_GET['concluir'];
    $tarefa_alterada = false;

    foreach ($todas_tarefas as $key => $tarefa) {
        if ($tarefa['id'] === $tarefa_id) {
            $status_antigo = $tarefa['status'];
            $novo_status = $status_antigo === 'concluida' ? 'pendente' : 'concluida';
            $todas_tarefas[$key]['status'] = $novo_status;
            $todas_tarefas[$key]['concluida'] = ($novo_status === 'concluida');

            if ($novo_status === 'concluida') {
                $todas_tarefas[$key]['data_conclusao'] = date('Y-m-d H:i:s');
                $todas_tarefas[$key]['concluido_por'] = $_SESSION['usuario_id'];
            }

            // Registrar alteração no histórico
            registrarAlteracao(
                $tarefa_id,
                'status',
                $status_antigo,
                $novo_status,
                $_SESSION['usuario_id'],
                $_SESSION['usuario_nome']
            );

            $tarefa_alterada = true;
            break;
        }
    }

    if ($tarefa_alterada) {
        // Salva as alterações no arquivo JSON
        salvarTarefas($todas_tarefas);
    }

    // Redireciona para evitar reenvio do formulário
    header('Location: tarefas.php');
    exit;
}

// Processar adição de comentários
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_comentario'])) {
    $tarefa_id = $_POST['tarefa_id'];
    $comentario_texto = trim($_POST['comentario_texto']);

    if (!empty($comentario_texto)) {
        // Carregar comentários existentes
        $comentarios = carregarComentarios();

        // Criar novo comentário
        $novo_comentario = [
            'id' => uniqid(),
            'tarefa_id' => $tarefa_id,
            'usuario_id' => $_SESSION['usuario_id'],
            'usuario_nome' => $_SESSION['usuario_nome'],
            'texto' => $comentario_texto,
            'data_criacao' => date('Y-m-d H:i:s')
        ];

        // Adicionar ao array de comentários
        $comentarios[] = $novo_comentario;

        // Salvar no arquivo JSON
        salvarComentarios($comentarios);

        // Redirecionar para evitar reenvio do formulário
        header('Location: tarefas.php');
        exit;
    }
}

// Excluir comentário
if (isset($_GET['excluir_comentario'])) {
    $comentario_id = $_GET['excluir_comentario'];
    $comentario_excluido = false;

    // Carregar comentários
    $comentarios = carregarComentarios();

    foreach ($comentarios as $key => $comentario) {
        if ($comentario['id'] === $comentario_id) {
            // Verificar permissão (apenas o autor do comentário pode excluí-lo)
            if ($comentario['usuario_id'] == $_SESSION['usuario_id']) {
                unset($comentarios[$key]);
                $comentario_excluido = true;
            } else {
                $mensagem_erro = "Você não tem permissão para excluir este comentário.";
            }
            break;
        }
    }

    if ($comentario_excluido) {
        // Reindexar array e salvar
        $comentarios = array_values($comentarios);
        salvarComentarios($comentarios);
        $mensagem_sucesso = "Comentário excluído com sucesso!";
    }

    // Redirecionar para evitar problemas
    if (!isset($mensagem_erro)) {
        header('Location: tarefas.php');
        exit;
    }
}

// Carregar comentários para exibição
$comentarios = carregarComentarios();

// Carregar histórico para exibição
$historico = carregarHistorico();

// Filtrar tarefas
$tarefas_filtradas = $todas_tarefas;

// Aplicar filtros se existirem
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['filtrar'])) {
    $filtro_responsavel = isset($_GET['filtro_responsavel']) ? $_GET['filtro_responsavel'] : '';
    $filtro_status = isset($_GET['filtro_status']) ? $_GET['filtro_status'] : '';
    $filtro_data_limite = isset($_GET['filtro_data_limite']) ? $_GET['filtro_data_limite'] : '';

    if (!empty($filtro_responsavel) || !empty($filtro_status) || !empty($filtro_data_limite)) {
        $tarefas_filtradas = array_filter($todas_tarefas, function ($tarefa) use ($filtro_responsavel, $filtro_status, $filtro_data_limite) {
            $match = true;

            // Filtro por responsável
            if (!empty($filtro_responsavel) && $tarefa['responsavel_id'] != $filtro_responsavel) {
                $match = false;
            }

            // Filtro por status
            if (!empty($filtro_status)) {
                $status_tarefa = isset($tarefa['status']) ? $tarefa['status'] : ($tarefa['concluida'] ? 'concluida' : 'pendente');
                if ($status_tarefa !== $filtro_status) {
                    $match = false;
                }
            }

            // Filtro por data limite
            if (!empty($filtro_data_limite) && !empty($tarefa['data_limite'])) {
                if (strtotime($tarefa['data_limite']) > strtotime($filtro_data_limite)) {
                    $match = false;
                }
            }

            return $match;
        });
    }
}

// Excluir tarefa
if (isset($_GET['excluir'])) {
    $tarefa_id = $_GET['excluir'];
    $tarefa_excluida = false;

    foreach ($todas_tarefas as $key => $tarefa) {
        if ($tarefa['id'] === $tarefa_id) {
            // Verificar permissão (apenas criador ou responsável pode excluir)
            if ($tarefa['criador_id'] == $_SESSION['usuario_id'] || $tarefa['responsavel_id'] == $_SESSION['usuario_id']) {
                unset($todas_tarefas[$key]);
                $tarefa_excluida = true;
            } else {
                $mensagem_erro = "Você não tem permissão para excluir esta tarefa.";
            }
            break;
        }
    }

    if ($tarefa_excluida) {
        // Reindexar array e salvar
        $todas_tarefas = array_values($todas_tarefas);
        salvarTarefas($todas_tarefas);
        $mensagem_sucesso = "Tarefa excluída com sucesso!";
    }

    // Redireciona para evitar problemas
    if (!isset($mensagem_erro)) {
        header('Location: tarefas.php');
        exit;
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
                <a href="index.php" class="text-xl font-bold text-blue-600 flex items-center">
                    <i class="fas fa-tasks mr-2"></i>Sistema de Tarefas
                </a>
                <ul class="flex space-x-4">
                    <li class="text-gray-700">Olá, <?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário'); ?>!</li>
                    <li>
                        <a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-home mr-1"></i>Início
                        </a>
                    </li>
                    <li>
                        <a href="login.php?logout=1" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">
                            <i class="fas fa-sign-out-alt mr-1"></i>Sair
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Mensagens de sucesso e erro -->
        <?php if (isset($mensagem_sucesso)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <span class="block sm:inline"><?php echo $mensagem_sucesso; ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($mensagem_erro)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <span class="block sm:inline"><?php echo $mensagem_erro; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Adicionar Tarefa -->
            <div class="bg-white shadow-md rounded-lg p-4">
                <h2 class="text-lg font-bold mb-4">Adicionar Nova Tarefa</h2>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="titulo" class="block text-gray-700 font-medium mb-2">Título</label>
                        <input type="text" id="titulo" name="titulo" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" required>
                    </div>
                    <div class="mb-4">
                        <label for="descricao" class="block text-gray-700 font-medium mb-2">Descrição</label>
                        <textarea id="descricao" name="descricao" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600"></textarea>
                    </div>
                    <div class="mb-4">
                        <label for="prioridade" class="block text-gray-700 font-medium mb-2">Prioridade</label>
                        <select id="prioridade" name="prioridade" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                            <option value="baixa">Baixa</option>
                            <option value="media" selected>Média</option>
                            <option value="alta">Alta</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="data_limite" class="block text-gray-700 font-medium mb-2">Data Limite</label>
                        <input type="date" id="data_limite" name="data_limite" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                    </div>
                    <div class="mb-4">
                        <label for="responsavel" class="block text-gray-700 font-medium mb-2">Responsável</label>
                        <select id="responsavel" name="responsavel" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo $usuario['id']; ?>" <?php echo ($usuario['id'] == $_SESSION['usuario_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="adicionar_tarefa" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus-circle mr-2"></i>Adicionar Tarefa
                    </button>
                </form>
            </div>

            <!-- Filtrar Tarefas -->
            <div class="bg-white shadow-md rounded-lg p-4 lg:col-span-2">
                <h2 class="text-lg font-bold mb-4">Filtrar Tarefas</h2>
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="filtro_responsavel" class="block text-gray-700 font-medium mb-2">Responsável</label>
                        <select id="filtro_responsavel" name="filtro_responsavel" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                            <option value="">Todos</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo $usuario['id']; ?>" <?php echo isset($_GET['filtro_responsavel']) && $_GET['filtro_responsavel'] == $usuario['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="filtro_status" class="block text-gray-700 font-medium mb-2">Status</label>
                        <select id="filtro_status" name="filtro_status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                            <option value="">Todos</option>
                            <option value="pendente" <?php echo isset($_GET['filtro_status']) && $_GET['filtro_status'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="andamento" <?php echo isset($_GET['filtro_status']) && $_GET['filtro_status'] == 'andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                            <option value="concluida" <?php echo isset($_GET['filtro_status']) && $_GET['filtro_status'] == 'concluida' ? 'selected' : ''; ?>>Concluída</option>
                        </select>
                    </div>
                    <div>
                        <label for="filtro_data_limite" class="block text-gray-700 font-medium mb-2">Data Limite até</label>
                        <input type="date" id="filtro_data_limite" name="filtro_data_limite" value="<?php echo isset($_GET['filtro_data_limite']) ? $_GET['filtro_data_limite'] : ''; ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                    </div>
                    <div class="col-span-1 md:col-span-3 flex justify-end space-x-4">
                        <button type="submit" name="filtrar" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-search mr-2"></i>Filtrar
                        </button>
                        <a href="tarefas.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-broom mr-2"></i>Limpar Filtros
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Tarefas -->
        <div class="bg-white shadow-md rounded-lg p-4 mt-6">
            <h2 class="text-lg font-bold mb-4">Minhas Tarefas</h2>
            <?php if (empty($tarefas_filtradas)): ?>
                <p class="text-center text-gray-500">Nenhuma tarefa encontrada com os filtros aplicados.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table-auto w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-200">
                                <th class="px-4 py-2">Título</th>
                                <th class="px-4 py-2">Descrição</th>
                                <th class="px-4 py-2">Prioridade</th>
                                <th class="px-4 py-2">Responsável</th>
                                <th class="px-4 py-2">Data Limite</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tarefas_filtradas as $tarefa): ?>
                                <tr class="border-t">
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($tarefa['titulo']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($tarefa['descricao']); ?></td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 rounded-lg text-white <?php echo $tarefa['prioridade'] === 'alta' ? 'bg-red-500' : ($tarefa['prioridade'] === 'media' ? 'bg-yellow-500' : 'bg-green-500'); ?>">
                                            <?php echo ucfirst($tarefa['prioridade']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($tarefa['responsavel_nome']); ?></td>
                                    <td class="px-4 py-2"><?php echo !empty($tarefa['data_limite']) ? date('d/m/Y', strtotime($tarefa['data_limite'])) : 'Sem data'; ?></td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 rounded-lg text-white <?php echo $tarefa['status'] === 'concluida' ? 'bg-green-500' : ($tarefa['status'] === 'andamento' ? 'bg-blue-500' : 'bg-yellow-500'); ?>">
                                            <?php echo ucfirst($tarefa['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex space-x-2">
                                            <form method="POST" action="">
                                                <input type="hidden" name="tarefa_id" value="<?php echo $tarefa['id']; ?>">
                                                <select name="novo_status" class="border border-gray-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-600" onchange="this.form.submit()">
                                                    <option value="pendente" <?php echo $tarefa['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                                    <option value="andamento" <?php echo $tarefa['status'] === 'andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                                                    <option value="concluida" <?php echo $tarefa['status'] === 'concluida' ? 'selected' : ''; ?>>Concluída</option>
                                                </select>
                                                <input type="hidden" name="atualizar_status" value="1">
                                            </form>
                                            <a href="tarefas.php?excluir=<?php echo $tarefa['id']; ?>" class="text-red-500 hover:text-red-700" onclick="return confirm('Tem certeza que deseja excluir esta tarefa?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>