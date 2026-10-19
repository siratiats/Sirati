# Writes ANTHROPIC_API_KEY into D:\Sirati\.env without echoing it or putting it in shell history.
# Usage (from the project root):  powershell -ExecutionPolicy Bypass -File scripts\set-anthropic-key.ps1
$envFile = Join-Path (Split-Path $PSScriptRoot -Parent) '.env'
if (-not (Test-Path $envFile)) { Write-Error ".env not found at $envFile"; exit 1 }

$secure = Read-Host -Prompt 'Paste your Anthropic API key (input is hidden)' -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try { $key = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) }
finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }

if ($key -notmatch '^sk-ant-[A-Za-z0-9_\-]{20,}$') { Write-Error 'That does not look like an Anthropic key (expected sk-ant-...). Nothing written.'; exit 1 }

$lines = Get-Content $envFile
if ($lines -match '^ANTHROPIC_API_KEY=') { $lines = $lines -replace '^ANTHROPIC_API_KEY=.*', "ANTHROPIC_API_KEY=$key" }
else { $lines += "ANTHROPIC_API_KEY=$key" }
Set-Content -Path $envFile -Value $lines -Encoding utf8
$key = $null
Write-Host 'Saved to .env (git-ignored). Restart php artisan serve / queue workers to pick it up.'
