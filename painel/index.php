<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel ao Vivo — Eleição Jornada Jovem</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        /* ── Base escura ── */
        body { background: #0f172a; color: #f1f5f9; margin: 0; }

        /* ── Topo ── */
        .painel-topo {
            background: linear-gradient(135deg, #1e1b4b, #312e81);
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .painel-topo h1 { font-size: 1.35rem; font-weight: 800; }
        .painel-topo p  { font-size: .82rem; opacity: .7; margin-top: 3px; }
        .painel-status  { display: flex; gap: 10px; font-size: .83rem; opacity: .9; flex-wrap: wrap; }
        .status-pill {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(255,255,255,.1);
            padding: 5px 12px; border-radius: 99px;
        }
        .status-dot { width: 9px; height: 9px; border-radius: 50%; display:inline-block; }
        .dot-verde    { background: #4ade80; animation: pulsar 1.5s infinite; }
        .dot-vermelho { background: #f87171; }
        .dot-amarelo  { background: #fbbf24; animation: pulsar 1.5s infinite; }
        @keyframes pulsar { 0%,100%{opacity:1} 50%{opacity:.3} }

        /* ── Grid ── */
        .painel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 18px;
            padding: 20px;
        }

        /* ── Card ── */
        .cargo-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            overflow: hidden;
        }
        .cargo-card-header {
            background: #4f46e5;
            padding: 11px 16px;
            font-weight: 700;
            font-size: .92rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-badge {
            background: rgba(255,255,255,.2);
            padding: 2px 10px;
            border-radius: 99px;
            font-size: .76rem;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ── Linha candidato (oculto) ── */
        .cand-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            border-bottom: 1px solid #2d3d55;
            gap: 10px;
        }
        .cand-row:last-child { border-bottom: none; }
        .cand-nome { font-size: .9rem; flex: 1; }
        .cand-lock { color: #475569; font-size: .85rem; }

        /* ── Linha candidato (revelado) ── */
        .cand-row.revelado {
            flex-direction: column;
            align-items: flex-start;
            padding: 12px 16px;
            gap: 6px;
        }
        .cand-row.revelado .cand-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        .cand-row.revelado .cand-nome { font-weight: 600; }

        .votos-num {
            background: #4f46e5; color: #fff;
            padding: 2px 12px; border-radius: 99px;
            font-weight: 700; font-size: .85rem;
            min-width: 36px; text-align: center;
        }
        .votos-num.nulo  { background: #475569; }
        .votos-num.lider { background: #16a34a; }

        /* Barra */
        .barra-wrap { width:100%; background:#0f172a; border-radius:99px; height:7px; overflow:hidden; }
        .barra-fill {
            height: 7px; border-radius: 99px;
            background: #4f46e5;
            transition: width 1.3s cubic-bezier(.4,0,.2,1);
            width: 0;
        }
        .barra-fill.lider { background: #16a34a; }
        .barra-fill.nulo  { background: #475569; }
        .pct-label { font-size: .72rem; color: #64748b; }

        /* Vencedor */
        .cand-row.vencedor {
            background: linear-gradient(90deg, rgba(22,163,74,.18), transparent);
            border-left: 3px solid #16a34a;
        }

        /* Animação de revelação */
        @keyframes revelar {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animar { animation: revelar .55s ease both; }

        /* ── Suspense ── */
        .suspense-overlay {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            min-height: 60vh; text-align: center; padding: 40px 20px;
        }
        .suspense-overlay .icone { font-size: 5rem; margin-bottom: 20px; }
        .suspense-overlay h2 { font-size: 1.8rem; color: #f8fafc; margin-bottom: 10px; }
        .suspense-overlay p  { color: #64748b; }
        .pontos::after {
            content: '';
            animation: pontos 1.5s steps(4,end) infinite;
        }
        @keyframes pontos {
            0%{content:'.'} 33%{content:'..'} 66%{content:'...'} 100%{content:''}
        }

        .rodape { text-align:center; padding:14px; font-size:.75rem; color:#334155; }
    </style>
</head>
<body>

<div class="painel-topo">
    <div>
        <h1>&#127891; Eleição Jornada Jovem 2026</h1>
        <p id="subtitulo">Painel de resultados ao vivo</p>
    </div>
    <div class="painel-status">
        <div class="status-pill">
            <span class="status-dot dot-verde" id="dot"></span>
            <span id="status-txt">Conectando…</span>
        </div>
        <div class="status-pill">&#128101; <span id="participantes">-/-</span></div>
        <div class="status-pill">&#8987; <span id="hora">--:--:--</span></div>
        <div class="status-pill" id="pill-timer" style="display:none">&#9201; <span id="timer-txt">00:00</span></div>
    </div>
</div>

<div id="conteudo">
    <div class="suspense-overlay">
        <div class="icone">&#128200;</div>
        <h2>Carregando<span class="pontos"></span></h2>
    </div>
</div>

<div class="rodape">Atualiza automaticamente a cada 5 segundos</div>

<script>
const API_URL    = '../api_resultados.php';
let ultimoEstado = null;
let ultimoJson   = '';
let timerInterval = null;
let timerInicioTs = null;

function formatarTempo(s) {
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const ss = s % 60;
    if (h > 0) return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(ss).padStart(2,'0');
    return String(m).padStart(2,'0') + ':' + String(ss).padStart(2,'0');
}

function iniciarTimer(inicioTs) {
    timerInicioTs = inicioTs;
    document.getElementById('pill-timer').style.display = '';
    if (timerInterval) return; // já rodando
    timerInterval = setInterval(() => {
        const elapsed = Math.max(0, Math.floor(Date.now() / 1000) - timerInicioTs);
        document.getElementById('timer-txt').textContent = formatarTempo(elapsed);
    }, 1000);
}

function pararTimer() {
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    document.getElementById('pill-timer').style.display = 'none';
    timerInicioTs = null;
}

async function atualizar() {
    try {
        const res  = await fetch(API_URL + '?t=' + Date.now());
        const data = await res.json();
        const estado = resolveEstado(data);
        const dot    = document.getElementById('dot');

        // ── Cabeçalho ──
        document.getElementById('hora').textContent = data.atualizado_em || '--:--:--';
        document.getElementById('participantes').textContent =
            (data.codigos_usados ?? '?') + ' / ' + (data.total_codigos ?? '?');

        // Cronômetro: só ativo quando votacao_ativa e há timestamp de início
        if (estado === 'andamento' && data.votacao_inicio_ts) {
            if (timerInicioTs !== data.votacao_inicio_ts) iniciarTimer(data.votacao_inicio_ts);
        } else {
            pararTimer();
        }

        dot.className = 'status-dot ';
        if (estado === 'aguardando') {
            dot.classList.add('dot-vermelho');
            document.getElementById('status-txt').textContent = 'Aguardando início';
            document.getElementById('subtitulo').textContent  = 'A votação ainda não foi iniciada';
        } else if (estado === 'andamento') {
            dot.classList.add('dot-verde');
            document.getElementById('status-txt').textContent = 'Votação em andamento';
            document.getElementById('subtitulo').textContent  = 'Acompanhe em tempo real';
        } else if (estado === 'suspense') {
            dot.classList.add('dot-vermelho');
            document.getElementById('status-txt').textContent = 'Votação encerrada';
            document.getElementById('subtitulo').textContent  = 'Aguardando revelação';
        } else {
            dot.classList.add('dot-amarelo');
            document.getElementById('status-txt').textContent = 'Resultados revelados';
            document.getElementById('subtitulo').textContent  = 'Resultado final ✨';
        }

        // ── Conteúdo (só re-renderiza se mudou) ──
        const json = JSON.stringify(data);
        if (json === ultimoJson && estado === ultimoEstado) return;

        const mudouParaRevelado = (ultimoEstado !== 'revelado' && estado === 'revelado');
        ultimoEstado = estado;
        ultimoJson   = json;

        if (estado === 'aguardando')    renderAguardando();
        else if (estado === 'andamento')     renderAndamento(data);
        else if (estado === 'suspense') renderSuspense();
        else                            renderRevelado(data, mudouParaRevelado);

    } catch (e) {
        document.getElementById('dot').className = 'status-dot dot-amarelo';
        document.getElementById('status-txt').textContent = 'Sem conexão';
    }
}

function resolveEstado(data) {
    const s = data.votacao_status;
    if (s === 'revelada')   return 'revelado';
    if (s === 'ativa')      return 'andamento';
    if (s === 'encerrada')  return 'suspense';
    return 'aguardando'; // 'aguardando' ou legado
}
// ─── MODO 0 — AGUARDANDO INÍCIO ────────────────────────────────────
function renderAguardando() {
    document.getElementById('conteudo').innerHTML = `
        <div class="suspense-overlay">
            <div class="icone">&#9203;</div>
            <h2>Aguardando início da votação<span class="pontos"></span></h2>
            <p>A votação ainda não foi iniciada. Este painel atualizará automaticamente.</p>
        </div>`;
}
// ─── MODO 1 — EM ANDAMENTO ──────────────────────────────────
// Candidatos visíveis, votos bloqueados, total do cargo exibido
function renderAndamento(data) {
    const cargos = data.cargos || [];
    if (!cargos.length) {
        document.getElementById('conteudo').innerHTML =
            '<div class="suspense-overlay"><p>Nenhum cargo cadastrado.</p></div>';
        return;
    }

    const cards = cargos.map(cargo => {
        const linhas = cargo.candidatos.map(c => `
            <div class="cand-row">
                <span class="cand-nome">${esc(c.nome)}</span>
                <span class="cand-lock">&#128274;</span>
            </div>`).join('');

        return `
        <div class="cargo-card">
            <div class="cargo-card-header">
                <span>${esc(cargo.nome)}</span>
                <span class="total-badge">&#128203; ${cargo.total_votos} voto${cargo.total_votos !== 1 ? 's' : ''}</span>
            </div>
            ${linhas}
            <div class="cand-row" style="opacity:.45">
                <span class="cand-nome" style="font-style:italic">&#128683; Voto Nulo</span>
                <span class="cand-lock">&#128274;</span>
            </div>
        </div>`;
    }).join('');

    document.getElementById('conteudo').innerHTML = `<div class="painel-grid">${cards}</div>`;
}

// ─── MODO 2 — SUSPENSE ──────────────────────────────────────
// Votação encerrada, admin ainda não revelou
function renderSuspense() {
    document.getElementById('conteudo').innerHTML = `
        <div class="suspense-overlay">
            <div class="icone">&#127914;</div>
            <h2>Votação encerrada</h2>
            <p>Aguardando revelação dos resultados<span class="pontos"></span></p>
            <p style="margin-top:10px;font-size:.82rem;color:#334155">
                O administrador revelará os vencedores em breve.
            </p>
        </div>`;
}

// ─── MODO 3 — REVELADO ──────────────────────────────────────
// Votos reais + barras de progresso + destaque do vencedor
function renderRevelado(data, animar) {
    const cargos = data.cargos || [];

    const cards = cargos.map((cargo, idx) => {
        const total    = cargo.total_votos  || 0;
        const nulos    = cargo.votos_nulos  ?? 0;

        // Ordena por votos (desc) somente na revelação
        const candidatos = [...cargo.candidatos].sort((a, b) => (b.votos ?? 0) - (a.votos ?? 0));
        const maxVotos = Math.max(...candidatos.map(c => c.votos ?? 0), 0);

        const delay = i => animar ? `animation-delay:${(idx * 0.08 + i * 0.05).toFixed(2)}s` : '';

        const linhas = candidatos.map((c, i) => {
            const votos   = c.votos  ?? 0;
            const pct     = total > 0 ? Math.round((votos / total) * 100) : 0;
            const isLider = votos > 0 && votos === maxVotos;
            return `
            <div class="cand-row revelado ${isLider ? 'vencedor animar' : (animar ? 'animar' : '')}"
                 style="${delay(i)}">
                <div class="cand-topo">
                    <span class="cand-nome">
                        ${isLider ? '&#127942; ' : ''}${esc(c.nome)}
                    </span>
                    <span class="votos-num ${isLider ? 'lider' : ''}">${votos}</span>
                </div>
                <div class="barra-wrap">
                    <div class="barra-fill ${isLider ? 'lider' : ''}" data-pct="${pct}"></div>
                </div>
                <span class="pct-label">${pct}%</span>
            </div>`;
        }).join('');

        const pctNulo = total > 0 ? Math.round((nulos / total) * 100) : 0;
        const linhaNulo = `
            <div class="cand-row revelado ${animar ? 'animar' : ''}"
                 style="opacity:.55;${delay(candidatos.length)}">
                <div class="cand-topo">
                    <span class="cand-nome">&#128683; Voto Nulo</span>
                    <span class="votos-num nulo">${nulos}</span>
                </div>
                <div class="barra-wrap">
                    <div class="barra-fill nulo" data-pct="${pctNulo}"></div>
                </div>
                <span class="pct-label">${pctNulo}%</span>
            </div>`;

        return `
        <div class="cargo-card ${animar ? 'animar' : ''}"
             style="${animar ? `animation-delay:${(idx * 0.1).toFixed(2)}s` : ''}">
            <div class="cargo-card-header">
                <span>${esc(cargo.nome)}</span>
                <span class="total-badge">&#128203; ${total} voto${total !== 1 ? 's' : ''}</span>
            </div>
            ${linhas}
            ${linhaNulo}
        </div>`;
    }).join('');

    document.getElementById('conteudo').innerHTML = `<div class="painel-grid">${cards}</div>`;

    // Anima barras após render
    requestAnimationFrame(() => {
        setTimeout(() => {
            document.querySelectorAll('.barra-fill[data-pct]').forEach(el => {
                el.style.width = el.dataset.pct + '%';
            });
        }, animar ? 700 : 50);
    });
}

function esc(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

atualizar();
setInterval(atualizar, 5000);
</script>
</body>
</html>
