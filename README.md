# Gestão de Retiro Leiteiro

Sistema web para registrar a rotina de um pequeno retiro leiteiro: produção diária,
estoque de ração e vendas de leite.

## Funcionalidades

- Registro da produção de leite por data
- Controle de entrada e saída de ração em quilos
- Registro de vendas com quantidade e valor por litro
- Cadastro de animais com brinco único, raça, nascimento e status
- Cálculo automático do leite disponível, estoque de ração e faturamento
- Histórico das movimentações recentes
- Validação para impedir vendas ou saídas maiores que o estoque
- Interface responsiva para computador e celular

## Tecnologias

- PHP 8 com PDO
- SQLite e SQL
- HTML5
- CSS3
- JavaScript

## Como executar

É necessário ter PHP 8 ou superior com a extensão `pdo_sqlite`.

```bash
php -S localhost:8000 -t public
```

Acesse `http://localhost:8000`. O banco será criado automaticamente em
`data/retiro.sqlite` na primeira execução.

No ambiente local preparado pelo Codex no Windows, também é possível iniciar com:

```powershell
powershell -ExecutionPolicy Bypass -File .\iniciar.ps1
```

## Estrutura

```text
public/
  index.php       # Dashboard e formulários
  style.css       # Interface
  app.js          # Interações no navegador
src/
  database.php    # Conexão, tabelas e consultas
data/             # Banco local ignorado pelo Git
```

## Aprendizados praticados

O projeto demonstra CRUD, banco relacional, consultas agregadas, transações,
validação no servidor, formulários web e organização de uma aplicação PHP.
