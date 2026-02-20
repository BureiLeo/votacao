<?php
/**
 * api_resultados.php — Retorna resultados em JSON (usado pelo painel ao vivo)
 * Acessível publicamente (apenas leitura).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

require_once 'config/database.php';

try {
    $pdo = getDB();

    // --- Contadores gerais ---
    $total_codigos  = (int)$pdo->query("SELECT COUNT(*) FROM codigos")->fetchColumn();
    $codigos_usados = (int)$pdo->query("SELECT COUNT(*) FROM codigos WHERE usado = 1")->fetchColumn();

    // --- Resultados por cargo e candidato ---
    // Parte A: votos nominais (candidato_id NOT NULL)
    $sql_nominais = "
        SELECT
            ca.id            AS cargo_id,
            ca.nome          AS cargo_nome,
            ca.ordem         AS cargo_ordem,
            cd.id            AS candidato_id,
            cd.nome          AS candidato_nome,
            COUNT(v.id)      AS total_votos
        FROM cargos ca
        LEFT JOIN candidatos cd ON cd.cargo_id = ca.id AND cd.ativo = 1
        LEFT JOIN votos v        ON v.cargo_id = ca.id AND v.candidato_id = cd.id
        GROUP BY ca.id, ca.nome, ca.ordem, cd.id, cd.nome
        ORDER BY ca.ordem, cd.nome
    ";
    $stmt    = $pdo->query($sql_nominais);
    $linhas  = $stmt->fetchAll();

    // Parte B: votos nulos por cargo
    $sql_nulos = "
        SELECT cargo_id, COUNT(*) AS votos_nulos
        FROM votos
        WHERE candidato_id IS NULL
        GROUP BY cargo_id
    ";
    $nulos_raw = $pdo->query($sql_nulos)->fetchAll();
    $nulos     = [];
    foreach ($nulos_raw as $n) {
        $nulos[(int)$n['cargo_id']] = (int)$n['votos_nulos'];
    }

    // --- Monta estrutura de resposta ---
    $cargos = [];
    foreach ($linhas as $row) {
        $cid = (int)$row['cargo_id'];

        if (!isset($cargos[$cid])) {
            $cargos[$cid] = [
                'id'          => $cid,
                'nome'        => $row['cargo_nome'],
                'ordem'       => (int)$row['cargo_ordem'],
                'candidatos'  => [],
                'votos_nulos' => $nulos[$cid] ?? 0,
                'total_votos' => 0,
            ];
        }

        // Se o LEFT JOIN retornou linha com candidato (pode ser NULL quando cargo não tem candidatos)
        if ($row['candidato_id'] !== null) {
            $votos = (int)$row['total_votos'];
            $cargos[$cid]['candidatos'][] = [
                'id'    => (int)$row['candidato_id'],
                'nome'  => $row['candidato_nome'],
                'votos' => $votos,
            ];
            $cargos[$cid]['total_votos'] += $votos;
        }
    }

    // Soma os nulos ao total
    foreach ($cargos as $cid => &$cargo) {
        $cargo['total_votos'] += $cargo['votos_nulos'];
    }
    unset($cargo);

    // Reindexar (garante array JSON, não objeto)
    $cargos = array_values($cargos);

    $revelado = getConfig('revelado', 'false') === 'true';
    $inicioStr = getConfig('votacao_inicio', '');
    $votacao_inicio_ts = ($inicioStr && $inicioStr !== '') ? strtotime($inicioStr) : null;
    $vstatus = votacaoStatus(); // 'aguardando' | 'ativa' | 'encerrada' | 'revelada'

    // Enquanto não revelado, mascara votos individuais para ninguém
    // bisbilhotar a API. Mantém total_votos visível (quantos já votaram).
    if (!$revelado) {
        foreach ($cargos as &$cargo) {
            foreach ($cargo['candidatos'] as &$c) {
                $c['votos'] = null;
            }
            $cargo['votos_nulos'] = null;
        }
        unset($cargo, $c);
    }

    echo json_encode([
        'votacao_status'     => $vstatus,
        'votacao_ativa'      => votacaoAtiva(),
        'revelado'           => $revelado,
        'total_codigos'      => $total_codigos,
        'codigos_usados'     => $codigos_usados,
        'cargos'             => $cargos,
        'atualizado_em'      => date('H:i:s'),
        'votacao_inicio_ts'  => $votacao_inicio_ts,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
