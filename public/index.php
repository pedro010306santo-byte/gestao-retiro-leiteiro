<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/database.php';

$pdo = banco();
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $mensagem = salvarRegistro($pdo, $_POST['acao'] ?? '');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = $e->getMessage();
    }
}

$resumo = resumo($pdo);
$producoes = $pdo->query('SELECT * FROM producoes ORDER BY data_producao DESC, id DESC LIMIT 8')->fetchAll();
$movimentos = $pdo->query('SELECT * FROM racao_movimentos ORDER BY data_movimento DESC, id DESC LIMIT 8')->fetchAll();
$vendas = $pdo->query('SELECT * FROM vendas ORDER BY data_venda DESC, id DESC LIMIT 8')->fetchAll();
$animais = $pdo->query('SELECT * FROM animais ORDER BY status, brinco LIMIT 50')->fetchAll();
$hoje = date('Y-m-d');

function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function numero(float $valor, int $casas = 1): string
{
    return number_format($valor, $casas, ',', '.');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Gestão de produção, estoque e vendas de um retiro leiteiro">
    <title>RetiroGestor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div>
        <span class="marca">🐄 RetiroGestor</span>
        <p>Produção, estoque e vendas em um só lugar</p>
    </div>
</header>

<main>
    <?php if ($mensagem): ?><div class="alerta sucesso"><?= e($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>

    <section class="cards" aria-label="Resumo">
        <article><span>Leite produzido hoje</span><strong><?= numero($resumo['producao_hoje']) ?> L</strong></article>
        <article><span>Leite disponível</span><strong><?= numero($resumo['leite_disponivel']) ?> L</strong></article>
        <article><span>Ração disponível</span><strong><?= numero($resumo['racao_disponivel']) ?> kg</strong></article>
        <article><span>Faturamento total</span><strong>R$ <?= numero($resumo['faturamento'], 2) ?></strong></article>
        <article><span>Animais ativos</span><strong><?= (int) $resumo['animais_ativos'] ?></strong></article>
    </section>

    <nav class="abas" aria-label="Cadastros">
        <button class="ativo" data-alvo="producao">Produção de leite</button>
        <button data-alvo="racao">Estoque de ração</button>
        <button data-alvo="venda">Venda de leite</button>
        <button data-alvo="animal">Cadastro de animais</button>
    </nav>

    <section class="formularios">
        <form id="producao" method="post" class="formulario ativo">
            <input type="hidden" name="acao" value="producao">
            <h2>Registrar produção</h2>
            <label>Data<input type="date" name="data" value="<?= $hoje ?>" required></label>
            <label>Litros produzidos<input type="number" name="litros" min="0.1" step="0.1" placeholder="Ex.: 125,5" required></label>
            <label class="largo">Observação<input name="observacao" maxlength="120" placeholder="Ex.: ordenha da manhã e da tarde"></label>
            <button type="submit">Salvar produção</button>
        </form>

        <form id="racao" method="post" class="formulario">
            <input type="hidden" name="acao" value="racao">
            <h2>Movimentar ração</h2>
            <label>Data<input type="date" name="data" value="<?= $hoje ?>" required></label>
            <label>Movimentação<select name="tipo"><option value="entrada">Entrada</option><option value="saida">Saída</option></select></label>
            <label>Quantidade em kg<input type="number" name="quantidade" min="0.1" step="0.1" required></label>
            <label>Descrição<input name="descricao" maxlength="120" placeholder="Compra ou consumo diário"></label>
            <button type="submit">Salvar movimentação</button>
        </form>

        <form id="venda" method="post" class="formulario">
            <input type="hidden" name="acao" value="venda">
            <h2>Registrar venda</h2>
            <label>Data<input type="date" name="data" value="<?= $hoje ?>" required></label>
            <label>Litros vendidos<input type="number" name="litros" min="0.1" step="0.1" required></label>
            <label>Valor por litro<input type="number" name="valor_litro" min="0" step="0.01" placeholder="R$ 0,00" required></label>
            <label>Comprador<input name="comprador" maxlength="120" placeholder="Nome do comprador ou laticínio"></label>
            <button type="submit">Salvar venda</button>
        </form>

        <form id="animal" method="post" class="formulario">
            <input type="hidden" name="acao" value="animal">
            <h2>Cadastrar animal</h2>
            <label>Número do brinco<input name="brinco" maxlength="30" placeholder="Ex.: VT-024" required></label>
            <label>Nome<input name="nome" maxlength="80" placeholder="Nome opcional"></label>
            <label>Raça<input name="raca" maxlength="80" placeholder="Ex.: Girolando" required></label>
            <label>Data de nascimento<input type="date" name="nascimento"></label>
            <label>Status<select name="status"><option value="ativo">Ativo</option><option value="vendido">Vendido</option><option value="inativo">Inativo</option></select></label>
            <button type="submit">Salvar animal</button>
        </form>
    </section>

    <section class="historicos">
        <article>
            <h2>Produções recentes</h2>
            <div class="tabela"><table><thead><tr><th>Data</th><th>Litros</th><th>Observação</th></tr></thead><tbody>
            <?php foreach ($producoes as $item): ?><tr><td><?= e($item['data_producao']) ?></td><td><?= numero((float)$item['litros']) ?> L</td><td><?= e($item['observacao'] ?: '—') ?></td></tr><?php endforeach; ?>
            <?php if (!$producoes): ?><tr><td colspan="3">Nenhuma produção registrada.</td></tr><?php endif; ?>
            </tbody></table></div>
        </article>

        <article>
            <h2>Ração: últimas movimentações</h2>
            <div class="tabela"><table><thead><tr><th>Data</th><th>Tipo</th><th>Quantidade</th></tr></thead><tbody>
            <?php foreach ($movimentos as $item): ?><tr><td><?= e($item['data_movimento']) ?></td><td><span class="tipo <?= e($item['tipo']) ?>"><?= e(ucfirst($item['tipo'])) ?></span></td><td><?= numero((float)$item['quantidade_kg']) ?> kg</td></tr><?php endforeach; ?>
            <?php if (!$movimentos): ?><tr><td colspan="3">Nenhuma movimentação registrada.</td></tr><?php endif; ?>
            </tbody></table></div>
        </article>

        <article>
            <h2>Vendas recentes</h2>
            <div class="tabela"><table><thead><tr><th>Data</th><th>Litros</th><th>Valor</th><th>Total</th></tr></thead><tbody>
            <?php foreach ($vendas as $item): ?><tr><td><?= e($item['data_venda']) ?></td><td><?= numero((float)$item['litros']) ?> L</td><td>R$ <?= numero((float)$item['valor_litro'], 2) ?></td><td>R$ <?= numero((float)$item['litros'] * (float)$item['valor_litro'], 2) ?></td></tr><?php endforeach; ?>
            <?php if (!$vendas): ?><tr><td colspan="4">Nenhuma venda registrada.</td></tr><?php endif; ?>
            </tbody></table></div>
        </article>

        <article>
            <h2>Rebanho cadastrado</h2>
            <div class="tabela"><table><thead><tr><th>Brinco</th><th>Nome</th><th>Raça</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($animais as $item): ?><tr><td><?= e($item['brinco']) ?></td><td><?= e($item['nome'] ?: '—') ?></td><td><?= e($item['raca']) ?></td><td><span class="tipo status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td></tr><?php endforeach; ?>
            <?php if (!$animais): ?><tr><td colspan="4">Nenhum animal cadastrado.</td></tr><?php endif; ?>
            </tbody></table></div>
        </article>
    </section>
</main>

<footer>Projeto acadêmico de Pedro H. Santo • Sistemas de Informação</footer>
<script src="app.js"></script>
</body>
</html>
