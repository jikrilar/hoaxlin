<#
.SYNOPSIS
Bootstraps the Hoaxlin local Docker environment without host language runtimes.

.DESCRIPTION
Creates an absent env file from .env.docker.example, generates only blank local
secrets, builds/starts services, waits for health, and runs safe migrations.
Any critical failure returns a non-zero exit code. Existing values are preserved.

.EXAMPLE
.\scripts\docker-setup.ps1
#>
[CmdletBinding()]
param(
    [string] $EnvFile = '.env',
    [string] $ProjectName = '',
    [ValidateRange(30, 1800)]
    [int] $HealthTimeoutSeconds = 300,
    [switch] $ValidateOnly,
    [switch] $RebuildApp
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$script:ProjectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$script:TemplatePath = Join-Path $script:ProjectRoot '.env.docker.example'
$script:EnvPath = if ([System.IO.Path]::IsPathRooted($EnvFile)) {
    [System.IO.Path]::GetFullPath($EnvFile)
} else {
    [System.IO.Path]::GetFullPath((Join-Path $script:ProjectRoot $EnvFile))
}
$script:Utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$script:ComposeArguments = @('compose', '--env-file', $script:EnvPath)
if ($ProjectName) {
    if ($ProjectName -notmatch '^[a-z0-9][a-z0-9_-]*$') {
        throw 'ProjectName must use lowercase letters, digits, hyphens, or underscores.'
    }
    $script:ComposeArguments += @('-p', $ProjectName)
}

function Write-Step([string] $Status, [string] $Message) {
    Write-Host ('[{0}] {1}' -f $Status, $Message)
}

function Invoke-Docker {
    param(
        [Parameter(Mandatory)] [string[]] $Arguments,
        [switch] $Quiet
    )

    if ($Quiet) {
        & docker @Arguments *> $null
    } else {
        & docker @Arguments
    }
    if ($LASTEXITCODE -ne 0) {
        throw ('docker {0} failed with exit code {1}.' -f ($Arguments -join ' '), $LASTEXITCODE)
    }
}

function Invoke-Compose {
    param(
        [Parameter(Mandatory)] [string[]] $Arguments,
        [switch] $Quiet
    )

    Invoke-Docker -Arguments ($script:ComposeArguments + $Arguments) -Quiet:$Quiet
}

function Get-EnvMatches([string] $Name) {
    $escaped = [regex]::Escape($Name)
    return @(Get-Content -LiteralPath $script:EnvPath | Where-Object { $_ -match "^$escaped=" })
}

function Get-EnvValue([string] $Name) {
    $matches = @(Get-EnvMatches $Name)
    if ($matches.Count -gt 1) {
        throw "Environment key $Name occurs more than once in $($script:EnvPath)."
    }
    if ($matches.Count -eq 0) {
        return $null
    }

    $value = $matches[0].Substring($Name.Length + 1).Trim()
    if ($value.Length -ge 2 -and (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'")))) {
        return $value.Substring(1, $value.Length - 2)
    }
    return $value
}

function Set-EnvValue([string] $Name, [string] $Value) {
    $lines = [System.IO.File]::ReadAllLines($script:EnvPath)
    $escaped = [regex]::Escape($Name)
    $indexes = @()
    for ($index = 0; $index -lt $lines.Count; $index++) {
        if ($lines[$index] -match "^$escaped=") {
            $indexes += $index
        }
    }
    if ($indexes.Count -gt 1) {
        throw "Environment key $Name occurs more than once in $($script:EnvPath)."
    }
    if ($indexes.Count -eq 1) {
        $lines[$indexes[0]] = "$Name=$Value"
    } else {
        $lines += "$Name=$Value"
    }
    [System.IO.File]::WriteAllLines($script:EnvPath, $lines, $script:Utf8NoBom)
}

function New-RandomBytes([int] $Length) {
    $bytes = New-Object byte[] $Length
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
    } finally {
        $generator.Dispose()
    }
    return $bytes
}

function New-HexSecret([int] $ByteLength = 32) {
    return -join ((New-RandomBytes $ByteLength) | ForEach-Object { $_.ToString('x2') })
}

function Initialize-Secret([string] $Name, [scriptblock] $Generator) {
    $current = Get-EnvValue $Name
    if ([string]::IsNullOrWhiteSpace($current)) {
        Set-EnvValue $Name (& $Generator)
        Write-Step 'PASS' "$Name generated"
    } else {
        Write-Step 'PASS' "$Name preserved"
    }
}

function Assert-EnvValue([string] $Name, [string] $Expected = '') {
    $value = Get-EnvValue $Name
    if ([string]::IsNullOrWhiteSpace($value)) {
        throw "$Name is required in $($script:EnvPath)."
    }
    if ($Expected -and $value -cne $Expected) {
        throw "$Name must be '$Expected' for the Docker environment (found a different value)."
    }
}

function Get-ServiceState([string] $Service) {
    $containerOutput = @(& docker @script:ComposeArguments ps -q $Service 2>$null)
    $composeExitCode = $LASTEXITCODE
    $containerId = $containerOutput | Select-Object -First 1
    if ($composeExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($containerId)) {
        return 'missing'
    }
    $stateOutput = @(& docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' $containerId 2>$null)
    $inspectExitCode = $LASTEXITCODE
    $state = $stateOutput | Select-Object -First 1
    if ($inspectExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($state)) {
        return 'unknown'
    }
    return $state.Trim()
}

function Wait-Service([string] $Service, [string[]] $AcceptedStates) {
    $deadline = [DateTime]::UtcNow.AddSeconds($HealthTimeoutSeconds)
    do {
        $state = Get-ServiceState $Service
        if ($AcceptedStates -contains $state) {
            Write-Step 'PASS' "$Service $state"
            return
        }
        if ($state -in @('exited', 'dead')) {
            break
        }
        Start-Sleep -Seconds 2
    } while ([DateTime]::UtcNow -lt $deadline)

    Write-Step 'FAIL' "$Service did not reach $($AcceptedStates -join '/') (last state: $state)"
    & docker @script:ComposeArguments logs --tail 40 $Service
    throw "Timed out waiting for $Service."
}

try {
    Set-Location -LiteralPath $script:ProjectRoot
    Write-Host 'Hoaxlin Docker Setup'
    Write-Host ''

    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw 'Docker CLI is not installed or is not available on PATH.'
    }
    Invoke-Docker -Arguments @('version', '--format', '{{.Server.Version}}') -Quiet
    Write-Step 'PASS' 'Docker daemon reachable'

    Invoke-Docker -Arguments @('compose', 'version') -Quiet
    Write-Step 'PASS' 'Docker Compose available'

    if (-not (Test-Path -LiteralPath $script:TemplatePath -PathType Leaf)) {
        throw "Missing environment template: $($script:TemplatePath)"
    }
    if (-not (Test-Path -LiteralPath $script:EnvPath -PathType Leaf)) {
        Copy-Item -LiteralPath $script:TemplatePath -Destination $script:EnvPath
        Write-Step 'PASS' "Environment created from .env.docker.example"
    } else {
        Write-Step 'PASS' 'Existing environment file preserved'
    }

    Initialize-Secret 'APP_KEY' { 'base64:' + [Convert]::ToBase64String((New-RandomBytes 32)) }
    Initialize-Secret 'DB_PASSWORD' { New-HexSecret }
    Initialize-Secret 'MYSQL_ROOT_PASSWORD' { New-HexSecret }
    Initialize-Secret 'BERT_SERVICE_TOKEN' { New-HexSecret }

    $requiredValues = [ordered]@{
        APP_ENV                  = 'local'
        DB_CONNECTION            = 'mysql'
        DB_HOST                  = 'mysql'
        DB_PORT                  = '3306'
        REDIS_CLIENT             = 'phpredis'
        REDIS_HOST               = 'redis'
        REDIS_PORT               = '6379'
        QUEUE_CONNECTION         = 'redis'
        CACHE_STORE              = 'redis'
        SESSION_DRIVER           = 'redis'
        BERT_SERVICE_URL         = 'http://bert:8001'
        BERT_MODEL_VERSION       = 'v1.0.0'
        BERT_CONFIDENCE_THRESHOLD = '0.99'
    }
    foreach ($entry in $requiredValues.GetEnumerator()) {
        Assert-EnvValue $entry.Key $entry.Value
    }
    foreach ($name in @('APP_KEY', 'DB_DATABASE', 'MYSQL_APP_USER', 'DB_USERNAME', 'DB_PASSWORD', 'MYSQL_ROOT_PASSWORD', 'BERT_SERVICE_TOKEN')) {
        Assert-EnvValue $name
    }
    foreach ($name in @('APP_URL', 'APP_HOST_PORT')) {
        Assert-EnvValue $name
    }
    $appKey = Get-EnvValue 'APP_KEY'
    try {
        $appKeyBytes = if ($appKey.StartsWith('base64:')) { [Convert]::FromBase64String($appKey.Substring(7)) } else { @() }
    } catch {
        $appKeyBytes = @()
    }
    if ($appKeyBytes.Count -ne 32) {
        throw 'APP_KEY must use Laravel base64 format with exactly 32 random bytes.'
    }
    if ((Get-EnvValue 'APP_HOST_PORT') -notmatch '^\d{1,5}$' -or [int](Get-EnvValue 'APP_HOST_PORT') -lt 1 -or [int](Get-EnvValue 'APP_HOST_PORT') -gt 65535) {
        throw 'APP_HOST_PORT must be a valid TCP port.'
    }
    if ((Get-EnvValue 'MYSQL_APP_USER') -eq 'root' -or (Get-EnvValue 'DB_USERNAME') -eq 'root') {
        throw 'The Docker application database user must not be root.'
    }
    if ((Get-EnvValue 'MYSQL_APP_USER') -cne (Get-EnvValue 'DB_USERNAME')) {
        throw 'MYSQL_APP_USER and DB_USERNAME must match in the Docker environment.'
    }
    Write-Step 'PASS' 'Docker environment contract valid'

    if ([string]::IsNullOrWhiteSpace((Get-EnvValue 'OPENAI_API_KEY'))) {
        Write-Step 'WARN' 'OpenAI is not configured; OCR, transcription, translation, and explanation will not work.'
    } else {
        Write-Step 'PASS' 'OpenAI configured'
    }

    Invoke-Compose -Arguments @('config', '--quiet') -Quiet
    Write-Step 'PASS' 'Compose configuration valid'

    if ($ValidateOnly) {
        Write-Step 'PASS' 'Validation-only run complete'
        exit 0
    }

    & docker image inspect 'hoaxlin-bert:v1.0.0' *> $null
    if ($LASTEXITCODE -eq 0) {
        Write-Step 'PASS' 'BERT image v1.0.0 available'
    } else {
        $artifactPath = Join-Path $script:ProjectRoot 'artifacts\indobert-hoax\v1.0.0'
        $criticalFiles = @('manifest.json', 'model.safetensors', 'config.json', 'tokenizer.json', 'tokenizer_config.json', 'threshold.json', 'calibration.json')
        $missingFiles = @($criticalFiles | Where-Object { -not (Test-Path -LiteralPath (Join-Path $artifactPath $_) -PathType Leaf) })
        if ($missingFiles.Count -gt 0) {
            throw 'BERT image hoaxlin-bert:v1.0.0 is absent and the packaged artifact is incomplete. Load the approved private image with docker load, or provide artifacts/indobert-hoax/v1.0.0.'
        }
        Invoke-Docker -Arguments @('build', '-f', 'bert-service/Dockerfile', '--build-arg', 'MODEL_RELEASE_PATH=artifacts/indobert-hoax/v1.0.0', '--build-arg', 'MODEL_VERSION=v1.0.0', '-t', 'hoaxlin-bert:v1.0.0', '.')
        Write-Step 'PASS' 'BERT image v1.0.0 built from local packaged artifact'
    }

    & docker image inspect 'hoaxlin-app:local' *> $null
    if ($LASTEXITCODE -ne 0 -or $RebuildApp) {
        Invoke-Compose -Arguments @('build', 'app')
        Write-Step 'PASS' 'Laravel image built'
    } else {
        Write-Step 'PASS' 'Laravel image available'
    }

    Invoke-Compose -Arguments @('up', '-d', 'mysql', 'redis', 'bert', 'app')
    foreach ($service in @('mysql', 'redis', 'bert', 'app')) {
        Wait-Service $service @('healthy')
    }

    Invoke-Compose -Arguments @('exec', '-T', 'app', 'php', 'artisan', 'migrate', '--force')
    Write-Step 'PASS' 'Database migration complete'

    Invoke-Compose -Arguments @('up', '-d', 'queue', 'scheduler')
    foreach ($service in @('queue', 'scheduler')) {
        Wait-Service $service @('healthy', 'running')
    }

    Write-Host ''
    Invoke-Compose -Arguments @('ps')
    Write-Host ''
    Write-Host 'Application:'
    Write-Host (Get-EnvValue 'APP_URL')
    Write-Host ''
    Write-Step 'PASS' 'Hoaxlin Docker environment is ready'
    exit 0
} catch {
    Write-Step 'FAIL' $_.Exception.Message
    exit 1
}
