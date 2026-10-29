<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];
$user_nome = $_SESSION['user_nome'] ?? 'Utilizador';

$notificacoes = [];

if ($user_tipo === 'cliente') {
    // 1. Veículos prontos para levantamento
    $sqlProntos = "
        SELECT v.matricula, v.marca, v.modelo
        FROM reparacoes r
        JOIN veiculos v ON r.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        JOIN utilizadores u ON c.email = u.email
        WHERE u.id = ? AND r.estado = 'Concluído'
        ORDER BY r.id DESC LIMIT 3
    ";
    $stmtP = $pdo->prepare($sqlProntos);
    $stmtP->execute([$user_id]);
    $prontos = $stmtP->fetchAll();

    foreach ($prontos as $p) {
        $notificacoes[] = [
            'tipo' => 'success',
            'mensagem' => "O seu veículo <strong>" . htmlspecialchars($p['marca'] . ' ' . $p['modelo']) . " (" . htmlspecialchars($p['matricula']) . ")</strong> está pronto para levantamento!"
        ];
    }

    // 2. Veículos em reparação
    $sqlEmCurso = "
        SELECT COUNT(*) 
        FROM reparacoes r
        JOIN veiculos v ON r.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        JOIN utilizadores u ON c.email = u.email
        WHERE u.id = ? AND r.estado != 'Concluído'
    ";
    $stmtC = $pdo->prepare($sqlEmCurso);
    $stmtC->execute([$user_id]);
    $totalEmCurso = $stmtC->fetchColumn();

    if ($totalEmCurso > 0) {
        $notificacoes[] = [
            'tipo' => 'info',
            'mensagem' => "Tem <strong>{$totalEmCurso}</strong> serviço(s) atualmente em curso na oficina."
        ];
    }

    // 3. Agendamentos Pendentes
    $sqlAgPend = "
        SELECT COUNT(*) 
        FROM agendamentos a
        JOIN clientes c ON a.cliente_id = c.id
        JOIN utilizadores u ON c.email = u.email
        WHERE u.id = ? AND a.estado = 'Pendente'
    ";
    $stmtAg = $pdo->prepare($sqlAgPend);
    $stmtAg->execute([$user_id]);
    $agendamentosPendentes = $stmtAg->fetchColumn();

    if ($agendamentosPendentes > 0) {
        $notificacoes[] = [
            'tipo' => 'warning',
            'mensagem' => "Tem <strong>{$agendamentosPendentes}</strong> pedido(s) de agendamento a aguardar aprovação da oficina."
        ];
    }

} else {
    // Notificações para Chefe e Mecânico
    $agendamentosPendentes = $pdo->query("SELECT COUNT(*) FROM agendamentos WHERE estado = 'Pendente'")->fetchColumn();
    if ($agendamentosPendentes > 0) {
        $notificacoes[] = [
            'tipo' => 'warning',
            'mensagem' => "Existem <strong>{$agendamentosPendentes}</strong> novo(s) pedido(s) de agendamento a aguardar aprovação."
        ];
    }

    $pendentes = $pdo->query("SELECT COUNT(*) FROM reparacoes WHERE estado = 'Pendente'")->fetchColumn();
    if ($pendentes > 0) {
        $notificacoes[] = [
            'tipo' => 'info',
            'mensagem' => "Existem <strong>{$pendentes}</strong> reparação(ões) pendente(s) a aguardar início."
        ];
    }

    $emEmAndamento = $pdo->query("SELECT COUNT(*) FROM reparacoes WHERE estado = 'Em Andamento'")->fetchColumn();
    if ($emEmAndamento > 0) {
        $notificacoes[] = [
            'tipo' => 'primary',
            'mensagem' => "Existem <strong>{$emEmAndamento}</strong> veículo(s) a ser reparado(s) neste momento."
        ];
    }
}

// Totais Rápidos
$totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$totalVeiculos = $pdo->query("SELECT COUNT(*) FROM veiculos")->fetchColumn();
$totalReparacoes = $pdo->query("SELECT COUNT(*) FROM reparacoes WHERE estado != 'Concluído'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-3 text-center">
            <img src="img/autofix.jpg" alt="AutoFix Logo" class="img-fluid rounded mb-3" style="max-width: 170px;">
            <a href="index.php" class="active">Dashboard</a>
            <a href="clientes.php">Clientes & Veículos</a>
            <a href="reparacoes.php">Reparações</a>
            <a href="agendamentos.php">Agendamentos</a>
            <a href="historico.php">Histórico</a>
            <a href="logout.php" class="text-danger mt-4 d-block">Sair</a>
        </div>

        <!-- Conteúdo Principal -->
        <div class="col-md-10 p-4">
            <h2>Bem-vindo ao AutoFix, <?= htmlspecialchars($user_nome) ?>!</h2>
            <p class="text-muted">Painel Principal de Gestão da Oficina</p>
            <hr>

            <!-- Painel de Notificações / Avisos -->
            <?php if (!empty($notificacoes)): ?>
                <h4 class="mt-4 mb-3">Notificações e Informações</h4>
                <div class="row">
                    <div class="col-md-12">
                        <?php foreach ($notificacoes as $n): ?>
                            <div class="alert alert-<?= $n['tipo'] ?> d-flex align-items-center shadow-sm mb-2" role="alert">
                                <div><?= $n['mensagem'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cards Informativos -->
            <h4 class="mt-4 mb-3">Resumo Geral</h4>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card bg-primary text-white p-3 shadow-sm">
                        <h5>Clientes</h5>
                        <h2><?= $totalClientes ?></h2>
                        <small>Registados no sistema</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white p-3 shadow-sm">
                        <h5>Veículos</h5>
                        <h2><?= $totalVeiculos ?></h2>
                        <small>Ativos no parque</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-dark p-3 shadow-sm">
                        <h5>Em Reparação</h5>
                        <h2><?= $totalReparacoes ?></h2>
                        <small>Serviços ativos na oficina</small>
                    </div>
                </div>
            </div>

            <!-- Ações Rápidas -->
            <h4 class="mt-5 mb-3">Ações Rápidas</h4>
            <div class="d-flex gap-2">
                <a href="clientes.php" class="btn btn-outline-primary">Ver Clientes & Veículos</a>
                <a href="reparacoes.php" class="btn btn-outline-success">Gerir Reparações</a>
                <a href="agendamentos.php" class="btn btn-outline-warning text-dark">Agendamentos</a>
                <a href="historico.php" class="btn btn-outline-secondary">Ver Histórico</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>