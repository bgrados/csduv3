# ============================================================================
#  CSDUV3 - Generador de iconos de la aplicación instalable (PWA)
# ----------------------------------------------------------------------------
#  Script de apoyo para el desarrollador. Genera los archivos PNG que espera
#  public/manifest.webmanifest a partir del color institucional provisional.
#
#  Uso (Windows, PowerShell):
#      powershell -NoProfile -ExecutionPolicy Bypass -File tools/generar_iconos.ps1
#
#  Cuando el club entregue su identidad oficial, reemplace los PNG generados
#  por los iconos institucionales definitivos.
#
#  Este script NO se ejecuta en el servidor: solo sirve para preparar archivos.
# ============================================================================

Add-Type -AssemblyName System.Drawing

$ErrorActionPreference = 'Stop'

$raiz = Split-Path -Parent $PSScriptRoot
$destino = Join-Path $raiz 'public\icons'

if (-not (Test-Path $destino)) {
    New-Item -ItemType Directory -Path $destino | Out-Null
}

$colorFondo = [System.Drawing.ColorTranslator]::FromHtml('#1f3b5c')
$colorTexto = [System.Drawing.Color]::White

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
        [bool]$Maskable = $false
    )

    $bitmap = New-Object System.Drawing.Bitmap($Tamano, $Tamano)
    $grafico = [System.Drawing.Graphics]::FromImage($bitmap)
    $grafico.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $grafico.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

    # Fondo: para iconos normales se redondean las esquinas; para los
    # maskable se cubre todo el cuadro (la plataforma recorta la forma).
    if ($Maskable) {
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
        $rutaEsquinas.Dispose()
    }

    # Proporción del texto: en maskable se reduce para respetar la zona segura.
    $proporcion = if ($Maskable) { 0.13 } else { 0.165 }
    $fuente = Nueva-Fuente ([int]($Tamano * $proporcion))
    $pincelTexto = New-Object System.Drawing.SolidBrush($colorTexto)

    $formato = New-Object System.Drawing.StringFormat
    $formato.Alignment = [System.Drawing.StringAlignment]::Center
    $formato.LineAlignment = [System.Drawing.StringAlignment]::Center

    $area = New-Object System.Drawing.RectangleF(0, 0, $Tamano, $Tamano)
    $grafico.DrawString('CSDUV3', $fuente, $pincelTexto, $area, $formato)

    $bitmap.Save($Ruta, [System.Drawing.Imaging.ImageFormat]::Png)

    $pincelTexto.Dispose()
    $fuente.Dispose()
    $grafico.Dispose()
    $bitmap.Dispose()
}

Crear-Icono -Tamano 192 -Ruta (Join-Path $destino 'icon-192.png')
Crear-Icono -Tamano 512 -Ruta (Join-Path $destino 'icon-512.png')
Crear-Icono -Tamano 192 -Ruta (Join-Path $destino 'icon-maskable-192.png') -Maskable $true
Crear-Icono -Tamano 512 -Ruta (Join-Path $destino 'icon-maskable-512.png') -Maskable $true
Crear-Icono -Tamano 180 -Ruta (Join-Path $destino 'apple-touch-icon-180.png')

Write-Host "Iconos generados en $destino"
