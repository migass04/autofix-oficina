<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];

$veiculo_selecionado = isset($_GET['veiculo_id']) ? (int)$_GET['veiculo_id'] : 0;

if ($user_tipo === 'cliente') {
    $stmtVeiculos = $pdo->prepare("
        SELECT v.id, v.marca, v.modelo, v.matricula 
        FROM veiculos v 
        JOIN clientes c ON v.cliente_id = c.id 
        JOIN utilizadores u ON c.email = u.email 
        WHERE u.id = ? 
        ORDER BY v.matricula ASC
    ");
    $stmtVeiculos->execute([$user_id]);
    $veiculos = $stmtVeiculos->fetchAll();

    $sqlHistorico = "
        SELECT r.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome
        FROM reparacoes r
        JOIN veiculos v ON r.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        JOIN utilizadores u ON c.email = u.email
        WHERE u.id = ? AND r.estado = 'Concluído'
    ";
    
    $params = [$user_id];
    if ($veiculo_selecionado > 0) {
        $sqlHistorico .= " AND v.id = ?";
        $params[] = $veiculo_selecionado;
    }
    $sqlHistorico .= " ORDER BY r.id DESC";
    
    $stmtHist = $pdo->prepare($sqlHistorico);
    $stmtHist->execute($params);
    $historico = $stmtHist->fetchAll();

} else {
    $veiculos = $pdo->query("SELECT v.id, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome FROM veiculos v JOIN clientes c ON v.cliente_id = c.id ORDER BY v.matricula ASC")->fetchAll();

    $sqlHistorico = "
        SELECT r.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome
        FROM reparacoes r
        JOIN veiculos v ON r.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE r.estado = 'Concluído'
    ";
    
    $params = [];
    if ($veiculo_selecionado > 0) {
        $sqlHistorico .= " AND v.id = ?";
        $params[] = $veiculo_selecionado;
    }
    $sqlHistorico .= " ORDER BY r.id DESC";
    
    $stmtHist = $pdo->prepare($sqlHistorico);
    $stmtHist->execute($params);
    $historico = $stmtHist->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Histórico de Manutenção</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-3 text-center">
            <img src="img/autofix.jpg" alt="AutoFix Logo" class="img-fluid rounded mb-3" style="max-width: 170px;">
            <a href="index.php">Dashboard</a>
            <a href="clientes.php">Clientes & Veículos</a>
            <a href="reparacoes.php">Reparações</a>
            <a href="agendamentos.php">Agendamentos</a>
            <a href="historico.php" class="active">Histórico</a>
            <a href="logout.php" class="text-danger mt-4 d-block">Sair</a>
        </div>

        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Histórico de Manutenções & Serviços</h2>
            </div>
            <hr>

            <div class="card p-3 mb-4 bg-light shadow-sm">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Filtrar por Veículo / Matrícula:</label>
                        <select name="veiculo_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">--- Todos os Veículos ---</option>
                            <?php foreach ($veiculos as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= $veiculo_selecionado == $v['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['matricula']) ?> - <?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?> 
                                    <?= isset($v['cliente_nome']) ? '(' . htmlspecialchars($v['cliente_nome']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($veiculo_selecionado > 0): ?>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="historico.php" class="btn btn-outline-secondary">Limpar Filtro</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover bg-white shadow-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Data Conclusão</th>
                            <th>Veículo</th>
                            <th>Proprietário</th>
                            <th>Serviço Efetuado / Descrição</th>
                            <th>Quilometragem</th>
                            <th>Custo Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historico)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Nenhum registo de manutenção concluída encontrado.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($historico as $h): ?>
                            <tr>
                                <td><strong><?= (!empty($h['data_fim']) && $h['data_fim'] !== '0000-00-00 00:00:00') ? date('d/m/Y', strtotime($h['data_fim'])) : 'Concluído' ?></strong></td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($h['matricula']) ?></span><br>
                                    <small><?= htmlspecialchars($h['marca'] . ' ' . $h['modelo']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($h['cliente_nome']) ?></td>
                                <td><?= nl2br(htmlspecialchars($h['descricao'])) ?></td>
                                <td><?= (!empty($h['km']) && $h['km'] > 0) ? number_format($h['km'], 0, ',', ' ') . ' km' : 'N/D' ?></td>
                                <td><strong class="text-success"><?= number_format($h['valor'], 2, ',', '.') ?> €</strong></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>