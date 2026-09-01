<?php

$dbHost = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'localhost';
$dbUser = getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'root';
$dbPass = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? '';
$dbName = getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'assistencia_tecnica';

$conexao = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

if (!$conexao) {
    $mensagemErroConexao = "Erro na conexão com o banco de dados: " . mysqli_connect_error();
} else {
    $mensagemErroConexao = null;
}

?>