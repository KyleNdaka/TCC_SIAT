<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

$totalClientes = (int) $conexao->query("SELECT COUNT(*) as total FROM clientes")->fetch_assoc()["total"];
$totalOrdens = (int) $conexao->query("SELECT COUNT(*) as total FROM ordens_servico")->fetch_assoc()["total"];
$totalConcluidas = (int) $conexao->query("SELECT COUNT(*) as total FROM ordens_servico WHERE status_os = 'Concluído'")->fetch_assoc()["total"];
$totalEmAnalise = (int) $conexao->query("SELECT COUNT(*) as total FROM ordens_servico WHERE status_os = 'Em análise'")->fetch_assoc()["total"];
$totalAguardando = (int) $conexao->query("SELECT COUNT(*) as total FROM ordens_servico WHERE status_os = 'Aguardando peça'")->fetch_assoc()["total"];
$valorTotal = $conexao->query("SELECT COALESCE(SUM(valor), 0) as total FROM ordens_servico")->fetch_assoc()["total"];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel</title>
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="clientes.php">Clientes</a>
                <a href="ordens.php">Ordens</a>
                <a href="relatorios.php">Relatórios</a>
                <a href="financeiro.php">Financeiro</a>
                <a href="despesas.php">Despesas</a>
                <a href="logout.php">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <h1 class="page-title">Painel do sistema</h1>
            <p>Bem-vindo, <strong><?php echo htmlspecialchars($_SESSION["usuario_nome"] ?? "Usuário"); ?></strong>!</p>
        </div>

        <section class="grid">
            <div class="metric card">
                <span>Clientes</span>
                <strong><?php echo $totalClientes; ?></strong>
            </div>
            <div class="metric card">
                <span>Ordens</span>
                <strong><?php echo $totalOrdens; ?></strong>
            </div>
            <div class="metric card">
                <span>Concluídas</span>
                <strong><?php echo $totalConcluidas; ?></strong>
            </div>
            <div class="metric card">
                <span>Em análise</span>
                <strong><?php echo $totalEmAnalise; ?></strong>
            </div>
            <div class="metric card">
                <span>Aguardando peça</span>
                <strong><?php echo $totalAguardando; ?></strong>
            </div>
            <div class="metric card">
                <span>Valor total</span>
                <strong>R$ <?php echo number_format((float)$valorTotal, 2, ',', '.'); ?></strong>
            </div>
        </section>

        <div class="card">
            <h3>Atalhos</h3>
            <div class="inline-actions">
                <a class="link-button" href="clientes.php">Cadastrar cliente</a>
                <a class="link-button" href="ordens.php">Nova ordem</a>
                <a class="link-button" href="financeiro.php">Financeiro</a>
                <a class="link-button" href="despesas.php">Despesas</a>
                <a class="link-button" href="relatorios.php">Ver relatórios</a>
            </div>
        </div>
    </main>
</body>
</html>
