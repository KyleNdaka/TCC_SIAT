<?php
session_start();
require __DIR__ . "/conexao/conexao.php";

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
    $senha = isset($_POST["senha"]) ? $_POST["senha"] : "";

    if ($email === "" || $senha === "") {
        $erro = "Preencha e-mail e senha.";
    } else {
        $stmt = $conexao->prepare("SELECT id, nome, email, senha FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows === 1) {
            $dadosUsuario = $resultado->fetch_assoc();
            $senhaValida = password_verify($senha, $dadosUsuario["senha"]) || $senha === $dadosUsuario["senha"];

            if ($senhaValida) {
                $_SESSION["usuario_id"] = $dadosUsuario["id"];
                $_SESSION["usuario_nome"] = $dadosUsuario["nome"];
                $_SESSION["usuario_login"] = $dadosUsuario["email"];

                header("Location: painel.php");
                exit;
            } else {
                $erro = "Senha incorreta.";
            }
        } else {
            $erro = "E-mail não encontrado.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="index.php">Início</a>
            </nav>
        </div>
    </header>

    <main class="container" style="max-width: 500px;">
        <div class="card">
            <h2 class="page-title" style="margin-bottom: 10px;">Login</h2>

            <?php if ($erro !== "") : ?>
                <div class="alert error"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <div>
                    <label for="email">E-mail:</label>
                    <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>" required>
                </div>

                <div>
                    <label for="senha">Senha:</label>
                    <input id="senha" type="password" name="senha" required>
                </div>

                <button type="submit" class="btn">Entrar</button>
            </form>
        </div>
    </main>
</body>
</html>
