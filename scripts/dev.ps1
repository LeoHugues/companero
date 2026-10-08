# Environnement de développement local de Companero, sous Windows et sans droits administrateur.
# Les outils (PHP, Composer, Java, SDK Android) sont téléchargés dans .tools\ :
# supprimer ce dossier suffit à tout désinstaller.
#
#   .\dev setup          installe PHP et Composer, les dépendances, la base de démo et le CSS
#   .\dev serve [port]   lance l'appli sur le PC et le Wi-Fi (port 8000 par défaut)
#   .\dev test           lance les tests PHPUnit
#   .\dev apk [adresse]  compile l'appli Android dans dist\companero.apk
#                        (adresse du serveur par défaut : http://<IP du PC>:8000)
#   .\dev console ...    bin/console, par ex. .\dev console app:week:close
#   .\dev composer ...   Composer, par ex. .\dev composer require --dev phpstan/phpstan
#   .\dev deploy <ssh> <dossier> [version]
#                        met à jour la production par SSH, par ex. .\dev deploy moi@serveur /srv/companero
#   .\dev publish-apk <ssh> <dossier>
#                        envoie dist\companero.apk sur le serveur (à compiler avec l'adresse publique)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$Root = Split-Path -Parent $PSScriptRoot
$Tools = Join-Path $Root '.tools'
$PhpDir = Join-Path $Tools 'php'
$Php = Join-Path $PhpDir 'php.exe'
$ComposerPhar = Join-Path $Tools 'composer.phar'
$JdkDir = Join-Path $Tools 'jdk'
$SdkDir = Join-Path $Tools 'android-sdk'
$SdkManager = Join-Path $SdkDir 'cmdline-tools\latest\bin\sdkmanager.bat'
$Tar = Join-Path $env:SystemRoot 'System32\tar.exe'
$Curl = Join-Path $env:SystemRoot 'System32\curl.exe'

$PhpBranch = '8.4'
$JdkVersion = '17'
$AndroidPackages = @('platform-tools', 'platforms;android-36', 'build-tools;35.0.0')

function Write-Step([string] $Message) {
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Invoke-Native {
    # [string[]] strips PowerShell's parameter metadata so "--version" reaches the program unchanged.
    $exe = $args[0]
    [string[]] $arguments = @($args | Select-Object -Skip 1)
    & $exe @arguments
    if ($LASTEXITCODE -ne 0) { throw "Échec ($LASTEXITCODE) : $exe $arguments" }
}

function Use-Tools {
    $env:PATH = "$PhpDir;$JdkDir\bin;$SdkDir\platform-tools;$env:PATH"
    $env:COMPOSER_HOME = Join-Path $Tools 'composer-home'
    $env:JAVA_HOME = $JdkDir
    $env:ANDROID_HOME = $SdkDir
    $env:GRADLE_USER_HOME = Join-Path $Tools 'gradle-home'
}

function Save-Download([string] $Url, [string] $OutFile) {
    Write-Step "Téléchargement de $Url"
    Invoke-Native $Curl -fL --retry 3 --progress-bar -o $OutFile $Url
}

# Décompresse une archive qui contient un seul dossier racine et le renomme en $Destination.
function Expand-SingleFolder([string] $Archive, [string] $Destination) {
    $staging = "$Destination.tmp"
    if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }
    New-Item -ItemType Directory $staging | Out-Null
    Invoke-Native $Tar -xf $Archive -C $staging
    $inner = @(Get-ChildItem $staging)
    if ($inner.Count -ne 1) { throw "Contenu inattendu dans $Archive" }
    New-Item -ItemType Directory -Force (Split-Path -Parent $Destination) | Out-Null
    Move-Item $inner[0].FullName $Destination
    Remove-Item $staging, $Archive -Recurse -Force
}

function Get-LanIp {
    $route = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -ErrorAction SilentlyContinue |
        Sort-Object { $_.RouteMetric + $_.InterfaceMetric } | Select-Object -First 1
    $candidates = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notmatch '^(127|169\.254)\.' }
    $ip = $candidates | Where-Object { $route -and $_.InterfaceIndex -eq $route.ifIndex } | Select-Object -First 1
    if (-not $ip) { $ip = $candidates | Select-Object -First 1 }
    if ($ip) { $ip.IPAddress } else { 'localhost' }
}

# --- Outils -------------------------------------------------------------------

function Install-Php {
    if (Test-Path $Php) { return }
    $releases = Invoke-RestMethod 'https://windows.php.net/downloads/releases/releases.json'
    $build = $releases.$PhpBranch.PSObject.Properties | Where-Object { $_.Name -like 'nts-vs*-x64' } | Select-Object -First 1
    $zip = $build.Value.zip.path
    New-Item -ItemType Directory -Force $PhpDir | Out-Null
    Save-Download "https://windows.php.net/downloads/releases/$zip" "$Tools\php.zip"
    Invoke-Native $Tar -xf "$Tools\php.zip" -C $PhpDir
    Remove-Item "$Tools\php.zip"
    Save-Download 'https://curl.se/ca/cacert.pem' "$PhpDir\cacert.pem"

    @"
[PHP]
extension_dir = "$PhpDir\ext"
extension=curl
extension=fileinfo
extension=intl
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sodium
extension=sqlite3
extension=zip
zend_extension=opcache

memory_limit = 512M
error_reporting = E_ALL
display_errors = On
display_startup_errors = On
post_max_size = 16M
upload_max_filesize = 16M
realpath_cache_size = 4096K
realpath_cache_ttl = 600

[Date]
date.timezone = Europe/Paris

[curl]
curl.cainfo = "$PhpDir\cacert.pem"

[openssl]
openssl.cafile = "$PhpDir\cacert.pem"

[opcache]
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
"@ | Set-Content "$PhpDir\php.ini" -Encoding ASCII
    Invoke-Native $Php --version
}

function Install-Composer {
    if (Test-Path $ComposerPhar) { return }
    Save-Download 'https://getcomposer.org/download/latest-stable/composer.phar' $ComposerPhar
}

function Install-Jdk {
    if (Test-Path "$JdkDir\bin\java.exe") { return }
    New-Item -ItemType Directory -Force $Tools | Out-Null
    Save-Download "https://api.adoptium.net/v3/binary/latest/$JdkVersion/ga/windows/x64/jdk/hotspot/normal/eclipse" "$Tools\jdk.zip"
    Expand-SingleFolder "$Tools\jdk.zip" $JdkDir
}

function Install-AndroidSdk {
    if (-not (Test-Path $SdkManager)) {
        $repository = (Invoke-WebRequest 'https://dl.google.com/android/repository/repository2-3.xml' -UseBasicParsing).Content
        $zip = [regex]::Matches($repository, 'commandlinetools-win-(\d+)_latest\.zip') |
            Sort-Object { [long] $_.Groups[1].Value } | Select-Object -Last 1
        Save-Download "https://dl.google.com/android/repository/$($zip.Value)" "$Tools\cmdline-tools.zip"
        Expand-SingleFolder "$Tools\cmdline-tools.zip" (Join-Path $SdkDir 'cmdline-tools\latest')
    }
    $missing = $AndroidPackages | Where-Object { -not (Test-Path (Join-Path $SdkDir ($_ -replace ';', '\'))) }
    if ($missing) {
        Write-Step 'Acceptation des licences du SDK Android'
        ("y`n" * 30) | & $SdkManager "--sdk_root=$SdkDir" --licenses | Out-Null
        Write-Step "Installation de $($missing -join ', ')"
        Invoke-Native $SdkManager "--sdk_root=$SdkDir" @missing
    }
}

# --- Projet -------------------------------------------------------------------

function Invoke-Composer { Invoke-Native $Php $ComposerPhar --working-dir=$Root @args }
function Invoke-Console { Invoke-Native $Php "$Root\bin\console" @args }

function Assert-Setup {
    if (-not (Test-Path $Php) -or -not (Test-Path "$Root\vendor")) {
        throw 'Projet non installé : lancez d''abord .\dev setup'
    }
}

function Invoke-Setup {
    New-Item -ItemType Directory -Force $Tools | Out-Null
    Install-Php
    Install-Composer
    Use-Tools
    Write-Step 'Dépendances PHP'
    Invoke-Composer install --no-interaction
    $freshDatabase = -not (Test-Path "$Root\var\data_dev.db")
    Write-Step 'Base de données'
    Invoke-Console doctrine:migrations:migrate --no-interaction
    if ($freshDatabase) {
        Write-Step 'Coloc de démo (leo@example.com / companero)'
        Invoke-Console doctrine:fixtures:load --no-interaction
    }
    Write-Step 'CSS (Tailwind)'
    Invoke-Console tailwind:build
    Write-Host ''
    Write-Host 'Prêt. Lancez .\dev serve' -ForegroundColor Green
}

function Invoke-Serve([int] $Port) {
    Assert-Setup
    $ip = Get-LanIp
    Write-Host ''
    Write-Host "  Sur ce PC      : http://localhost:$Port" -ForegroundColor Green
    Write-Host "  Sur le Wi-Fi   : http://${ip}:$Port" -ForegroundColor Green
    if (Test-Path "$Root\dist\companero.apk") {
        Write-Host "  APK Android    : http://${ip}:$Port/companero.apk" -ForegroundColor Green
    }
    Write-Host '  Connexion démo : leo@example.com / companero'
    Write-Host '  Ctrl+C pour arrêter.'
    Write-Host ''

    # Tailwind recompile le CSS à chaque modification des templates.
    $tailwind = Start-Process -FilePath $Php -ArgumentList 'bin\console', 'tailwind:build', '--watch' `
        -WorkingDirectory $Root -NoNewWindow -PassThru
    try {
        Push-Location $Root
        & $Php -S "0.0.0.0:$Port" -t public scripts\router.php
    } finally {
        Pop-Location
        & taskkill.exe /PID $tailwind.Id /T /F 2>&1 | Out-Null
    }
}

function Invoke-Apk([string] $ServerUrl) {
    Install-Jdk
    Use-Tools
    Install-AndroidSdk
    if (-not $ServerUrl) { $ServerUrl = "http://$(Get-LanIp):8000" }
    Write-Step "Compilation de l'APK (serveur proposé : $ServerUrl)"
    Push-Location "$Root\android"
    try {
        # No daemons: nothing keeps running (and holding 2 GB of memory) once the APK is built.
        Invoke-Native .\gradlew.bat --no-daemon --console=plain assembleDebug "-PserverUrl=$ServerUrl" `
            '-Pkotlin.compiler.execution.strategy=in-process'
    } finally {
        Pop-Location
    }
    New-Item -ItemType Directory -Force "$Root\dist" | Out-Null
    Copy-Item "$Root\android\app\build\outputs\apk\debug\app-debug.apk" "$Root\dist\companero.apk" -Force
    Write-Host ''
    Write-Host "APK prêt : $Root\dist\companero.apk" -ForegroundColor Green
    Write-Host "Pendant que .\dev serve tourne, il se télécharge depuis le téléphone sur http://$(Get-LanIp):8000/companero.apk"
}

function Invoke-Deploy([string] $Target, [string] $Path, [string] $Version) {
    if (-not $Target -or -not $Path) { throw 'Usage : .\dev deploy <utilisateur@serveur> <dossier> [version]' }
    Write-Step "Déploiement sur $Target ($Path)"
    Invoke-Native ssh $Target "cd '$Path' && ./scripts/deploy.sh $Version"
}

function Invoke-PublishApk([string] $Target, [string] $Path) {
    if (-not $Target -or -not $Path) { throw 'Usage : .\dev publish-apk <utilisateur@serveur> <dossier>' }
    $apk = "$Root\dist\companero.apk"
    if (-not (Test-Path $apk)) { throw "Pas d'APK : lancez d'abord .\dev apk https://votre-domaine" }
    Write-Step "Envoi de l'APK sur $Target"
    Invoke-Native scp $apk "${Target}:$Path/public/companero.apk"
}

$command = $args[0]
$rest = @($args | Select-Object -Skip 1)
switch ($command) {
    'setup' { Invoke-Setup }
    'serve' { Invoke-Serve $(if ($rest) { [int] $rest[0] } else { 8000 }) }
    'test' { Assert-Setup; Use-Tools; Invoke-Native $Php "$Root\bin\phpunit" @rest }
    'apk' { Invoke-Apk $(if ($rest) { $rest[0] } else { '' }) }
    'console' { Assert-Setup; Use-Tools; Invoke-Console @rest }
    'composer' { Install-Php; Install-Composer; Use-Tools; Invoke-Composer @rest }
    'deploy' { Invoke-Deploy $rest[0] $rest[1] $(if ($rest.Count -gt 2) { $rest[2] } else { '' }) }
    'publish-apk' { Invoke-PublishApk $rest[0] $rest[1] }
    default {
        Get-Content $PSCommandPath -Encoding UTF8 | Select-Object -First 16 | ForEach-Object { $_ -replace '^# ?', '' }
    }
}
