<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

$filtroStatus = isset($_GET["status"]) ? trim($_GET["status"]) : "";
$filtroCliente = (int)($_GET["cliente"] ?? 0);

$sql = "
    SELECT os.id, c.nome AS cliente, a.marca, a.modelo, os.status_os, os.valor, os.data_entrada
    FROM ordens_servico os
    LEFT JOIN clientes c ON c.id = os.cliente_id
    LEFT JOIN aparelhos a ON a.id = os.aparelho_id
    WHERE 1 = 1";

if ($filtroCliente > 0) {
    $sql .= " AND os.cliente_id = $filtroCliente";
}

if ($filtroStatus !== "") {
    $sql .= " AND os.status_os = '" . $conexao->real_escape_string($filtroStatus) . "'";
}

$sql .= " ORDER BY os.data_entrada DESC";
$resultado = $conexao->query($sql);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ordens_exportadas.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, ['ID', 'Cliente', 'Marca', 'Modelo', 'Status', 'Valor', 'Data de entrada']);

if ($resultado && $resultado->num_rows > 0) {
    while ($ordem = $resultado->fetch_assoc()) {
        fputcsv($output, [
            (int)$ordem['id'],
            $ordem['cliente'] ?? '-',
            $ordem['marca'] ?? '-',
            $ordem['modelo'] ?? '-',
            $ordem['status_os'] ?? '-',
            number_format((float)($ordem['valor'] ?? 0), 2, ',', '.'),
            $ordem['data_entrada'] ?? '-'
        ]);
    }
}

fclose($output);
