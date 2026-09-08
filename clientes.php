<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

$mensagem = "";
$clienteEditando = null;
$filtro = trim($_GET["q"] ?? "");

if (isset($_GET["delete"])) {
    $id = (int)($_GET["delete"] ?? 0);

    if ($id > 0) {
        $ordensRelacionadas = $conexao->query("SELECT id_Servico FROM ordens_servico WHERE cliente_id = $id");
        $idsOrdens = [];
        while ($linha = $ordensRelacionadas->fetch_assoc()) {
            $idsOrdens[] = (int) $linha["id_Servico"];
        }

        if (!empty($idsOrdens)) {
            $idsOrdensEmSql = implode(",", $idsOrdens);
            $conexao->query("DELETE FROM historico_status WHERE ordem_id IN ($idsOrdensEmSql)");
            $conexao->query("DELETE FROM ordens_servico WHERE cliente_id = $id");
        }

        $conexao->query("DELETE FROM aparelhos WHERE cliente_id = $id");
        $conexao->query("DELETE FROM clientes WHERE id_Cliente = $id");
        header("Location: clientes.php");
        exit;
    }
}

if (isset($_GET["id"])) {
    $id = (int)$_GET["id"];
    $resultado = $conexao->query("SELECT * FROM clientes WHERE id_Cliente = $id LIMIT 1");
    if ($resultado && $resultado->num_rows > 0) {
        $clienteEditando = $resultado->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST["id_Cliente"] ?? 0);
    $nome = trim($_POST["nome"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($nome === "") {
        $mensagem = "O nome do cliente é obrigatório.";
    } else {
        if ($id > 0) {
            $stmt = $conexao->prepare("UPDATE clientes SET nome = ?, telefone = ?, email = ? WHERE id_Cliente = ?");
            $stmt->bind_param("sssi", $nome, $telefone, $email, $id);
        } else {
            $stmt = $conexao->prepare("INSERT INTO clientes (nome, telefone, email) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nome, $telefone, $email);
        }

        if ($stmt->execute()) {
            header("Location: clientes.php");
            exit;
        } else {
            $mensagem = "Erro ao salvar cliente: " . $stmt->error;
        }
    }
}

$baseClientes = "SELECT * FROM clientes";
if ($filtro !== "") {
    $filtroEscapado = $conexao->real_escape_string($filtro);
    $baseClientes .= " WHERE nome LIKE '%$filtroEscapado%' OR telefone LIKE '%$filtroEscapado%' OR email LIKE '%$filtroEscapado%'";
}
$baseClientes .= " ORDER BY nome ASC";
$resultadoClientes = $conexao->query($baseClientes);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes</title>
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
        <div class="card">
            <h1 class="page-title">Cadastro de clientes</h1>
            <div class="inline-actions">
                <a class="link-button" href="painel.php">Voltar ao painel</a>
            </div>
        </div>

        <div class="card">
            <form method="get" action="clientes.php" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
                <div style="flex:1; min-width:220px;">
                    <label>Buscar cliente:</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($filtro); ?>" placeholder="Nome, telefone ou e-mail">
                </div>
                <button type="submit" class="btn">Buscar</button>
                <a class="btn btn-secondary" href="clientes.php">Limpar</a>
            </form>
        </div>

        <div class="card">
            <?php if ($mensagem !== "") : ?>
                <div class="alert error"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php endif; ?>

            <form method="post" action="clientes.php">
                <input type="hidden" name="id_Cliente" value="<?php echo htmlspecialchars((string)($clienteEditando["id_Cliente"] ?? 0)); ?>">

                <div>
                    <label>Nome:</label>
                    <input type="text" name="nome" value="<?php echo htmlspecialchars($clienteEditando["nome"] ?? ""); ?>" required>
                </div>

                <div>
                    <label>Telefone:</label>
                    <input type="text" name="telefone" value="<?php echo htmlspecialchars($clienteEditando["telefone"] ?? ""); ?>">
                </div>

                <div>
                    <label>E-mail:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($clienteEditando["email"] ?? ""); ?>">
                </div>

                <button type="submit"><?php echo $clienteEditando ? "Salvar alterações" : "Cadastrar cliente"; ?></button>
            </form>
        </div>

        <div class="card">
            <h3>Clientes cadastrados</h3>
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
                                    <a class="btn btn-danger" href="clientes.php?delete=<?php echo (int)$cliente["id_Cliente"]; ?>" onclick="return confirm('Deseja excluir este cliente?');">Excluir</a>
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
