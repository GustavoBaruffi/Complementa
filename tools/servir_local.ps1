param(
    [string]$DbHost = '127.0.0.1',
    [string]$DbPort = '3306',
    [string]$DbName = 'complementa',
    [string]$DbUser = 'root',
    [int]$Porta = 8000,
    [switch]$Verificar,
    [switch]$CriarOrganizador
)

$ErrorActionPreference = 'Stop'
$projeto = Split-Path -Parent $PSScriptRoot
$nomes = @('DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD')
$anteriores = @{}
foreach ($nome in $nomes) {
    $anteriores[$nome] = [Environment]::GetEnvironmentVariable($nome, 'Process')
}
$senhaSegura = Read-Host 'Senha do MySQL (Enter se não houver)' -AsSecureString
$ponteiro = [IntPtr]::Zero
try {
    $ponteiro = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($senhaSegura)
    $env:DB_HOST = $DbHost
    $env:DB_PORT = $DbPort
    $env:DB_NAME = $DbName
    $env:DB_USER = $DbUser
    $env:DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ponteiro)
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ponteiro)
    $ponteiro = [IntPtr]::Zero
    $phpArgs = @()
    $modulos = & php -m
    if ($LASTEXITCODE -ne 0) { throw 'Não foi possível executar o PHP.' }
    if ($modulos -notcontains 'pdo_mysql') {
        $phpArgs += @('-d', 'extension=pdo_mysql')
    }
    if ($Verificar) {
        & php @phpArgs (Join-Path $projeto 'tools/verificar_ambiente.php')
    } elseif ($CriarOrganizador) {
        & php @phpArgs (Join-Path $projeto 'tools/criar_organizador.php')
    } else {
        Write-Host "Complementa em http://localhost:$Porta (Ctrl+C para parar)."
        & php @phpArgs -S "127.0.0.1:$Porta" -t $projeto
    }
    if ($LASTEXITCODE -ne 0) { throw 'O comando PHP terminou com erro.' }
} finally {
    if ($ponteiro -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ponteiro)
    }
    $senhaSegura.Dispose()
    foreach ($nome in $nomes) {
        [Environment]::SetEnvironmentVariable($nome, $anteriores[$nome], 'Process')
    }
}
