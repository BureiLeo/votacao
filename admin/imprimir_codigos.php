<?php
/**
 * admin/imprimir_codigos.php — Impressão de códigos em impressora térmica
 */
define('ADMIN_LOGIN_URL', 'index.php');
require '_auth.php';

$pdo = getDB();

// ── Auto-migração: adiciona colunas de controle de impressão se não existirem ──
foreach ([
    "ALTER TABLE codigos ADD COLUMN impresso     TINYINT(1) NOT NULL DEFAULT 0   AFTER usado_em",
    "ALTER TABLE codigos ADD COLUMN impresso_em  DATETIME       NULL DEFAULT NULL AFTER impresso",
] as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) { /* coluna já existe */ }
}

// Parâmetros
$filtro     = $_GET['filtro']     ?? 'nao_impressos';
$largura    = $_GET['largura']    ?? '80';
$quantidade = isset($_GET['quantidade']) && $_GET['quantidade'] !== '0'
                ? max(1, (int)$_GET['quantidade'])
                : 0; // 0 = todos

$where = match($filtro) {
    'usados'       => 'WHERE usado = 1',
    'todos'        => '',
    'disponiveis'  => 'WHERE usado = 0',
    default        => 'WHERE impresso = 0 AND usado = 0',   // nao_impressos
};

$limit   = $quantidade > 0 ? "LIMIT $quantidade" : '';
$codigos = $pdo->query(
    "SELECT id, codigo, usado FROM codigos $where ORDER BY criado_em ASC $limit"
)->fetchAll();

$total = count($codigos);

// Contagem geral
$stats = $pdo->query(
    "SELECT
        SUM(usado=0 AND impresso=0) AS fila,
        SUM(impresso=1)             AS ja_impressos,
        SUM(usado=1)                AS ja_usados
     FROM codigos"
)->fetch();

// URL da urna (dinâmica, funciona em local e produção)
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$urnaUrl  = $scheme . '://' . $host . $basePath . '/urna/';
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
            margin-bottom: 16px;
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

        .info-bar {
            font-size:.83rem; color:#94a3b8;
            margin-bottom:14px;
            display:flex; gap:18px; flex-wrap:wrap;
        }
        .info-bar span strong { color:#f1f5f9; }

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
            font-size: 10pt; color: #555; font-weight: bold;
            text-transform: uppercase; letter-spacing: .08em;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px; margin-bottom: 6px;
        }
        .ticket .codigo {
            font-size: <?php echo $largura === '58' ? '18pt' : '22pt'; ?>;
            font-weight: 900; letter-spacing: .18em; line-height: 1.1;
        }
        .ticket .instrucao {
            font-size: 9pt; color: #666; font-weight: bold;
            margin-top: 5px; border-top: 1px solid #ccc; padding-top: 4px;
        }
        .ticket .horario {
            font-size: 9pt; color: #888; font-weight: bold; margin-top: 3px; letter-spacing: .04em;
        }
        .ticket .qr-wrap {
            margin: 6px auto 2px;
            display: flex; justify-content: center;
        }
        .ticket .qr-wrap img {
            width:  <?php echo $largura === '58' ? '64px' : '80px'; ?>;
            height: <?php echo $largura === '58' ? '64px' : '80px'; ?>;
            display: block;
        }

        #btn-imprimir.loading {
            opacity: .6; pointer-events: none; cursor: wait;
        }

        /* ── Regras de impressão ── */
        @media print {
            @page {
                size: <?php echo $largura === '58' ? '58mm' : '80mm'; ?> auto;
                margin: 2mm;
            }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { background: #fff !important; margin: 0; padding: 0; }

            .controles, .admin-nav-header, .admin-nav,
            nav, header, h1, h2, .alerta, .info-bar,
            #btn-imprimir-wrap { display: none !important; }

            .tickets-grid { display: block; gap: 0; }
            .ticket {
                width: auto !important; border: none !important;
                border-bottom: 1px dashed #999 !important;
                border-radius: 0 !important;
                margin: 0 !important; padding: 6px 2mm !important;
                page-break-inside: avoid; break-inside: avoid;
            }
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
                <option value="nao_impressos" <?php echo $filtro==='nao_impressos'?'selected':''; ?>>
                    Não impressos ainda<?php if ($stats['fila'] > 0): ?> (<?php echo (int)$stats['fila']; ?> na fila)<?php endif; ?>
                </option>
                <option value="disponiveis" <?php echo $filtro==='disponiveis'?'selected':''; ?>>Todos disponíveis</option>
                <option value="usados"      <?php echo $filtro==='usados'     ?'selected':''; ?>>Somente usados</option>
                <option value="todos"       <?php echo $filtro==='todos'      ?'selected':''; ?>>Todos</option>
            </select>
        </div>
        <div>
            <label>Quantidade a imprimir</label>
            <select id="sel-quantidade">
                <?php
                $opts = [1 => '1', 5 => '5', 10 => '10', 25 => '25', 50 => '50', 100 => '100', 0 => 'Todos'];
                foreach ($opts as $v => $l):
                    $sel = ($quantidade === $v || ($v === 0 && $quantidade === 0)) ? 'selected' : '';
                ?>
                <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
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
        </div>
    </div>

    <!-- Info + botão de impressão -->
    <div class="info-bar">
        <span>&#128220; Na fila: <strong><?php echo (int)$stats['fila']; ?></strong></span>
        <span>&#128438; Já impressos: <strong><?php echo (int)$stats['ja_impressos']; ?></strong></span>
        <span>&#9989; Já votaram: <strong><?php echo (int)$stats['ja_usados']; ?></strong></span>
        <span>Mostrando: <strong><?php echo $total; ?> ticket<?php echo $total!==1?'s':''; ?></strong></span>
    </div>

    <div id="btn-imprimir-wrap" style="margin-bottom:16px">
        <?php if ($total > 0): ?>
        <button id="btn-imprimir" class="btn btn-primary" onclick="marcarEImprimir()">
            &#128438; Imprimir <?php echo $total; ?> código<?php echo $total!==1?'s':''; ?>
        </button>
        <span style="font-size:.8rem;color:#94a3b8;margin-left:10px">
            Após a impressão esses códigos saem da fila e não aparecem novamente.
        </span>
        <?php else: ?>
        <p class="text-muted" style="font-size:.9rem">
            &#9989; Nenhum código pendente com o filtro selecionado.
            <?php if ($filtro === 'nao_impressos' && (int)$stats['ja_impressos'] > 0): ?>
            Todos os <?php echo (int)$stats['ja_impressos']; ?> código(s) já foram impressos.
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Tickets -->
    <div class="tickets-grid" id="tickets-grid">
        <?php foreach ($codigos as $c): ?>
        <div class="ticket" data-id="<?php echo $c['id']; ?>">
            <div class="evento">Eleição Jornada Jovem 2026</div>
            <div class="codigo"><?php echo htmlspecialchars($c['codigo']); ?></div>
            <div class="qr-wrap">
                <img class="qr-img" src="" alt="QR" data-url="<?php echo htmlspecialchars($urnaUrl); ?>">
            </div>
            <div class="instrucao">Utilize esse codigo para votar!</div>
            <div class="horario"></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($codigos)): ?>
            <p class="text-muted" style="font-size:.88rem">Nenhum código encontrado com o filtro selecionado.</p>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// ── Navegação ────────────────────────────────────────────────
function aplicarFiltro() {
    const f = document.getElementById('sel-filtro').value;
    const l = document.getElementById('sel-largura').value;
    const q = document.getElementById('sel-quantidade').value;
    window.location = 'imprimir_codigos.php?filtro=' + f + '&largura=' + l + '&quantidade=' + q;
}

// ── Nome do evento em tempo real ─────────────────────────────
document.getElementById('inp-evento')?.addEventListener('input', function () {
    document.querySelectorAll('#tickets-grid .evento').forEach(el => {
        el.textContent = this.value || 'Eleição';
    });
});

// ── QR codes ─────────────────────────────────────────────────
document.querySelectorAll('.qr-img').forEach(img => {
    const url  = img.dataset.url;
    const size = <?php echo $largura === '58' ? 64 : 80; ?>;
    const tmp  = document.createElement('div');
    tmp.style.display = 'none';
    document.body.appendChild(tmp);
    new QRCode(tmp, { text: url, width: size, height: size,
        colorDark: '#000000', colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M });
    const canvas = tmp.querySelector('canvas');
    if (canvas) img.src = canvas.toDataURL('image/png');
    document.body.removeChild(tmp);
});

// ── Data/hora ─────────────────────────────────────────────────
function horaAtual() {
    return new Date().toLocaleString('pt-BR', {
        day:'2-digit', month:'2-digit', year:'numeric',
        hour:'2-digit', minute:'2-digit'
    }).replace(',', ' •');
}
document.querySelectorAll('#tickets-grid .horario').forEach(el => {
    el.textContent = 'Impresso em ' + horaAtual();
});

window.addEventListener('beforeprint', function () {
    const nome = document.getElementById('inp-evento')?.value || 'Eleição';
    const hora = horaAtual();
    document.querySelectorAll('#tickets-grid .evento').forEach(el => el.textContent = nome);
    document.querySelectorAll('#tickets-grid .horario').forEach(el => el.textContent = 'Impresso em ' + hora);
});

// ── Marcar como impresso → depois imprimir ───────────────────
async function marcarEImprimir() {
    const btn = document.getElementById('btn-imprimir');
    if (!btn) return;

    const ids = Array.from(
        document.querySelectorAll('#tickets-grid .ticket[data-id]')
    ).map(el => parseInt(el.dataset.id, 10)).filter(Boolean);

    if (ids.length === 0) { window.print(); return; }

    btn.classList.add('loading');
    btn.textContent = '⏳ Preparando…';

    try {
        const res = await fetch('api_marcar_impresso.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ ids })
        });
        const data = await res.json();

        if (!data.ok) {
            alert('Erro ao registrar impressão: ' + (data.erro ?? 'desconhecido'));
            btn.classList.remove('loading');
            btn.textContent = '🖨️ Imprimir';
            return;
        }

        // Imprime e recarrega após fechar o diálogo
        window.print();
        window.addEventListener('afterprint', function onAfter() {
            window.removeEventListener('afterprint', onAfter);
            window.location.reload();
        }, { once: true });

    } catch (err) {
        alert('Falha na comunicação com o servidor.');
        btn.classList.remove('loading');
        btn.textContent = '🖨️ Imprimir';
    }
}
</script>

</body>
</html>
