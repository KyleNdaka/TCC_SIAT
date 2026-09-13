<?php
session_start();

// Validação de Sessão (Segurança)
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

$mensagem = "";
$tipoMensagem = "";
$clienteEditando = null;
$filtro = trim($_GET["q"] ?? "");

// -----------------------------------------------------------------------------
// 1. EXCLUIR (DELETE) - Utilizando Prepared Statement e Cascade do Banco
// -----------------------------------------------------------------------------
if (isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];

    if ($id > 0) {
        $stmtDelete = $conexao->prepare("DELETE FROM clientes WHERE id_Cliente = ?");
        $stmtDelete->bind_param("i", $id);

        if ($stmtDelete->execute()) {
            header("Location: clientes.php?msg=deleted");
            exit;
        } else {
            $mensagem = "Erro ao excluir cliente: " . $conexao->error;
            $tipoMensagem = "error";
        }
    }
}

// -----------------------------------------------------------------------------
// 2. BUSCAR REGISTRO PARA EDIÇÃO (READ - Seleção)
// -----------------------------------------------------------------------------
if (isset($_GET["id"])) {
    $id = (int)$_GET["id"];
    $stmtSelectId = $conexao->prepare("SELECT * FROM clientes WHERE id_Cliente = ? LIMIT 1");
    $stmtSelectId->bind_param("i", $id);
    $stmtSelectId->execute();
    $resultadoEdit = $stmtSelectId->get_result();

    if ($resultadoEdit && $resultadoEdit->num_rows > 0) {
        $clienteEditando = $resultadoEdit->fetch_assoc();
    }
}

// -----------------------------------------------------------------------------
// 3. CRIAR (CREATE) E ATUALIZAR (UPDATE)
// -----------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST["id_Cliente"] ?? 0);
    $nome = trim($_POST["nome"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($nome === "") {
        $mensagem = "O nome do cliente é obrigatório.";
        $tipoMensagem = "error";
    } else {
        if ($id > 0) {
            // OPERAÇÃO: UPDATE (Atualizar)
            $stmt = $conexao->prepare("UPDATE clientes SET nome = ?, telefone = ?, email = ? WHERE id_Cliente = ?");
            $stmt->bind_param("sssi", $nome, $telefone, $email, $id);
            $acaoMsg = "updated";
        } else {
            // OPERAÇÃO: CREATE (Cadastrar)
            $stmt = $conexao->prepare("INSERT INTO clientes (nome, telefone, email) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nome, $telefone, $email);
            $acaoMsg = "created";
        }

        if ($stmt->execute()) {
            header("Location: clientes.php?msg=" . $acaoMsg);
            exit;
        } else {
            $mensagem = "Erro ao salvar cliente: " . $stmt->error;
            $tipoMensagem = "error";
        }
    }
}

// Trata mensagens de sucesso via GET
if (isset($_GET["msg"])) {
    if ($_GET["msg"] === "created") {
        $mensagem = "Cliente cadastrado com sucesso!";
        $tipoMensagem = "success";
    } elseif ($_GET["msg"] === "updated") {
        $mensagem = "Dados do cliente atualizados com sucesso!";
        $tipoMensagem = "success";
    } elseif ($_GET["msg"] === "deleted") {
        $mensagem = "Cliente e registros vinculados foram removidos com sucesso!";
        $tipoMensagem = "success";
    }
}

// -----------------------------------------------------------------------------
// 4. CONSULTAR / LISTAR / FILTRAR (READ - Listagem)
// -----------------------------------------------------------------------------
if ($filtro !== "") {
    $filtroParam = "%" . $filtro . "%";
    $stmtClientes = $conexao->prepare("SELECT * FROM clientes WHERE nome LIKE ? OR telefone LIKE ? OR email LIKE ? ORDER BY nome ASC");
    $stmtClientes->bind_param("sss", $filtroParam, $filtroParam, $filtroParam);
    $stmtClientes->execute();
    $resultadoClientes = $stmtClientes->get_result();
} else {
    $resultadoClientes = $conexao->query("SELECT * FROM clientes ORDER BY nome ASC");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Clientes - CRUD</title>
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="painel.php">Painel</a>
                <a href="ordens.php">Ordens</a>
                <a href="logout.php">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <!-- Cabeçalho da Página -->
        <div class="card">
            <h1 class="page-title">Gerenciamento de Clientes</h1>
            <div class="inline-actions">
                <a class="link-button" href="painel.php">Voltar ao painel</a>
            </div>
        </div>

        <!-- Alerta de Feedback (Sucesso ou Erro) -->
        <?php if ($mensagem !== "") : ?>
            <div class="alert <?php echo $tipoMensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <!-- Formulário de Busca (READ - Filtro) -->
        <div class="card">
            <form method="get" action="clientes.php" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                <div style="flex:1; min-width:220px;">
                    <label for="q">Buscar cliente:</label>
                    <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($filtro); ?>" placeholder="Buscar por Nome, Telefone ou E-mail...">
                </div>
                <button type="submit" class="btn">Buscar</button>
                <a class="btn btn-secondary" href="clientes.php">Limpar Filtro</a>
            </form>
        </div>

        <!-- Formulário de Cadastro / Edição (CREATE / UPDATE) -->
        <div class="card">
            <h2><?php echo $clienteEditando ? "Editar Cliente #" . (int)$clienteEditando["id_Cliente"] : "Novo Cliente"; ?></h2>
            
            <form method="post" action="clientes.php">
                <input type="hidden" name="id_Cliente" value="<?php echo htmlspecialchars((string)($clienteEditando["id_Cliente"] ?? 0)); ?>">

                <div>
                    <label for="nome">Nome Completo *:</label>
                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($clienteEditando["nome"] ?? ""); ?>" required>
                </div>

                <div>
                    <label for="telefone">Telefone / WhatsApp:</label>
                    <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($clienteEditando["telefone"] ?? ""); ?>">
                </div>

                <div>
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($clienteEditando["email"] ?? ""); ?>">
                </div>

                <div style="margin-top: 10px;">
                    <button type="submit" class="btn">
                        <?php echo $clienteEditando ? "Salvar Alterações" : "Cadastrar Cliente"; ?>
                    </button>
                    <?php if ($clienteEditando) : ?>
                        <a class="btn btn-secondary" href="clientes.php">Cancelar Edição</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabela de Listagem de Clientes (READ & Ações UPDATE/DELETE) -->
        <div class="card">
            <h3>Clientes Cadastrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>E-mail</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultadoClientes && $resultadoClientes->num_rows > 0) : ?>
                        <?php while ($cliente = $resultadoClientes->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo (int)$cliente["id_Cliente"]; ?></td>
                                <td><?php echo htmlspecialchars($cliente["nome"] ?? ""); ?></td>
                                <td><?php echo htmlspecialchars($cliente["telefone"] ?? "-"); ?></td>
                                <td><?php echo htmlspecialchars($cliente["email"] ?? "-"); ?></td>
                                <td class="inline-actions">
                                    <a class="btn btn-secondary" href="clientes.php?id=<?php echo (int)$cliente["id_Cliente"]; ?>">Editar</a>
                                    <a class="btn btn-danger" href="clientes.php?delete=<?php echo (int)$cliente["id_Cliente"]; ?>" onclick="return confirm('Tem certeza que deseja excluir este cliente? Todas as ordens e aparelhos vinculados a ele também serão excluídos.');">Excluir</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">Nenhum cliente encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
