# ============================================================================
#  CSDUV3 - Verificación estática del código
# ----------------------------------------------------------------------------
#  Se usa cuando no hay PHP disponible en el equipo. No sustituye a `php -l`,
#  pero detecta errores frecuentes y costosos de encontrar en producción:
#
#    * Balance de paréntesis, llaves y corchetes dentro de los bloques PHP.
#    * Archivos que no empiezan con <?php.
#    * Ausencia de declare(strict_types=1).
#    * namespace y nombre de clase que no coinciden con la ruta del archivo.
#    * `use App\...` que apuntan a archivos inexistentes.
#    * Controladores o middleware citados en app/routes.php que no existen.
#    * Vistas citadas con View::display()/View::render() que no existen.
#    * Rutas de require/include con __DIR__ o APP_PATH que no existen.
#    * Funciones auxiliares esperadas que no están definidas en helpers.php.
#
#  Uso:
#      powershell -NoProfile -ExecutionPolicy Bypass -File tools/verificar_estatico.ps1
#
#  Código de salida: 0 si todo está correcto, 1 si hay hallazgos.
# ============================================================================

$ErrorActionPreference = 'Stop'

$raiz = Split-Path -Parent $PSScriptRoot
$hallazgos = New-Object System.Collections.Generic.List[string]
$revisados = 0

function Analizar-BloquesPhp {
    param([string]$Texto)

    $n = $Texto.Length
    $estado = 'normal'
    $enPhp = $false
    $paren = 0
    $llave = 0
    $corch = 0
    $minParen = 0
    $minLlave = 0
    $minCorch = 0
    $i = 0
    $inicioLinea = $true

    while ($i -lt $n) {
        $c = $Texto[$i]
        $sig = if ($i + 1 -lt $n) { $Texto[$i + 1] } else { '' }

        if (-not $enPhp) {
            if ($c -eq '<' -and $sig -eq '?') {
                $resto = $Texto.Substring($i)

                if ($resto.StartsWith('<?php')) {
                    $i += 5
                } elseif ($resto.StartsWith('<?=')) {
                    $i += 3
                } else {
                    $i += 2
                }

                $enPhp = $true
                $inicioLinea = $true
                continue
            }

            $i++
            continue
        }

        switch ($estado) {
            'normal' {
                if ($c -eq '?' -and $sig -eq '>') { $enPhp = $false; $i += 2; continue }
                if ($c -eq '/' -and $sig -eq '/') { $estado = 'linea'; $i += 2; continue }
                if ($c -eq '#' -and $inicioLinea) { $estado = 'linea'; $i++; continue }
                if ($c -eq '/' -and $sig -eq '*') { $estado = 'bloque'; $i += 2; continue }
                if ($c -eq "'") { $estado = 'simple'; $inicioLinea = $false; $i++; continue }
                if ($c -eq '"') { $estado = 'doble'; $inicioLinea = $false; $i++; continue }

                switch ($c) {
                    '(' { $paren++ }
                    ')' { $paren--; if ($paren -lt $minParen) { $minParen = $paren } }
                    '{' { $llave++ }
                    '}' { $llave--; if ($llave -lt $minLlave) { $minLlave = $llave } }
                    '[' { $corch++ }
                    ']' { $corch--; if ($corch -lt $minCorch) { $minCorch = $corch } }
                }

                if ($c -eq "`n") {
                    $inicioLinea = $true
                } elseif ($c -ne ' ' -and $c -ne "`t" -and $c -ne "`r") {
                    $inicioLinea = $false
                }

                $i++
            }
            'linea' {
                if ($c -eq "`n") { $estado = 'normal'; $inicioLinea = $true }
                $i++
            }
            'bloque' {
                if ($c -eq '*' -and $sig -eq '/') { $estado = 'normal'; $i += 2; continue }
                $i++
            }
            'simple' {
                if ($c -eq '\') { $i += 2; continue }
                if ($c -eq "'") { $estado = 'normal' }
                $i++
            }
            'doble' {
                if ($c -eq '\') { $i += 2; continue }
                if ($c -eq '"') { $estado = 'normal' }
                $i++
            }
        }
    }

    return [pscustomobject]@{
        Paren    = $paren
        Llave    = $llave
        Corch    = $corch
        MinParen = $minParen
        MinLlave = $minLlave
        MinCorch = $minCorch
    }
}

function Ruta-DeClase {
    param([string]$Fqn)

    $relativo = $Fqn -replace '^App\\', 'app\'

    return Join-Path $raiz ($relativo + '.php')
}

# ---------------------------------------------------------------------------
#  1. Recorrido de archivos PHP
# ---------------------------------------------------------------------------

$carpetas = @(
    (Join-Path $raiz 'app'),
    (Join-Path $raiz 'config'),
    (Join-Path $raiz 'public'),
    (Join-Path $raiz 'tools')
)

$archivos = Get-ChildItem -Path $carpetas -Filter '*.php' -Recurse -File |
    Where-Object { $_.FullName -notmatch 'storage' }

foreach ($archivo in $archivos) {
    $revisados++
    $texto = [System.IO.File]::ReadAllText($archivo.FullName)
    $relativo = $archivo.FullName.Substring($raiz.Length + 1)

    if (-not $texto.StartsWith('<?php')) {
        $hallazgos.Add("$relativo : no empieza con <?php")
    }

    $bloques = Analizar-BloquesPhp -Texto $texto

    if ($bloques.Paren -ne 0 -or $bloques.MinParen -lt 0) {
        $hallazgos.Add("$relativo : parentesis desbalanceados (fin=$($bloques.Paren), min=$($bloques.MinParen))")
    }

    if ($bloques.Llave -ne 0 -or $bloques.MinLlave -lt 0) {
        $hallazgos.Add("$relativo : llaves desbalanceadas (fin=$($bloques.Llave), min=$($bloques.MinLlave))")
    }

    if ($bloques.Corch -ne 0 -or $bloques.MinCorch -lt 0) {
        $hallazgos.Add("$relativo : corchetes desbalanceados (fin=$($bloques.Corch), min=$($bloques.MinCorch))")
    }

    if ($texto -notmatch 'declare\s*\(\s*strict_types\s*=\s*1\s*\)') {
        $hallazgos.Add("$relativo : falta declare(strict_types=1)")
    }

    # namespace y clase deben coincidir con la ruta (excepto vistas y helpers).
    if ($relativo -match '^app\\Core\\helpers\.php$') {
        # helpers.php contiene funciones globales: no declara namespace ni clase.
    } elseif ($relativo -match '^app\\(Core|Models|Controllers|Middleware|Notifications)\\') {
        $esperadoNamespace = 'App\' + ($relativo -replace '^app\\', '' -replace '\\[^\\]+\.php$', '')
        $esperadoClase = [System.IO.Path]::GetFileNameWithoutExtension($archivo.Name)

        if ($texto -notmatch ('namespace\s+' + [regex]::Escape($esperadoNamespace) + '\s*;')) {
            $hallazgos.Add("$relativo : se esperaba namespace $esperadoNamespace")
        }

        if ($texto -notmatch ('(class|interface|trait)\s+' + [regex]::Escape($esperadoClase) + '\b')) {
            $hallazgos.Add("$relativo : se esperaba la clase $esperadoClase")
        }
    } elseif ($relativo -match '^app\\Views\\') {
        if ($texto -match '(?m)^namespace\s') {
            $hallazgos.Add("$relativo : las vistas no deben declarar namespace")
        }
    } else {
        if ($texto -match '(?m)^namespace\s') {
            $hallazgos.Add("$relativo : no deberia declarar namespace")
        }
    }

    # 2. Imports de la aplicación.
    foreach ($uso in [regex]::Matches($texto, '(?m)^use\s+(App\\[A-Za-z0-9_\\]+)\s*;')) {
        $fqn = $uso.Groups[1].Value
        $destino = Ruta-DeClase $fqn

        if (-not (Test-Path $destino)) {
            $hallazgos.Add("$relativo : use $fqn no existe como archivo")
        }
    }

    # 3. require/include con __DIR__ o APP_PATH (solo archivos PHP esperados).
    foreach ($coincidencia in [regex]::Matches($texto, "(__DIR__|APP_PATH)\s*\.\s*'([^']+)'")) {
        $objetivo = $coincidencia.Groups[2].Value
        $nombreArchivo = Split-Path -Leaf ($objetivo -replace '/', '\')

        # Los archivos opcionales (.env, .app_path.php) pueden no existir: no es un error.
        if ($nombreArchivo.StartsWith('.')) {
            continue
        }

        if ($objetivo -notmatch '\.php$') {
            continue
        }

        $base = if ($coincidencia.Groups[1].Value -eq '__DIR__') { $archivo.DirectoryName } else { $raiz }
        $destino = Join-Path $base ($objetivo -replace '/', '\')

        if (-not (Test-Path $destino)) {
            $hallazgos.Add("$relativo : require/include apunta a ruta inexistente: $objetivo")
        }
    }
}

# ---------------------------------------------------------------------------
#  4. Rutas: controladores y métodos citados
# ---------------------------------------------------------------------------

$rutas = [System.IO.File]::ReadAllText((Join-Path $raiz 'app\routes.php'))

# Se eliminan los comentarios de bloque para no confundir el ejemplo de formato.
$rutas = [regex]::Replace($rutas, '(?s)/\*.*?\*/', '')

$mapa = @{}

foreach ($fila in [regex]::Matches($rutas, '(?m)^use\s+(?<fqn>App\\[A-Za-z0-9_\\]+)\s*;')) {
    $fqn = $fila.Groups['fqn'].Value
    $partes = $fqn -split '\\'
    $mapa[$partes[-1]] = $fqn
}

$manejadores = [regex]::Matches($rutas, "\[\s*([A-Za-z0-9_]+)::class\s*,\s*'([A-Za-z0-9_]+)'\s*\]")
$vistos = @{}

foreach ($manejador in $manejadores) {
    $clase = $manejador.Groups[1].Value
    $metodo = $manejador.Groups[2].Value

    if ($vistos.ContainsKey("$clase::$metodo")) {
        continue
    }

    $vistos["$clase::$metodo"] = $true

    if (-not $mapa.ContainsKey($clase)) {
        $hallazgos.Add("app\routes.php : $clase no esta importado")

        continue
    }

    $archivoClase = Ruta-DeClase $mapa[$clase]

    if (-not (Test-Path $archivoClase)) {
        $hallazgos.Add("app\routes.php : $clase no existe como archivo")

        continue
    }

    $contenido = [System.IO.File]::ReadAllText($archivoClase)

    if ($contenido -notmatch ('function\s+' + [regex]::Escape($metodo) + '\s*\(')) {
        $hallazgos.Add("app\routes.php : $clase::$metodo no esta definido en el archivo")
    }
}

# ---------------------------------------------------------------------------
#  5. Vistas citadas en los controladores
# ---------------------------------------------------------------------------

foreach ($controlador in Get-ChildItem -Path (Join-Path $raiz 'app\Controllers') -Filter '*.php' -File) {
    $texto = [System.IO.File]::ReadAllText($controlador.FullName)
    $relativo = $controlador.FullName.Substring($raiz.Length + 1)

    foreach ($vista in [regex]::Matches($texto, "View::(?:display|render)\(\s*'([^']+)'")) {
        $nombre = $vista.Groups[1].Value -replace '\.', '\' -replace '/', '\'
        $rutaVista = Join-Path (Join-Path $raiz 'app\Views') ($nombre + '.php')

        if (-not (Test-Path $rutaVista)) {
            $hallazgos.Add("$relativo : vista inexistente $nombre.php")
        }
    }
}

# ---------------------------------------------------------------------------
#  6. Metodos propios de cada clase (llamadas a $this->metodo)
# ---------------------------------------------------------------------------
#  Comprueba que todo metodo invocado con $this-> exista en la misma clase.
#  Una llamada a un metodo inexistente provoca un error 500 en produccion.

foreach ($clase in Get-ChildItem -Path (Join-Path $raiz 'app') -Filter '*.php' -File -Recurse) {
    $texto = [System.IO.File]::ReadAllText($clase.FullName)
    $relativo = $clase.FullName.Substring($raiz.Length + 1)

    if ($texto -notmatch '\$this->') {
        continue
    }

    $definidos = New-Object System.Collections.Generic.HashSet[string]

    foreach ($m in [regex]::Matches($texto, '(?:public|private|protected)\s+(?:static\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)')) {
        [void] $definidos.Add($m.Groups[1].Value)
    }

    foreach ($m in [regex]::Matches($texto, '\$this->([A-Za-z_][A-Za-z0-9_]*)\s*\(')) {
        $metodo = $m.Groups[1].Value

        if (-not $definidos.Contains($metodo)) {
            $hallazgos.Add("$relativo : se llama a `$this->$metodo() pero ese metodo no existe en la clase")
        }
    }
}

# ---------------------------------------------------------------------------
#  7. Enlaces internos de las vistas contra las rutas declaradas
# ---------------------------------------------------------------------------
#  Un enlace a una ruta inexistente produce un 404 al pulsarlo. Se comparan
#  los patrones de url() de las vistas con las rutas de app/routes.php.

$rutasArchivo = Join-Path $raiz 'app\routes.php'
$rutasTexto = [System.IO.File]::ReadAllText($rutasArchivo)
$patrones = New-Object System.Collections.Generic.List[string]

foreach ($m in [regex]::Matches($rutasTexto, "\`$router->(?:get|post)\(\s*'([^']+)'")) {
    $ruta = $m.Groups[1].Value
    $regex = '^'
    $pos = 0

    foreach ($mm in [regex]::Matches($ruta, '\{[a-zA-Z_]+\}')) {
        $regex += [regex]::Escape($ruta.Substring($pos, $mm.Index - $pos))
        $regex += '[^/]+'
        $pos = $mm.Index + $mm.Length
    }

    $regex += [regex]::Escape($ruta.Substring($pos)) + '$'
    $patrones.Add($regex)
}

if ($patrones.Count -gt 0) {
    # Rutas que sirven archivos estaticos y no pasan por el router.
    $estaticas = @('/manifest.webmanifest', '/sw.js', '/offline.html', '/icons', '/assets', '/favicon.ico', '/robots.txt')

    foreach ($vista in Get-ChildItem -Path (Join-Path $raiz 'app\Views') -Filter '*.php' -File -Recurse) {
        $texto = [System.IO.File]::ReadAllText($vista.FullName)
        $relativo = $vista.FullName.Substring($raiz.Length + 1)

        foreach ($m in [regex]::Matches($texto, "url\(\s*'([^']*)'")) {
            $completo = $m.Groups[1].Value
            $destino = ($completo -split '\?')[0]

            # Las rutas concatenadas (url('/marca/' . $tipo)) no se pueden comprobar.
            if ($destino.EndsWith('/')) {
                continue
            }

            if (-not $destino.StartsWith('/')) {
                continue
            }

            $esEstatica = $false

            foreach ($prefijo in $estaticas) {
                if ($destino -eq $prefijo -or $destino.StartsWith(($prefijo + '/'))) {
                    $esEstatica = $true
                    break
                }
            }

            if ($esEstatica) {
                continue
            }

            $coincide = $false

            foreach ($patron in $patrones) {
                if ($destino -match $patron) {
                    $coincide = $true
                    break
                }
            }

            if (-not $coincide) {
                $hallazgos.Add("$relativo : enlace a la ruta inexistente $destino")
            }
        }
    }
}

# ---------------------------------------------------------------------------
#  8. Funciones auxiliares esperadas
# ---------------------------------------------------------------------------

$helpers = [System.IO.File]::ReadAllText((Join-Path $raiz 'app\Core\helpers.php'))
$esperadas = @(
    'config', 'base_path', 'storage_path', 'e', 'url', 'asset', 'csrf_token', 'csrf_field',
    'old', 'remember_old_input', 'is_current', 'abort', 'auth', 'can', 'usuario_nombre',
    'dinero', 'fecha_legible', 'query_url'
)

foreach ($funcion in $esperadas) {
    if ($helpers -notmatch ('function\s+' + [regex]::Escape($funcion) + '\s*\(')) {
        $hallazgos.Add("app\Core\helpers.php : falta la funcion $funcion")
    }
}

# ---------------------------------------------------------------------------
#  Informe
# ---------------------------------------------------------------------------

Write-Host ''
Write-Host "Archivos PHP revisados: $revisados"

if ($hallazgos.Count -eq 0) {
    Write-Host 'Sin hallazgos: la verificacion estatica paso.' -ForegroundColor Green

    exit 0
}

Write-Host "Hallazgos: $($hallazgos.Count)" -ForegroundColor Yellow

foreach ($hallazgo in $hallazgos) {
    Write-Host "  - $hallazgo"
}

exit 1
