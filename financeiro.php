<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

$conexao->query("CREATE TABLE IF NOT EXISTS pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordem_id INT NOT NULL,
    cliente_id INT NOT NULL,
    valor_pago DECIMAL(10,2) NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    data_pagamento DATETIME DEFAULT CURRENT_TIMESTAMP,
    status_pagamento VARCHAR(30) DEFAULT 'Pago',
    observacao TEXT,
    CONSTRAINT fk_pagamentos_ordem FOREIGN KEY (ordem_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagamentos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
)");

$conexao->query("CREATE TABLE IF NOT EXISTS despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_despesa DATE NOT NULL,
    observacao TEXT
)");

$mensagem = "";
$pagamentoEditando = null;
$despesaEditando = null;

if (isset($_GET["delete_pagamento"])) {
    $id = (int)($_GET["delete_pagamento"] ?? 0);
    if ($id > 0) {
        $conexao->query("DELETE FROM pagamentos WHERE id = $id");
        header("Location: financeiro.php");
        exit;
    }
}

if (isset($_GET["delete_despesa"])) {
    $id = (int)($_GET["delete_despesa"] ?? 0);
    if ($id > 0) {
        $conexao->query("DELETE FROM despesas WHERE id = $id");
        header("Location: financeiro.php");
        exit;
    }
}

if (isset($_GET["edit_pagamento"])) {
    $id = (int)($_GET["edit_pagamento"] ?? 0);
    if ($id > 0) {
        $resultado = $conexao->query("SELECT * FROM pagamentos WHERE id = $id LIMIT 1");
        if ($resultado && $resultado->num_rows > 0) {
            $pagamentoEditando = $resultado->fetch_assoc();
        }
    }
}

if (isset($_GET["edit_despesa"])) {
    $id = (int)($_GET["edit_despesa"] ?? 0);
    if ($id > 0) {
        $resultado = $conexao->query("SELECT * FROM despesas WHERE id = $id LIMIT 1");
        if ($resultado && $resultado->num_rows > 0) {
            $despesaEditando = $resultado->fetch_assoc();
        }
    }
}

$categoriasFinanceiras = [
    "Peça",
    "Mão de obra",
    "Frete",
    "Material",
    "Ferramenta",
    "Compra de componente",
    "Reparo externo",
    "Software",
    "Energia/telefone",
    "Transporte",
    "Outros"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["tipo_form"])) {
        if ($_POST["tipo_form"] === "pagamento") {
            $idPagamento = (int)($_POST["pagamento_id"] ?? 0);
            $ordemId = (int)($_POST["ordem_id"] ?? 0);
            $valorPago = (float)($_POST["valor_pago"] ?? 0);
            $forma = trim($_POST["forma_pagamento"] ?? "");
            $status = trim($_POST["status_pagamento"] ?? "Pago");
            $observacao = trim($_POST["observacao"] ?? "");

            if ($ordemId > 0 && $valorPago > 0 && $forma !== "") {
                $clienteId = (int) $conexao->query("SELECT cliente_id FROM ordens_servico WHERE id = $ordemId LIMIT 1")->fetch_assoc()["cliente_id"] ?? 0;
                if ($clienteId > 0) {
                    if ($idPagamento > 0) {
                        $stmt = $conexao->prepare("UPDATE pagamentos SET ordem_id = ?, cliente_id = ?, valor_pago = ?, forma_pagamento = ?, status_pagamento = ?, observacao = ? WHERE id = ?");
                        $stmt->bind_param("iidsssi", $ordemId, $clienteId, $valorPago, $forma, $status, $observacao, $idPagamento);
                        $mensagem = "Pagamento atualizado com sucesso.";
                    } else {
                        $stmt = $conexao->prepare("INSERT INTO pagamentos (ordem_id, cliente_id, valor_pago, forma_pagamento, data_pagamento, status_pagamento, observacao) VALUES (?, ?, ?, ?, NOW(), ?, ?)");
                        $stmt->bind_param("iidsss", $ordemId, $clienteId, $valorPago, $forma, $status, $observacao);
                        $mensagem = "Pagamento registrado com sucesso.";
                    }
                    $stmt->execute();
                } else {
                    $mensagem = "Ordem não encontrada.";
                }
            } else {
                $mensagem = "Preencha corretamente os dados do pagamento.";
            }
        }

        if ($_POST["tipo_form"] === "despesa") {
            $idDespesa = (int)($_POST["despesa_id"] ?? 0);
            $descricao = trim($_POST["descricao"] ?? "");
            $categoria = trim($_POST["categoria"] ?? "");
            $valor = (float)($_POST["valor"] ?? 0);
            $data = $_POST["data_despesa"] ?? date("Y-m-d");
            $observacao = trim($_POST["observacao_despesa"] ?? "");

            if ($descricao !== "" && $categoria !== "" && $valor > 0) {
                if ($idDespesa > 0) {
                    $stmt = $conexao->prepare("UPDATE despesas SET descricao = ?, categoria = ?, valor = ?, data_despesa = ?, observacao = ? WHERE id = ?");
                    $stmt->bind_param("ssdssi", $descricao, $categoria, $valor, $data, $observacao, $idDespesa);
                    $mensagem = "Despesa atualizada com sucesso.";
                } else {
                    $stmt = $conexao->prepare("INSERT INTO despesas (descricao, categoria, valor, data_despesa, observacao) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssdss", $descricao, $categoria, $valor, $data, $observacao);
                    $mensagem = "Despesa cadastrada com sucesso.";
                }
                $stmt->execute();
            } else {
                $mensagem = "Preencha corretamente a despesa.";
            }
        }
    }
}

$totalOrdens = (float)($conexao->query("SELECT COALESCE(SUM(valor), 0) as total FROM ordens_servico")->fetch_assoc()["total"] ?? 0);
$totalRecebido = (float)($conexao->query("SELECT COALESCE(SUM(valor_pago), 0) as total FROM pagamentos")->fetch_assoc()["total"] ?? 0);
$totalDespesa = (float)($conexao->query("SELECT COALESCE(SUM(valor), 0) as total FROM despesas")->fetch_assoc()["total"] ?? 0);
$totalPendente = max($totalOrdens - $totalRecebido, 0);
$saldoLiquido = $totalRecebido - $totalDespesa;

$ordens = $conexao->query("SELECT os.id, c.nome AS cliente, os.valor, os.status_os FROM ordens_servico os LEFT JOIN clientes c ON c.id = os.cliente_id ORDER BY os.data_entrada DESC");
$pagamentos = $conexao->query("SELECT p.*, c.nome AS cliente, os.id AS ordem_id FROM pagamentos p LEFT JOIN clientes c ON c.id = p.cliente_id LEFT JOIN ordens_servico os ON os.id = p.ordem_id ORDER BY p.data_pagamento DESC LIMIT 20");
$despesasLista = $conexao->query("SELECT * FROM despesas ORDER BY data_despesa DESC LIMIT 20");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro</title>
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="painel.php">Painel</a>
                <a href="ordens.php">Ordens</a>
                <a href="clientes.php">Clientes</a>
                <a href="logout.php">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <h1 class="page-title">Financeiro</h1>
        </div>

        <?php if ($mensagem !== "") : ?>
            <div class="alert success"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <section class="grid">
            <div class="metric card">
                <span>Faturamento bruto</span>
                <strong>R$ <?php echo number_format($totalOrdens, 2, ',', '.'); ?></strong>
            </div>
            <div class="metric card">
                <span>Recebido</span>
                <strong>R$ <?php echo number_format($totalRecebido, 2, ',', '.'); ?></strong>
            </div>
            <div class="metric card">
                <span>Pendente</span>
                <strong>R$ <?php echo number_format($totalPendente, 2, ',', '.'); ?></strong>
            </div>
            <div class="metric card">
                <span>Despesas</span>
                <strong>R$ <?php echo number_format($totalDespesa, 2, ',', '.'); ?></strong>
            </div>
            <div class="metric card">
                <span>Saldo líquido</span>
                <strong>R$ <?php echo number_format($saldoLiquido, 2, ',', '.'); ?></strong>
            </div>
        </section>

        <div class="grid">
            <div class="card">
                <h3><?php echo $pagamentoEditando ? "Editar pagamento" : "Registrar pagamento"; ?></h3>
                <form method="post" action="financeiro.php">
                    <input type="hidden" name="tipo_form" value="pagamento">
                    <input type="hidden" name="pagamento_id" value="<?php echo (int)($pagamentoEditando["id"] ?? 0); ?>">
                    <div>
                        <label>Ordem:</label>
                        <select name="ordem_id" required>
                            <option value="">Selecione</option>
                            <?php while ($ordem = $ordens->fetch_assoc()) : ?>
                                <option value="<?php echo (int)$ordem["id"]; ?>" <?php echo ((int)($pagamentoEditando["ordem_id"] ?? 0) === (int)$ordem["id"]) ? "selected" : ""; ?>>
                                    <?php echo (int)$ordem["id"]; ?> - <?php echo htmlspecialchars($ordem["cliente"] ?? "-"); ?> (R$ <?php echo number_format((float)($ordem["valor"] ?? 0), 2, ',', '.'); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label>Valor recebido:</label>
                        <input type="number" step="0.01" name="valor_pago" value="<?php echo htmlspecialchars((string)($pagamentoEditando["valor_pago"] ?? "")); ?>" required>
                    </div>

                    <div>
                        <label>Forma de pagamento:</label>
                        <select name="forma_pagamento" required>
                            <?php $formaAtual = $pagamentoEditando["forma_pagamento"] ?? "Dinheiro"; ?>
                            <?php foreach (["Dinheiro", "Pix", "Cartão", "Transferência"] as $forma) : ?>
                                <option value="<?php echo $forma; ?>" <?php echo $formaAtual === $forma ? "selected" : ""; ?>><?php echo $forma; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Status:</label>
                        <select name="status_pagamento">
                            <?php $statusAtual = $pagamentoEditando["status_pagamento"] ?? "Pago"; ?>
                            <?php foreach (["Pago", "Parcial", "Pendente"] as $status) : ?>
                                <option value="<?php echo $status; ?>" <?php echo $statusAtual === $status ? "selected" : ""; ?>><?php echo $status; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Observação:</label>
                        <textarea name="observacao"><?php echo htmlspecialchars($pagamentoEditando["observacao"] ?? ""); ?></textarea>
                    </div>

                    <button type="submit"><?php echo $pagamentoEditando ? "Salvar alterações" : "Salvar pagamento"; ?></button>
                </form>
            </div>

            <div class="card">
                <h3><?php echo $despesaEditando ? "Editar despesa" : "Registrar despesa"; ?></h3>
                <form method="post" action="financeiro.php">
                    <input type="hidden" name="tipo_form" value="despesa">
                    <input type="hidden" name="despesa_id" value="<?php echo (int)($despesaEditando["id"] ?? 0); ?>">
                    <div>
                        <label>Descrição:</label>
                        <input type="text" name="descricao" value="<?php echo htmlspecialchars($despesaEditando["descricao"] ?? ""); ?>" required>
                    </div>

                    <div>
                        <label>Categoria:</label>
                        <select name="categoria" required>
                            <option value="">Selecione</option>
                            <?php foreach ($categoriasFinanceiras as $categoriaOpcao) : ?>
                                <option value="<?php echo htmlspecialchars($categoriaOpcao); ?>" <?php echo (($despesaEditando["categoria"] ?? "") === $categoriaOpcao) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($categoriaOpcao); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Valor:</label>
                        <input type="number" step="0.01" name="valor" value="<?php echo htmlspecialchars((string)($despesaEditando["valor"] ?? "")); ?>" required>
                    </div>

                    <div>
                        <label>Data:</label>
                        <input type="date" name="data_despesa" value="<?php echo htmlspecialchars($despesaEditando["data_despesa"] ?? date('Y-m-d')); ?>" required>
                    </div>

                    <div>
                        <label>Observação:</label>
                        <textarea name="observacao_despesa"><?php echo htmlspecialchars($despesaEditando["observacao"] ?? ""); ?></textarea>
                    </div>

                    <button type="submit"><?php echo $despesaEditando ? "Salvar alterações" : "Salvar despesa"; ?></button>
                </form>
            </div>
        </div>

        <div class="card">
            <h3>Pagamentos recentes</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Ordem</th>
                        <th>Valor</th>
                        <th>Forma</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pagamentos && $pagamentos->num_rows > 0) : ?>
                        <?php while ($pagamento = $pagamentos->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo (int)$pagamento["id"]; ?></td>
                                <td><?php echo htmlspecialchars($pagamento["cliente"] ?? "-"); ?></td>
                                <td><?php echo (int)($pagamento["ordem_id"] ?? 0); ?></td>
                                <td>R$ <?php echo number_format((float)($pagamento["valor_pago"] ?? 0), 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($pagamento["forma_pagamento"] ?? "-"); ?></td>
                                <td><?php echo htmlspecialchars($pagamento["status_pagamento"] ?? "-"); ?></td>
                                <td><?php echo htmlspecialchars($pagamento["data_pagamento"] ?? "-"); ?></td>
                                <td class="inline-actions">
                                    <a class="btn btn-secondary" href="financeiro.php?edit_pagamento=<?php echo (int)$pagamento["id"]; ?>">Editar</a>
                                    <a class="btn btn-danger" href="financeiro.php?delete_pagamento=<?php echo (int)$pagamento["id"]; ?>" onclick="return confirm('Deseja excluir este pagamento?');">Excluir</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr><td colspan="8">Nenhum pagamento registrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Despesas recentes</h3>
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Valor</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($despesasLista && $despesasLista->num_rows > 0) : ?>
                        <?php while ($despesa = $despesasLista->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($despesa["descricao"] ?? "-"); ?></td>
                                <td><?php echo htmlspecialchars($despesa["categoria"] ?? "-"); ?></td>
                                <td>R$ <?php echo number_format((float)($despesa["valor"] ?? 0), 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($despesa["data_despesa"] ?? "-"); ?></td>
                                <td class="inline-actions">
                                    <a class="btn btn-secondary" href="financeiro.php?edit_despesa=<?php echo (int)$despesa["id"]; ?>">Editar</a>
                                    <a class="btn btn-danger" href="financeiro.php?delete_despesa=<?php echo (int)$despesa["id"]; ?>" onclick="return confirm('Deseja excluir esta despesa?');">Excluir</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr><td colspan="5">Nenhuma despesa registrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
