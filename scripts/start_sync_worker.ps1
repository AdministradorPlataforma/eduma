<#
.SYNOPSIS
  Inicia el worker de cola de EDUMA de forma automática.

.DESCRIPTION
  Comprueba si ya hay un worker ejecutándose y, si no, lanza el script
  `scripts/sync_worker.php` en una nueva ventana de PowerShell.

.PARAMETER Daemon
  Inicia el worker en modo daemon.

.PARAMETER Sleep
  Ajusta el intervalo de sondeo de la cola (segundos).

.PARAMETER Force
  Fuerza el inicio aunque ya exista un worker en ejecución.
#>

param(
    [switch]$Daemon,
    [int]$Sleep = 5,
    [switch]$Force
)

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$workerScript = Join-Path $projectRoot 'sync_worker.php'
$phpCmd = 'php'

function Get-WorkerProcess {
    try {
        Get-CimInstance Win32_Process -ErrorAction Stop | Where-Object {
            $_.CommandLine -and $_.CommandLine -match [regex]::Escape($workerScript)
        }
    } catch {
        @()
    }
}

$existing = Get-WorkerProcess
if ($existing -and -not $Force) {
    Write-Host "Ya existe un worker en ejecución con PID(s): $($existing.ProcessId -join ', ')" -ForegroundColor Yellow
    Write-Host "Use -Force para iniciar otra instancia." -ForegroundColor DarkYellow
    return
}

$arguments = @('scripts/sync_worker.php')
if ($Daemon) { $arguments += '--daemon' }
if ($Sleep -gt 0) { $arguments += "--sleep=$Sleep" }

$psi = New-Object System.Diagnostics.ProcessStartInfo
$psi.FileName = $phpCmd
$psi.Arguments = $arguments -join ' '
$psi.WorkingDirectory = $projectRoot
$psi.UseShellExecute = $true
$psi.CreateNoWindow = $false
$psi.WindowStyle = [System.Diagnostics.ProcessWindowStyle]::Normal

Start-Process @psi
Write-Host "Worker iniciado: $($arguments -join ' ')" -ForegroundColor Green
Write-Host "Directorio: $projectRoot" -ForegroundColor Gray
