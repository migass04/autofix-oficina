<?php
session_start();
require_once 'config/db.php';

$token = $_GET['token'] ?? '';
$mensagem = '';
$erro = '';

if (empty($token)) {
    die("Token inválido.");
}

// Verificar se o token existe e não expirou
$stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE reset_token = ? AND reset_token_expira > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $erro = "O pedido de recuperação é inválido ou já expirou.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['nova_senha'];
    $nova_senha_hash = password_hash($nova_senha, PASSWORD_BCRYPT);

    // Atualizar a palavra-passe e anular o token
    $stmtUpdate = $pdo->prepare("UPDATE utilizadores SET senha = ?, reset_token = NULL, reset_token_expira = NULL WHERE id = ?");
    $stmtUpdate->execute([$nova_senha_hash, $user['id']]);

    $mensagem = "Palavra-passe alterada com sucesso! Já podes fazer login.";
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Nova Palavra-passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center" style="min-height: 100vh;">
<div class="card p-4 shadow" style="width: 400px;">
    <h3 class="text-center mb-3">🛠️ AutoFix</h3>
    <h5 class="text-center text-muted mb-4">Nova Palavra-passe</h5>

    <?php if ($mensagem): ?>
        <div class="alert alert-success p-2">
            <?= $mensagem ?><br>
            <a href="login.php" class="btn btn-primary btn-sm mt-2 w-100">Ir para o Login</a>
        </div>
    <?php elseif ($erro): ?>
        <div class="alert alert-danger p-2"><?= $erro ?></div>
    <?php else: ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nova Palavra-passe</label>
                <input type="password" name="nova_senha" class="form-control" required placeholder="Nova palavra-passe">
            </div>
            <button type="submit" class="btn btn-success w-100">Atualizar Palavra-passe</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>