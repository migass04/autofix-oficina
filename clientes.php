<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];

// Eliminar Selecionados (Apenas Chefe)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_selecionados']) && $user_tipo === 'chefe') {
    if (!empty($_POST['clientes_ids'])) {
        $ids = array_map('intval', $_POST['clientes_ids']);
        $in  = str_repeat('?,', count($ids) - 1) . '?';
        
        $stmt = $pdo->prepare("DELETE FROM clientes WHERE id IN ($in)");
        $stmt->execute($ids);

        // Reordena IDs e reseta AUTO_INCREMENT
        $pdo->query("SET @count = 0;");
        $pdo->query("UPDATE clientes SET id = (@count := @count + 1) ORDER BY id ASC;");
        $pdo->query("ALTER TABLE clientes AUTO_INCREMENT = 1;");
    }
    header('Location: clientes.php');
    exit;
}

// Criar Cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_cliente'])) {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);

    $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, telefone) VALUES (?, ?, ?)");
    $stmt->execute([$nome, $email, $telefone]);
    header('Location: clientes.php');
    exit;
}

// Criar Veículo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_veiculo'])) {
    $cliente_id = (int)$_POST['cliente_id'];
    $tipo = $_POST['tipo'];
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $matricula = trim($_POST['matricula']);

    $stmt = $pdo->prepare("INSERT INTO veiculos (cliente_id, tipo, marca, modelo, matricula) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$cliente_id, $tipo, $marca, $modelo, $matricula]);
    header('Location: clientes.php');
    exit;
}

// Consultas
if ($user_tipo === 'cliente') {
    $stmt = $pdo->prepare("
        SELECT c.* FROM clientes c 
        JOIN utilizadores u ON c.email = u.email 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $clientes = $stmt->fetchAll();
} else {
    $clientes = $pdo->query("SELECT * FROM clientes ORDER BY id ASC")->fetchAll();
}

$veiculos = $pdo->query("SELECT v.*, c.nome AS cliente_nome FROM veiculos v JOIN clientes c ON v.cliente_id = c.id ORDER BY v.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Clientes & Veículos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-3 text-center">
            <img src="img/autofix.jpg" alt="AutoFix Logo" class="img-fluid rounded mb-3" style="max-width: 170px;">
            <a href="index.php">Dashboard</a>
            <a href="clientes.php" class="active">Clientes & Veículos</a>
            <a href="reparacoes.php">Reparações</a>
            <a href="agendamentos.php">Agendamentos</a>
            <a href="historico.php">Histórico</a>
            <a href="logout.php" class="text-danger mt-4 d-block">Sair</a>
        </div>

        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Gestão de Clientes e Veículos</h2>
                <?php if ($user_tipo !== 'cliente'): ?>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCliente">+ Novo Cliente</button>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalVeiculo">+ Novo Veículo</button>
                    </div>
                <?php endif; ?>
            </div>
            <hr>

            <form method="POST" id="formListaClientes">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Lista de Clientes</h4>
                    <?php if ($user_tipo === 'chefe'): ?>
                        <button type="submit" name="eliminar_selecionados" class="btn btn-danger btn-sm" onclick="return confirm('Tem a certeza que deseja eliminar os clientes selecionados?');">
                            Eliminar Selecionados
                        </button>
                    <?php endif; ?>
                </div>

                <div class="table-responsive mb-5">
                    <table class="table table-striped table-hover bg-white shadow-sm align-middle">
                        <thead class="table-dark">
                            <tr>
                                <?php if ($user_tipo === 'chefe'): ?>
                                    <th width="40"><input type="checkbox" id="selectAll"></th>
                                <?php endif; ?>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Telefone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $c): ?>
                            <tr>
                                <?php if ($user_tipo === 'chefe'): ?>
                                    <td><input type="checkbox" name="clientes_ids[]" value="<?= $c['id'] ?>" class="checkItem"></td>
                                <?php endif; ?>
                                <td><?= $c['id'] ?></td>
                                <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($c['email']) ?></td>
                                <td><?= htmlspecialchars($c['telefone']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <h4>Parque de Veículos Registados</h4>
            <div class="table-responsive">
                <table class="table table-striped table-hover bg-white shadow-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Matrícula</th>
                            <th>Tipo</th>
                            <th>Marca / Modelo</th>
                            <th>Proprietário</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($veiculos as $v): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($v['matricula']) ?></span></td>
                            <td><?= ucfirst(htmlspecialchars($v['tipo'])) ?></td>
                            <td><?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?></td>
                            <td><?= htmlspecialchars($v['cliente_nome']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Cliente -->
<div class="modal fade" id="modalCliente" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Registar Cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="novo_cliente" value="1">
        <div class="mb-3"><label class="form-label">Nome Completo</label><input type="text" name="nome" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Telefone</label><input type="text" name="telefone" class="form-control" required></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">Guardar</button></div>
    </form>
  </div>
</div>

<!-- Modal Novo Veículo -->
<div class="modal fade" id="modalVeiculo" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Registar Veículo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="novo_veiculo" value="1">
        <div class="mb-3">
            <label class="form-label">Cliente Proprietário</label>
            <select name="cliente_id" class="form-select" required>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select" required>
                <option value="carro">Carro</option>
                <option value="moto">Moto</option>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Marca</label><input type="text" name="marca" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Modelo</label><input type="text" name="modelo" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Matrícula</label><input type="text" name="matricula" class="form-control" required></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-success">Guardar Veículo</button></div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    let checkboxes = document.querySelectorAll('.checkItem');
    for (let checkbox of checkboxes) {
        checkbox.checked = this.checked;
    }
});
</script>
</body>
</html>