<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];

// 1. Processar Novo Agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_agendamento'])) {
    $veiculo_id = (int)$_POST['veiculo_id'];
    $servico = trim($_POST['servico']);
    $data_agendamento = $_POST['data_agendamento'];
    $observacoes = trim($_POST['observacoes']);

    $stmtV = $pdo->prepare("SELECT cliente_id FROM veiculos WHERE id = ?");
    $stmtV->execute([$veiculo_id]);
    $cliente_id = $stmtV->fetchColumn();

    if ($cliente_id) {
        $stmtIns = $pdo->prepare("INSERT INTO agendamentos (cliente_id, veiculo_id, servico, data_agendamento, observacoes) VALUES (?, ?, ?, ?, ?)");
        $stmtIns->execute([$cliente_id, $veiculo_id, $servico, $data_agendamento, $observacoes]);
    }
    header('Location: agendamentos.php');
    exit;
}

// 2. Alterar Estado do Agendamento (Exclusivo Chefe e Mecânico)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_estado']) && $user_tipo !== 'cliente') {
    $agendamento_id = (int)$_POST['agendamento_id'];
    $novo_estado = $_POST['estado'];

    $stmtUpd = $pdo->prepare("UPDATE agendamentos SET estado = ? WHERE id = ?");
    $stmtUpd->execute([$novo_estado, $agendamento_id]);

    if ($novo_estado === 'Aprovado') {
        $stmtAg = $pdo->prepare("SELECT veiculo_id, servico FROM agendamentos WHERE id = ?");
        $stmtAg->execute([$agendamento_id]);
        $ag = $stmtAg->fetch();

        if ($ag) {
            $stmtRep = $pdo->prepare("INSERT INTO reparacoes (veiculo_id, descricao, estado, data_inicio, valor) VALUES (?, ?, 'Pendente', NOW(), 0.00)");
            $stmtRep->execute([$ag['veiculo_id'], "AGENDAMENTO: " . $ag['servico']]);
        }
    }

    header('Location: agendamentos.php');
    exit;
}

// Obter Veículos para o formulário
if ($user_tipo === 'cliente') {
    $stmtVeiculos = $pdo->prepare("
        SELECT v.id, v.marca, v.modelo, v.matricula 
        FROM veiculos v 
        JOIN clientes c ON v.cliente_id = c.id 
        JOIN utilizadores u ON c.email = u.email 
        WHERE u.id = ?
    ");
    $stmtVeiculos->execute([$user_id]);
    $veiculos = $stmtVeiculos->fetchAll();

    $stmtLista = $pdo->prepare("
        SELECT a.*, v.marca, v.modelo, v.matricula 
        FROM agendamentos a 
        JOIN veiculos v ON a.veiculo_id = v.id 
        JOIN clientes c ON a.cliente_id = c.id 
        JOIN utilizadores u ON c.email = u.email 
        WHERE u.id = ? 
        ORDER BY a.data_agendamento DESC
    ");
    $stmtLista->execute([$user_id]);
    $agendamentos = $stmtLista->fetchAll();
} else {
    $veiculos = $pdo->query("SELECT v.id, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome FROM veiculos v JOIN clientes c ON v.cliente_id = c.id ORDER BY v.matricula ASC")->fetchAll();

    $sqlLista = "
        SELECT a.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome, c.telefone 
        FROM agendamentos a 
        JOIN veiculos v ON a.veiculo_id = v.id 
        JOIN clientes c ON a.cliente_id = c.id 
        ORDER BY a.data_agendamento DESC
    ";
    $agendamentos = $pdo->query($sqlLista)->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Agendamentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-3 text-center">
            <img src="img/autofix.jpg" alt="AutoFix Logo" class="img-fluid rounded mb-3" style="max-width: 170px;">
            <a href="index.php">Dashboard</a>
            <a href="clientes.php">Clientes & Veículos</a>
            <a href="reparacoes.php">Reparações</a>
            <a href="agendamentos.php" class="active">Agendamentos</a>
            <a href="historico.php">Histórico</a>
            <a href="logout.php" class="text-danger mt-4 d-block">Sair</a>
        </div>

        <!-- Conteúdo Principal -->
        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Agendamento de Serviços</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgendamento">+ Pedir Marcação</button>
            </div>
            <hr>

            <!-- Tabela de Agendamentos -->
            <div class="table-responsive">
                <table class="table table-striped table-hover bg-white shadow-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Data & Hora</th>
                            <th>Veículo</th>
                            <?php if ($user_tipo !== 'cliente'): ?><th>Cliente / Contacto</th><?php endif; ?>
                            <th>Serviço Solicitado</th>
                            <th>Estado</th>
                            <?php if ($user_tipo !== 'cliente'): ?><th>Ação</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($agendamentos)): ?>
                        <tr>
                            <td colspan="<?= $user_tipo !== 'cliente' ? '6' : '4' ?>" class="text-center text-muted py-4">Nenhum agendamento registado.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($agendamentos as $a): ?>
                            <tr>
                                <td><strong><?= date('d/m/Y H:i', strtotime($a['data_agendamento'])) ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($a['matricula']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($a['marca'] . ' ' . $a['modelo']) ?></small>
                                </td>
                                <?php if ($user_tipo !== 'cliente'): ?>
                                    <td>
                                        <?= htmlspecialchars($a['cliente_nome']) ?><br>
                                        <small class="text-muted">Tel: <?= htmlspecialchars($a['telefone']) ?></small>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <strong><?= htmlspecialchars($a['servico']) ?></strong>
                                    <?php if (!empty($a['observacoes'])): ?>
                                        <br><small class="text-muted">Obs: <?= htmlspecialchars($a['observacoes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $badge = 'secondary';
                                        if ($a['estado'] === 'Pendente') $badge = 'warning';
                                        if ($a['estado'] === 'Aprovado') $badge = 'success';
                                        if ($a['estado'] === 'Recusado') $badge = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= $a['estado'] ?></span>
                                </td>
                                <?php if ($user_tipo !== 'cliente'): ?>
                                    <td>
                                        <?php if ($a['estado'] === 'Pendente'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="alterar_estado" value="1">
                                                <input type="hidden" name="agendamento_id" value="<?= $a['id'] ?>">
                                                <button type="submit" name="estado" value="Aprovado" class="btn btn-sm btn-success">Aprovar</button>
                                                <button type="submit" name="estado" value="Recusado" class="btn btn-sm btn-danger">Recusar</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">Processado</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Agendamento -->
<div class="modal fade" id="modalAgendamento" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Novo Agendamento de Serviço</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="novo_agendamento" value="1">
        <div class="mb-3">
            <label class="form-label">Veículo</label>
            <select name="veiculo_id" class="form-select" required>
                <option value="">Selecione o veículo...</option>
                <?php foreach ($veiculos as $v): ?>
                    <option value="<?= $v['id'] ?>">
                        <?= htmlspecialchars($v['matricula']) ?> - <?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?> 
                        <?= isset($v['cliente_nome']) ? '(' . htmlspecialchars($v['cliente_nome']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Tipo de Serviço Pretendido</label>
            <select name="servico" class="form-select" required>
                <option value="Revisão Geral">Revisão Geral</option>
                <option value="Mudança de Óleo e Filtros">Mudança de Óleo e Filtros</option>
                <option value="Substituição de Travões / Calços">Substituição de Travões / Calços</option>
                <option value="Troca / Calibração de Pneus">Troca / Calibração de Pneus</option>
                <option value="Diagnóstico de Avaria">Diagnóstico de Avaria</option>
                <option value="Outro Serviço">Outro Serviço</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Data e Hora Pretendida</label>
            <input type="datetime-local" name="data_agendamento" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Observações / Notas Adicionais</label>
            <textarea name="observacoes" class="form-rows-2 form-control" placeholder="Ex: Barulho na roda dianteira..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Confirmar Agendamento</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>