<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/database.php';

$pdo = banco();
$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
    $mes = date('Y-m');
}

$producao = $pdo->prepare(
    "SELECT COALESCE(SUM(litros), 0) AS litros, COUNT(DISTINCT data_producao) AS dias
     FROM producoes WHERE substr(data_producao, 1, 7) = ?"
);
$producao->execute([$mes]);
$resumoProducao = $producao->fetch();

$venda = $pdo->prepare(
    "SELECT COALESCE(SUM(litros), 0) AS litros,
            COALESCE(SUM(litros * valor_litro), 0) AS faturamento,
            COALESCE(SUM(litros * valor_litro) / NULLIF(SUM(litros), 0), 0) AS preco_medio
     FROM vendas WHERE substr(data_venda, 1, 7) = ?"
);
$venda->execute([$mes]);
$resumoVenda = $venda->fetch();

$porDia = [];
$stmt = $pdo->prepare(
    "SELECT data_producao AS data, SUM(litros) AS produzido
     FROM producoes WHERE substr(data_producao, 1, 7) = ?
     GROUP BY data_producao ORDER BY data_producao"
);
$stmt->execute([$mes]);
foreach ($stmt as $linha) {
    $porDia[$linha['data']] = ['produzido' => (float) $linha['produzido'], 'vendido' => 0, 'faturamento' => 0];
}

$stmt = $pdo->prepare(
    "SELECT data_venda AS data, SUM(litros) AS vendido, SUM(litros * valor_litro) AS faturamento
     FROM vendas WHERE substr(data_venda, 1, 7) = ?
     GROUP BY data_venda ORDER BY data_venda"
);
$stmt->execute([$mes]);
foreach ($stmt as $linha) {
    $porDia[$linha['data']] ??= ['produzido' => 0, 'vendido' => 0, 'faturamento' => 0];
    $porDia[$linha['data']]['vendido'] = (float) $linha['vendido'];
    $porDia[$linha['data']]['faturamento'] = (float) $linha['faturamento'];
}
ksort($porDia);

function n(float $valor, int $casas = 1): string
{
    return number_format($valor, $casas, ',', '.');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório mensal • RetiroGestor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="cabecalho">
        <div><span class="marca">📈 Relatório mensal</span><p>Indicadores de produção e vendas</p></div>
        <a class="link-cabecalho" href="index.php">Voltar ao dashboard</a>
    </div>
</header>
<main>
    <form class="filtro" method="get">
        <label>Mês de referência<input type="month" name="mes" value="<?= htmlspecialchars($mes, ENT_QUOTES, 'UTF-8') ?>"></label>
        <button type="submit">Atualizar relatório</button>
    </form>

    <section class="cards relatorio-cards">
        <article><span>Leite produzido</span><strong><?= n((float) $resumoProducao['litros']) ?> L</strong></article>
        <article><span>Leite vendido</span><strong><?= n((float) $resumoVenda['litros']) ?> L</strong></article>
        <article><span>Faturamento</span><strong>R$ <?= n((float) $resumoVenda['faturamento'], 2) ?></strong></article>
        <article><span>Preço médio por litro</span><strong>R$ <?= n((float) $resumoVenda['preco_medio'], 2) ?></strong></article>
    </section>

    <section class="historicos relatorio-tabela">
        <article>
            <h2>Movimento diário em <?= htmlspecialchars($mes, ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="tabela"><table>
                <thead><tr><th>Data</th><th>Produzido</th><th>Vendido</th><th>Faturamento</th></tr></thead>
                <tbody>
                <?php foreach ($porDia as $data => $valores): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($data)) ?></td>
                        <td><?= n($valores['produzido']) ?> L</td>
                        <td><?= n($valores['vendido']) ?> L</td>
                        <td>R$ <?= n($valores['faturamento'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$porDia): ?><tr><td colspan="4">Nenhum lançamento encontrado neste mês.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </article>
    </section>
</main>
<footer>Projeto acadêmico de Pedro H. Santo • Sistemas de Informação</footer>
</body>
</html>
