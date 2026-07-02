<?php
declare(strict_types=1);

function banco(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $diretorio = dirname(__DIR__) . '/data';
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $diretorio . '/retiro.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    criarTabelas($pdo);

    return $pdo;
}

function criarTabelas(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS producoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            data_producao TEXT NOT NULL,
            litros REAL NOT NULL CHECK (litros > 0),
            observacao TEXT,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS racao_movimentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            data_movimento TEXT NOT NULL,
            tipo TEXT NOT NULL CHECK (tipo IN ("entrada", "saida")),
            quantidade_kg REAL NOT NULL CHECK (quantidade_kg > 0),
            descricao TEXT,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS vendas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            data_venda TEXT NOT NULL,
            litros REAL NOT NULL CHECK (litros > 0),
            valor_litro REAL NOT NULL CHECK (valor_litro >= 0),
            comprador TEXT,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS animais (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            brinco TEXT NOT NULL UNIQUE,
            nome TEXT,
            raca TEXT NOT NULL,
            nascimento TEXT,
            status TEXT NOT NULL DEFAULT "ativo" CHECK (status IN ("ativo", "vendido", "inativo")),
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );'
    );
}

function numeroPost(string $campo): float
{
    $valor = str_replace(',', '.', trim($_POST[$campo] ?? ''));
    if (!is_numeric($valor) || (float) $valor <= 0) {
        throw new InvalidArgumentException('Informe um valor maior que zero.');
    }
    return round((float) $valor, 2);
}

function textoPost(string $campo, int $limite = 120): string
{
    $texto = trim($_POST[$campo] ?? '');
    return function_exists('mb_substr')
        ? mb_substr($texto, 0, $limite)
        : substr($texto, 0, $limite);
}

function dataPost(string $campo): string
{
    $data = trim($_POST[$campo] ?? '');
    $objeto = DateTimeImmutable::createFromFormat('Y-m-d', $data);
    if (!$objeto || $objeto->format('Y-m-d') !== $data) {
        throw new InvalidArgumentException('Informe uma data válida.');
    }
    return $data;
}

function total(PDO $pdo, string $sql): float
{
    return (float) $pdo->query($sql)->fetchColumn();
}

function resumo(PDO $pdo): array
{
    $produzido = total($pdo, 'SELECT COALESCE(SUM(litros), 0) FROM producoes');
    $vendido = total($pdo, 'SELECT COALESCE(SUM(litros), 0) FROM vendas');
    $entradas = total($pdo, "SELECT COALESCE(SUM(quantidade_kg), 0) FROM racao_movimentos WHERE tipo = 'entrada'");
    $saidas = total($pdo, "SELECT COALESCE(SUM(quantidade_kg), 0) FROM racao_movimentos WHERE tipo = 'saida'");

    return [
        'producao_hoje' => total($pdo, "SELECT COALESCE(SUM(litros), 0) FROM producoes WHERE data_producao = date('now', 'localtime')"),
        'leite_disponivel' => $produzido - $vendido,
        'racao_disponivel' => $entradas - $saidas,
        'faturamento' => total($pdo, 'SELECT COALESCE(SUM(litros * valor_litro), 0) FROM vendas'),
        'animais_ativos' => total($pdo, "SELECT COUNT(*) FROM animais WHERE status = 'ativo'"),
    ];
}

function salvarRegistro(PDO $pdo, string $acao): string
{
    if ($acao === 'producao') {
        $stmt = $pdo->prepare('INSERT INTO producoes (data_producao, litros, observacao) VALUES (?, ?, ?)');
        $stmt->execute([dataPost('data'), numeroPost('litros'), textoPost('observacao')]);
        return 'Produção de leite registrada.';
    }

    if ($acao === 'racao') {
        $tipo = $_POST['tipo'] ?? '';
        if (!in_array($tipo, ['entrada', 'saida'], true)) {
            throw new InvalidArgumentException('Tipo de movimentação inválido.');
        }
        $quantidade = numeroPost('quantidade');
        if ($tipo === 'saida' && $quantidade > resumo($pdo)['racao_disponivel']) {
            throw new InvalidArgumentException('A saída é maior que o estoque de ração.');
        }
        $stmt = $pdo->prepare('INSERT INTO racao_movimentos (data_movimento, tipo, quantidade_kg, descricao) VALUES (?, ?, ?, ?)');
        $stmt->execute([dataPost('data'), $tipo, $quantidade, textoPost('descricao')]);
        return 'Movimentação de ração registrada.';
    }

    if ($acao === 'venda') {
        $litros = numeroPost('litros');
        if ($litros > resumo($pdo)['leite_disponivel']) {
            throw new InvalidArgumentException('A venda é maior que o estoque de leite.');
        }
        $valor = str_replace(',', '.', trim($_POST['valor_litro'] ?? ''));
        if (!is_numeric($valor) || (float) $valor < 0) {
            throw new InvalidArgumentException('Informe um valor por litro válido.');
        }
        $stmt = $pdo->prepare('INSERT INTO vendas (data_venda, litros, valor_litro, comprador) VALUES (?, ?, ?, ?)');
        $stmt->execute([dataPost('data'), $litros, round((float) $valor, 2), textoPost('comprador')]);
        return 'Venda de leite registrada.';
    }

    if ($acao === 'animal') {
        $brinco = textoPost('brinco', 30);
        $raca = textoPost('raca', 80);
        $status = $_POST['status'] ?? '';
        if ($brinco === '' || $raca === '' || !in_array($status, ['ativo', 'vendido', 'inativo'], true)) {
            throw new InvalidArgumentException('Informe brinco, raça e status válidos.');
        }
        $nascimento = trim($_POST['nascimento'] ?? '');
        if ($nascimento !== '') {
            $_POST['nascimento_validacao'] = $nascimento;
            $nascimento = dataPost('nascimento_validacao');
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO animais (brinco, nome, raca, nascimento, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$brinco, textoPost('nome', 80), $raca, $nascimento ?: null, $status]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                throw new InvalidArgumentException('Já existe um animal com esse número de brinco.');
            }
            throw $e;
        }
        return 'Animal cadastrado com sucesso.';
    }

    throw new InvalidArgumentException('Ação inválida.');
}
