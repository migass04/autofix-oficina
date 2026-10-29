<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];

// Criar Reparacao
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_reparacao']) && $user_tipo !== 'cliente') {
    $veiculo_id = (int)$_POST['veiculo_id'];
    $descricao = trim($_POST['descricao']);
    $valor = (float)$_POST['valor'];
    $km = !empty($_POST['km']) ? (int)$_POST['km'] : NULL;

    $stmt = $pdo->prepare("INSERT INTO reparacoes (veiculo_id, descricao, valor, km, estado, data_inicio) VALUES (?, ?, ?, ?, 'Pendente', NOW())");
    $stmt->execute([$veiculo_id, $descricao, $valor, $km]);
    header('Location: reparacoes.php');
    exit;
}

// Alterar Estado / Atualizar Valores
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_reparacao']) && $user_tipo !== 'cliente') {
    $reparacao_id = (int)$_POST['reparacao_id'];
    $estado = $_POST['estado'];
    $valor = (float)$_POST['valor'];

    if ($estado === 'Concluído') {
        $stmt = $pdo->prepare("UPDATE reparacoes SET estado = ?, valor = ?, data_fim = NOW() WHERE id = ?");
        $stmt->execute([$estado, $valor, $reparacao_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE reparacoes SET estado = ?, valor = ? WHERE id = ?");
        $stmt->execute([$estado, $valor, $reparacao_id]);
    }
    header('Location: reparacoes.php');
    exit;
}

// Obter Veículos
$veiculos = $pdo->query("SELECT v.id, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome FROM veiculos v JOIN clientes c ON v.cliente_id = c.id ORDER BY v.matricula ASC")->fetchAll();

// Obter Reparações Ativas (Não Concluídas)
if ($user_tipo === 'cliente') {
    $stmtRep = $pdo->prepare("
        SELECT r.*, v.marca, v.modelo, v.matricula 
        FROM reparacoes r 
        JOIN veiculos v ON r.veiculo_id = v.id 
        JOIN clientes c ON v.cliente_id = c.id 
        JOIN utilizadores u ON c.email = u.email 
        WHERE u.id = ? AND r.estado != 'Concluído'
        ORDER BY r.id DESC
    ");
    $stmtRep->execute([$user_id]);
    $reparacoes = $stmtRep->fetchAll();
} else {
    $reparacoes = $pdo->query("
        SELECT r.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome 
        FROM reparacoes r 
        JOIN veiculos v ON r.veiculo_id = v.id 
        JOIN clientes c ON v.cliente_id = c.id 
        WHERE r.estado != 'Concluído'
        ORDER BY r.id DESC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Gestão de Reparações</title>
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
            <a href="reparacoes.php" class="active">Reparações</a>
            <a href="agendamentos.php">Agendamentos</a>
            <a href="historico.php">Histórico</a>
            <a href="logout.php" class="text-danger mt-4 d-block">Sair</a>
        </div>

        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Serviços e Reparações Ativas</h2>
                <?php if ($user_tipo !== 'cliente'): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalReparacao">+ Nova Reparação</button>
                <?php endif; ?>
            </div>
            <hr>

            <div class="table-responsive">
                <table class="table table-striped table-hover bg-white shadow-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Veículo</th>
                            <?php if ($user_tipo !== 'cliente'): ?><th>Cliente</th><?php endif; ?>
                            <th>Descrição da Avaria / Serviço</th>
                            <th>Valor Est.</th>
                            <th>Estado</th>
                            <?php if ($user_tipo !== 'cliente'): ?><th>Ação</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reparacoes)): ?>
                        <tr>
                            <td colspan="<?= $user_tipo !== 'cliente' ? '7' : '5' ?>" class="text-center text-muted py-4">Nenhuma reparação em curso no momento.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($reparacoes as $r): ?>
                            <tr>
                                <td><?= $r['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($r['matricula']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($r['marca'] . ' ' . $r['modelo']) ?></small>
                                </td>
                                <?php if ($user_tipo !== 'cliente'): ?><td><?= htmlspecialchars($r['cliente_nome']) ?></td><?php endif; ?>
                                <td><?= nl2br(htmlspecialchars($r['descricao'])) ?></td>
                                <td><strong><?= number_format($r['valor'], 2, ',', '.') ?> €</strong></td>
                                <td>
                                    <?php 
                                        $badge = 'secondary';
                                        if ($r['estado'] === 'Pendente') $badge = 'warning';
                                        if ($r['estado'] === 'Em Andamento') $badge = 'primary';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= $r['estado'] ?></span>
                                </td>
                                <?php if ($user_tipo !== 'cliente'): ?>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $r['id'] ?>">Gerir</button>
                                    </td>
                                <?php endif; ?>
                            </tr>

                            <!-- Modal Editar Serviço -->
                            <?php if ($user_tipo !== 'cliente'): ?>
                            <div class="modal fade" id="modalEditar<?= $r['id'] ?>" tabindex="-1">
                              <div class="modal-dialog">
                                <form method="POST" class="modal-content">
                                  <div class="modal-header">
                                    <h5 class="modal-title">Atualizar Serviço #<?= $r['id'] ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                  </div>
                                  <div class="modal-body">
                                    <input type="hidden" name="atualizar_reparacao" value="1">
                                    <input type="hidden" name="reparacao_id" value="<?= $r['id'] ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Estado do Serviço</label>
                                        <select name="estado" class="form-select" required>
                                            <option value="Pendente" <?= $r['estado'] === 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                                            <option value="Em Andamento" <?= $r['estado'] === 'Em Andamento' ? 'selected' : '' ?>>Em Andamento</option>
                                            <option value="Concluído">Concluído (Enviar para Histórico)</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Valor do Serviço (€)</label>
                                        <input type="number" step="0.01" name="valor" class="form-control" value="<?= $r['valor'] ?>" required>
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="submit" class="btn btn-success">Guardar Alterações</button>
                                  </div>
                                </form>
                              </div>
                            </div>
                            <?php endif; ?>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Reparação -->
<div class="modal fade" id="modalReparacao" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Abrir Nova Reparação</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="nova_reparacao" value="1">
        <div class="mb-3">
            <label class="form-label">Veículo</label>
            <select name="veiculo_id" class="form-select" required>
                <?php foreach ($veiculos as $v): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['matricula']) ?> - <?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?> (<?= htmlspecialchars($v['cliente_nome']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Descrição do Problema / Serviço</label>
            <textarea name="descricao" class="form-control" rows="3" required></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Quilometragem Atual (Km)</label>
            <input type="number" name="km" class="form-control" placeholder="Ex: 125000">
        </div>
        <div class="mb-3">
            <label class="form-label">Valor Estimado (€)</label>
            <input type="number" step="0.01" name="valor" class="form-control" value="0.00" required>
        </div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">Abrir Serviço</button></div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>