# ============================================================================
#  CSDUV3 - Generador de iconos de la aplicacion instalable (PWA)
# ----------------------------------------------------------------------------
#  Genera los PNG que espera public/manifest.webmanifest usando los colores
#  del logotipo institucional: amarillo #FEFE08 y verde #009F39.
#
#  Uso (Windows, PowerShell):
#      powershell -NoProfile -ExecutionPolicy Bypass -File tools/generar_iconos.ps1
#
#  Este script NO se ejecuta en el servidor: solo prepara archivos.
# ============================================================================

Add-Type -AssemblyName System.Drawing

$ErrorActionPreference = 'Stop'

$raiz = Split-Path -Parent $PSScriptRoot
$destino = Join-Path $raiz 'public\icons'

if (-not (Test-Path $destino)) {
    New-Item -ItemType Directory -Path $destino | Out-Null
}

$colorFondo = [System.Drawing.ColorTranslator]::FromHtml('#FEFE08')
$colorTexto = [System.Drawing.ColorTranslator]::FromHtml('#007A2C')
$colorBorde = [System.Drawing.ColorTranslator]::FromHtml('#009F39')

function Nueva-Fuente([int]$tamano) {
    $fuentes = @('Segoe UI', 'Arial', 'Verdana')

    foreach ($nombre in $fuentes) {
        try {
            return New-Object System.Drawing.Font($nombre, $tamano, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
        } catch {
            continue
        }
    }

    return New-Object System.Drawing.Font([System.Drawing.FontFamily]::GenericSansSerif, $tamano, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
}

function Crear-Icono {
    param(
        [int]$Tamano,
        [string]$Ruta,
        [bool]$Maskable = $false,
        [bool]$FondoCompleto = $false
    )

    $bitmap = New-Object System.Drawing.Bitmap($Tamano, $Tamano)
    $grafico = [System.Drawing.Graphics]::FromImage($bitmap)
    $grafico.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $grafico.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
    $grafico.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic

    # Fondo: los iconos normales llevan esquinas redondeadas y un borde verde.
    # Los maskable y el icono de Apple cubren todo el cuadro para evitar
    # esquinas transparentes cuando la plataforma aplica su propia máscara.
    if ($Maskable -or $FondoCompleto) {
        $grafico.Clear($colorFondo)
    } else {
        $grafico.Clear([System.Drawing.Color]::Transparent)

        $radio = [int]($Tamano * 0.2)
        $rutaEsquinas = New-Object System.Drawing.Drawing2D.GraphicsPath
        $diametro = $radio * 2

        $rutaEsquinas.AddArc(0, 0, $diametro, $diametro, 180, 90)
        $rutaEsquinas.AddArc($Tamano - $diametro - 1, 0, $diametro, $diametro, 270, 90)
        $rutaEsquinas.AddArc($Tamano - $diametro - 1, $Tamano - $diametro - 1, $diametro, $diametro, 0, 90)
        $rutaEsquinas.AddArc(0, $Tamano - $diametro - 1, $diametro, $diametro, 90, 90)
        $rutaEsquinas.CloseFigure()

        $pincelFondo = New-Object System.Drawing.SolidBrush($colorFondo)
        $grafico.FillPath($pincelFondo, $rutaEsquinas)
        $pincelFondo.Dispose()

        $grosor = [Math]::Max(2, [int]($Tamano * 0.012))
        $lapiz = New-Object System.Drawing.Pen($colorBorde, $grosor)
        $grafico.DrawPath($lapiz, $rutaEsquinas)
        $lapiz.Dispose()
        $rutaEsquinas.Dispose()
    }

    # Monograma UV3 y siglas, dentro de la zona segura de los iconos maskable.
    $proporcionPrincipal = if ($Maskable) { 0.16 } else { 0.19 }
    $proporcionSecundaria = if ($Maskable) { 0.042 } else { 0.05 }

    $fuentePrincipal = Nueva-Fuente ([int]($Tamano * $proporcionPrincipal))
    $fuenteSecundaria = Nueva-Fuente ([int]($Tamano * $proporcionSecundaria))
    $pincelTexto = New-Object System.Drawing.SolidBrush($colorTexto)

    $formato = New-Object System.Drawing.StringFormat
    $formato.Alignment = [System.Drawing.StringAlignment]::Center
    $formato.LineAlignment = [System.Drawing.StringAlignment]::Center

    $areaPrincipal = New-Object System.Drawing.RectangleF(0, - ($Tamano * 0.06), $Tamano, $Tamano)
    $areaSecundaria = New-Object System.Drawing.RectangleF(0, ($Tamano * 0.17), $Tamano, $Tamano)

    $grafico.DrawString('UV3', $fuentePrincipal, $pincelTexto, $areaPrincipal, $formato)
    $grafico.DrawString('CSDUV3', $fuenteSecundaria, $pincelTexto, $areaSecundaria, $formato)

    $bitmap.Save($Ruta, [System.Drawing.Imaging.ImageFormat]::Png)

    $pincelTexto.Dispose()
    $formato.Dispose()
    $fuentePrincipal.Dispose()
    $fuenteSecundaria.Dispose()
    $grafico.Dispose()
    $bitmap.Dispose()
}

Crear-Icono -Tamano 192 -Ruta (Join-Path $destino 'icon-192.png')
Crear-Icono -Tamano 512 -Ruta (Join-Path $destino 'icon-512.png')
Crear-Icono -Tamano 192 -Ruta (Join-Path $destino 'icon-maskable-192.png') -Maskable $true
Crear-Icono -Tamano 512 -Ruta (Join-Path $destino 'icon-maskable-512.png') -Maskable $true
Crear-Icono -Tamano 180 -Ruta (Join-Path $destino 'apple-touch-icon-180.png') -FondoCompleto $true

Write-Host "Iconos UV3 generados en $destino"
