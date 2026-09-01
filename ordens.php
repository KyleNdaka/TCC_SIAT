<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

$mensagem = "";
$ordemEditando = null;

$filtroStatus = $_GET["status"] ?? "";
$filtroCliente = (int)($_GET["cliente"] ?? 0);

$clientes = $conexao->query("SELECT id, nome FROM clientes ORDER BY nome ASC");

$consultaOrdens = "
    SELECT os.id, os.status_os, os.valor, os.data_entrada, c.nome AS cliente, a.marca, a.modelo, a.defeito
    FROM ordens_servico os
    LEFT JOIN clientes c ON c.id = os.cliente_id
    LEFT JOIN aparelhos a ON a.id = os.aparelho_id
    WHERE 1 = 1";

if ($filtroCliente > 0) {
    $consultaOrdens .= " AND os.cliente_id = $filtroCliente";
}

if ($filtroStatus !== "") {
    $consultaOrdens .= " AND os.status_os = '" . $conexao->real_escape_string($filtroStatus) . "'";
}

$consultaOrdens .= " ORDER BY os.data_entrada DESC";
$listaOrdens = $conexao->query($consultaOrdens);

if (isset($_GET["delete"])) {
    $id = (int)($_GET["delete"] ?? 0);

    if ($id > 0) {
        $conexao->query("DELETE FROM historico_status WHERE ordem_id = $id");

        $ordem = $conexao->query("SELECT aparelho_id FROM ordens_servico WHERE id = $id LIMIT 1");
        if ($ordem && $ordem->num_rows > 0) {
            $dados = $ordem->fetch_assoc();
            if (!empty($dados["aparelho_id"])) {
                $conexao->query("DELETE FROM aparelhos WHERE id = " . (int)$dados["aparelho_id"]);
            }
        }

        $conexao->query("DELETE FROM ordens_servico WHERE id = $id");
        header("Location: ordens.php");
        exit;
    }
}

if (isset($_GET["id"])) {
    $id = (int)$_GET["id"];
    $resultado = $conexao->query("SELECT os.*, a.marca, a.modelo, a.imei, a.defeito FROM ordens_servico os LEFT JOIN aparelhos a ON a.id = os.aparelho_id WHERE os.id = $id LIMIT 1");
    if ($resultado && $resultado->num_rows > 0) {
        $ordemEditando = $resultado->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST["id"] ?? 0);
    $clienteId = (int)($_POST["cliente_id"] ?? 0);
    $marca = trim($_POST["marca"] ?? "");
    $modelo = trim($_POST["modelo"] ?? "");
    $imei = trim($_POST["imei"] ?? "");
    $defeito = trim($_POST["defeito"] ?? "");
    $statusOs = trim($_POST["status_os"] ?? "Recebido");
    $valor = trim($_POST["valor"] ?? "0");

    if ($clienteId <= 0) {
        $mensagem = "Selecione um cliente.";
    } else {
        if ($id > 0) {
            $ordemAtual = $conexao->query("SELECT aparelho_id FROM ordens_servico WHERE id = $id LIMIT 1");
            $dadosAtual = $ordemAtual->fetch_assoc();
            $aparelhoId = $dadosAtual["aparelho_id"] ?? null;

            if ($aparelhoId) {
                $stmtAparelho = $conexao->prepare("UPDATE aparelhos SET marca = ?, modelo = ?, imei = ?, defeito = ? WHERE id = ?");
                $stmtAparelho->bind_param("ssssi", $marca, $modelo, $imei, $defeito, $aparelhoId);
                $stmtAparelho->execute();
            }

            $stmt = $conexao->prepare("UPDATE ordens_servico SET cliente_id = ?, status_os = ?, valor = ? WHERE id = ?");
            $stmt->bind_param("issi", $clienteId, $statusOs, $valor, $id);
        } else {
            $stmtAparelho = $conexao->prepare("INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito) VALUES (?, ?, ?, ?, ?)");
            $stmtAparelho->bind_param("issss", $clienteId, $marca, $modelo, $imei, $defeito);
            $stmtAparelho->execute();
            $aparelhoId = $stmtAparelho->insert_id;

            $stmt = $conexao->prepare("INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisd", $clienteId, $aparelhoId, $statusOs, $valor);
        }

        if ($stmt->execute()) {
            $ordemId = $id > 0 ? $id : $conexao->insert_id;
            $histStmt = $conexao->prepare("INSERT INTO historico_status (ordem_id, status, descricao, data_hora) VALUES (?, ?, ?, NOW())");
            $descricao = "Status inicial registrado como {$statusOs}.";
            $histStmt->bind_param("iss", $ordemId, $statusOs, $descricao);
            $histStmt->execute();

            header("Location: ordens.php");
            exit;
        } else {
            $mensagem = "Erro ao salvar ordem de serviço: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordens de serviço</title>
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="painel.php">Painel</a>
                <a href="clientes.php">Clientes</a>
                <a href="logout.php">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <h1 class="page-title">Ordens de serviço</h1>
            <div class="inline-actions">
                <a class="link-button" href="painel.php">Voltar ao painel</a>
            </div>
        </div>

        <?php if ($mensagem !== "") : ?>
            <div class="alert error"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" action="ordens.php">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)($ordemEditando["id"] ?? 0)); ?>">

                <div>
                    <label>Cliente:</label>
                    <select name="cliente_id" required>
                        <option value="">Selecione</option>
                        <?php while ($cliente = $clientes->fetch_assoc()) : ?>
                            <option value="<?php echo (int)$cliente["id"]; ?>" <?php echo (($ordemEditando["cliente_id"] ?? 0) == (int)$cliente["id"]) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($cliente["nome"]); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label>Marca:</label>
                    <input type="text" name="marca" value="<?php echo htmlspecialchars($ordemEditando["marca"] ?? ""); ?>">
                </div>

                <div>
                    <label>Modelo:</label>
                    <input type="text" name="modelo" value="<?php echo htmlspecialchars($ordemEditando["modelo"] ?? ""); ?>">
                </div>

                <div>
                    <label>IMEI:</label>
                    <input type="text" name="imei" value="<?php echo htmlspecialchars($ordemEditando["imei"] ?? ""); ?>">
                </div>

                <div>
                    <label>Defeito:</label>
                    <textarea name="defeito"><?php echo htmlspecialchars($ordemEditando["defeito"] ?? ""); ?></textarea>
                </div>

                <div>
                    <label>Status:</label>
                    <select name="status_os">
                        <?php $statusAtual = $ordemEditando["status_os"] ?? "Recebido"; ?>
                        <?php foreach (["Recebido", "Em análise", "Aguardando peça", "Concluído"] as $status) : ?>
                            <option value="<?php echo $status; ?>" <?php echo $statusAtual === $status ? "selected" : ""; ?>>
                                <?php echo $status; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Valor:</label>
                    <input type="text" name="valor" value="<?php echo htmlspecialchars((string)($ordemEditando["valor"] ?? "0")); ?>">
                </div>

                <button type="submit"><?php echo $ordemEditando ? "Salvar alterações" : "Cadastrar ordem"; ?></button>
            </form>
        </div>

        <div class="card">
            <form method="get" action="ordens.php" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
                <div style="flex:1; min-width:220px;">
                    <label>Cliente:</label>
                    <select name="cliente">
                        <option value="">Todos</option>
                        <?php while ($cliente = $clientes->fetch_assoc()) : ?>
                            <option value="<?php echo (int)$cliente["id"]; ?>" <?php echo $filtroCliente === (int)$cliente["id"] ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($cliente["nome"]); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div style="flex:1; min-width:200px;">
                    <label>Status:</label>
                    <select name="status">
                        <option value="">Todos</option>
                        <?php foreach (["Recebido", "Em análise", "Aguardando peça", "Concluído"] as $status) : ?>
                            <option value="<?php echo $status; ?>" <?php echo $filtroStatus === $status ? "selected" : ""; ?>>
                                <?php echo $status; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn">Filtrar</button>
                <a class="btn btn-secondary" href="ordens.php">Limpar</a>
                <a class="btn" href="exportar_csv.php?cliente=<?php echo (int)$filtroCliente; ?>&status=<?php echo urlencode((string)$filtroStatus); ?>">Exportar CSV</a>
            </form>
        </div>

        <div class="card">
            <h3>Ordens cadastradas</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Status</th>
                        <th>Valor</th>
                        <th>Entrada</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($listaOrdens && $listaOrdens->num_rows > 0) : ?>
                        <?php while ($ordem = $listaOrdens->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo (int)$ordem["id"]; ?></td>
                                <td><?php echo htmlspecialchars($ordem["cliente"] ?? "-"); ?></td>
                                <td><?php echo htmlspecialchars($ordem["marca"] ?? "-"); ?></td>
                                <td><?php echo htmlspecialchars($ordem["modelo"] ?? "-"); ?></td>
                                <td><span class="status-badge <?php echo strtolower(str_replace(' ', '-', $ordem["status_os"] ?? "recebido")); ?>"><?php echo htmlspecialchars($ordem["status_os"] ?? "-"); ?></span></td>
                                <td>R$ <?php echo htmlspecialchars((string)($ordem["valor"] ?? "0.00")); ?></td>
                                <td><?php echo htmlspecialchars($ordem["data_entrada"] ?? "-"); ?></td>
                                <td class="inline-actions">
                                    <a class="btn" href="ordem_detalhes.php?id=<?php echo (int)$ordem["id"]; ?>">Detalhes</a>
                                    <a class="btn btn-secondary" href="ordens.php?id=<?php echo (int)$ordem["id"]; ?>">Editar</a>
                                    <a class="btn btn-danger" href="ordens.php?delete=<?php echo (int)$ordem["id"]; ?>" onclick="return confirm('Deseja excluir esta ordem de serviço?');">Excluir</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8">Nenhuma ordem cadastrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
