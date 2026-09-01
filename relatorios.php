<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

$valoresPorStatus = [];
$statuses = ["Recebido", "Em análise", "Aguardando peça", "Concluído"];
foreach ($statuses as $status) {
    $resultado = $conexao->query("SELECT COUNT(*) as total, COALESCE(SUM(valor), 0) as valor FROM ordens_servico WHERE status_os = '$status'");
    $valoresPorStatus[$status] = $resultado ? $resultado->fetch_assoc() : ["total" => 0, "valor" => 0];
}

$ultimasOrdens = $conexao->query(
    "SELECT os.id, c.nome as cliente, os.status_os, os.valor, os.data_entrada
     FROM ordens_servico os
     LEFT JOIN clientes c ON c.id = os.cliente_id
     ORDER BY os.data_entrada DESC
     LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios</title>
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
            <h1 class="page-title">Relatórios</h1>
        </div>

        <section class="grid">
            <?php foreach ($statuses as $status) : ?>
                <div class="metric card">
                    <span><?php echo $status; ?></span>
                    <strong><?php echo (int)($valoresPorStatus[$status]["total"] ?? 0); ?></strong>
                    <small>R$ <?php echo number_format((float)($valoresPorStatus[$status]["valor"] ?? 0), 2, ',', '.'); ?></small>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="card">
            <h3>Últimas ordens</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Status</th>
                        <th>Valor</th>
                        <th>Entrada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($ultimasOrdens && $ultimasOrdens->num_rows > 0) : ?>
                        <?php while ($ordem = $ultimasOrdens->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo (int)$ordem["id"]; ?></td>
                                <td><?php echo htmlspecialchars($ordem["cliente"] ?? "-"); ?></td>
                                <td><span class="status-badge <?php echo strtolower(str_replace(' ', '-', $ordem["status_os"] ?? "recebido")); ?>"><?php echo htmlspecialchars($ordem["status_os"] ?? "-"); ?></span></td>
                                <td>R$ <?php echo number_format((float)($ordem["valor"] ?? 0), 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($ordem["data_entrada"] ?? "-"); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">Nenhuma ordem registrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
