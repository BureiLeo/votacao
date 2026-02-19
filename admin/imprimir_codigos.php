<?php
/**
 * admin/imprimir_codigos.php — Impressão de códigos em impressora térmica
 */
define('ADMIN_LOGIN_URL', 'index.php');
require '_auth.php';

$pdo = getDB();

// Filtro: todos | disponiveis | usados
$filtro = $_GET['filtro'] ?? 'disponiveis';
$largura = $_GET['largura'] ?? '80'; // 58mm ou 80mm

$where = match($filtro) {
    'usados'     => 'WHERE usado = 1',
    'todos'      => '',
    default      => 'WHERE usado = 0',
};

$codigos = $pdo->query(
    "SELECT codigo, usado FROM codigos $where ORDER BY criado_em ASC"
)->fetchAll();

$total = count($codigos);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Códigos — Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        /* ── Controles (some ao imprimir) ── */
        .controles {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .controles label { display:block; font-size:.8rem; color:#94a3b8; margin-bottom:4px; }
        .controles select, .controles input {
            background:#0f172a; color:#f1f5f9;
            border:1px solid #334155; border-radius:6px;
            padding:7px 10px; font-size:.9rem;
        }

        /* ── Preview dos tickets na tela ── */
        .tickets-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .ticket {
            background: #fff;
            color: #000;
            border: 1px dashed #999;
            border-radius: 4px;
            width: <?php echo $largura === '58' ? '155px' : '220px'; ?>;
            padding: 8px 10px;
            text-align: center;
            font-family: 'Courier New', monospace;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .ticket .evento {
            font-size: 7pt;
            color: #555;
            text-transform: uppercase;
            letter-spacing: .08em;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .ticket .codigo {
            font-size: <?php echo $largura === '58' ? '18pt' : '22pt'; ?>;
            font-weight: 900;
            letter-spacing: .18em;
            line-height: 1.1;
        }
        .ticket .instrucao {
            font-size: 6.5pt;
            color: #666;
            margin-top: 5px;
            border-top: 1px solid #ccc;
            padding-top: 4px;
        }
        .ticket.usado {
            opacity: .35;
        }

        /* ── Regras de impressão ── */
        @media print {
            @page {
                size: <?php echo $largura === '58' ? '58mm' : '80mm'; ?> auto;
                margin: 2mm;
            }

            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

            body { background: #fff !important; margin: 0; padding: 0; }

            /* Esconde tudo que não é ticket */
            .controles, .admin-nav-header, .admin-nav,
            nav, header, h1, h2, .alerta, .btn-imprimir-wrap { display: none !important; }

            .tickets-grid {
                display: block;
                gap: 0;
            }

            .ticket {
                width: auto !important;
                border: none !important;
                border-bottom: 1px dashed #999 !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 6px 2mm !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .ticket.usado { display: none; }
        }
    </style>
</head>
<body>

<div class="admin-nav-header">
    <h1>&#127891; Votação Jornada Jovem — Admin</h1>
    <form method="POST" action="index.php" style="display:inline">
        <button name="sair" class="btn btn-danger btn-sm">Sair</button>
    </form>
</div>
<nav class="admin-nav">
    <a href="index.php">&#127968; Dashboard</a>
    <a href="candidatos.php">&#128101; Candidatos</a>
    <a href="codigos.php">&#128273; Códigos</a>
    <a href="imprimir_codigos.php" class="ativo">&#128438; Imprimir</a>
    <a href="../painel/index.php" target="_blank">&#128200; Painel &#8599;</a>
</nav>

<div class="container" style="max-width:960px">

    <!-- Controles -->
    <div class="controles">
        <div>
            <label>Filtro</label>
            <select id="sel-filtro">
                <option value="disponiveis" <?php echo $filtro==='disponiveis'?'selected':''; ?>>Somente disponíveis</option>
                <option value="todos"       <?php echo $filtro==='todos'      ?'selected':''; ?>>Todos</option>
                <option value="usados"      <?php echo $filtro==='usados'     ?'selected':''; ?>>Somente usados</option>
            </select>
        </div>
        <div>
            <label>Largura do papel</label>
            <select id="sel-largura">
                <option value="80" <?php echo $largura==='80'?'selected':''; ?>>80 mm</option>
                <option value="58" <?php echo $largura==='58'?'selected':''; ?>>58 mm</option>
            </select>
        </div>
        <div>
            <label>Nome do evento (no ticket)</label>
            <input type="text" id="inp-evento" value="Eleição Jornada Jovem 2026" maxlength="40" style="width:220px">
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-secondary btn-sm" onclick="aplicarFiltro()">&#8635; Atualizar</button>
            <button class="btn btn-primary" onclick="window.print()">&#128438; Imprimir <?php echo $total; ?> código<?php echo $total!==1?'s':''; ?></button>
        </div>
    </div>

    <p class="text-muted" style="font-size:.82rem;margin-bottom:12px">
        Pré-visualização — <?php echo $total; ?> código(s) · Papel <?php echo $largura; ?>mm
    </p>

    <!-- Tickets -->
    <div class="tickets-grid" id="tickets-grid">
        <?php foreach ($codigos as $c): ?>
        <div class="ticket <?php echo $c['usado'] ? 'usado' : ''; ?>">
            <div class="evento" id="texto-evento">Eleição Jornada Jovem 2026</div>
            <div class="codigo"><?php echo htmlspecialchars($c['codigo']); ?></div>
            <div class="instrucao">Apresente este código na urna</div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($codigos)): ?>
            <p class="text-muted" style="font-size:.88rem">Nenhum código encontrado com o filtro selecionado.</p>
        <?php endif; ?>
    </div>

</div>

<script>
function aplicarFiltro() {
    const f = document.getElementById('sel-filtro').value;
    const l = document.getElementById('sel-largura').value;
    window.location = 'imprimir_codigos.php?filtro=' + f + '&largura=' + l;
}

// Atualiza o texto do evento em tempo real nos tickets
document.getElementById('inp-evento').addEventListener('input', function() {
    document.querySelectorAll('#tickets-grid .evento').forEach(el => {
        el.textContent = this.value || 'Eleição';
    });
});

// Antes de imprimir, injeta o nome do evento atual nos tickets
window.addEventListener('beforeprint', function() {
    const nome = document.getElementById('inp-evento').value || 'Eleição';
    document.querySelectorAll('#tickets-grid .evento').forEach(el => {
        el.textContent = nome;
    });
});
</script>

</body>
</html>
