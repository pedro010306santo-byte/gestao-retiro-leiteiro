$ErrorActionPreference = 'Stop'
$phpGlobal = Get-Command php -ErrorAction SilentlyContinue
$phpLocal = Join-Path $PSScriptRoot '..\..\tools\php-8.5.8\php.exe'

if ($phpGlobal) {
    $php = $phpGlobal.Source
    & $php -S localhost:8000 -t (Join-Path $PSScriptRoot 'public')
} elseif (Test-Path $phpLocal) {
    $ext = Join-Path (Split-Path $phpLocal) 'ext'
    & $phpLocal -n -d "extension_dir=$ext" -d extension=pdo_sqlite `
        -S localhost:8000 -t (Join-Path $PSScriptRoot 'public')
} else {
    Write-Error 'PHP não foi encontrado. Instale o PHP 8 ou ajuste o caminho no script.'
}
