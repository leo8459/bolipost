$ErrorActionPreference = "Stop"

$OutputPath = Join-Path (Get-Location) "docs\documentacion_api_direcciones_destino.docx"
$TempRoot = Join-Path (Get-Location) "storage\app\docx_api_direcciones_build"
$BaseUrl = "https://trackingbo.correos.gob.bo:8100"

$Token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC9sb2NhbGhvc3QiLCJhdWQiOiJib2xpcG9zdC1kaXJlY2Npb25lcy1kZXN0aW5vIiwic3ViIjoiMSIsImp0aSI6ImFjYjg4ZWM1MjNiMTNiYjIzMWFlYTAwOTQ1Zjc4Y2U1MzI1MDRmODYxMjQ2NmMyZjEzNTBlMDdjNTQwNjFiZDAiLCJuYW1lIjoiQVBJIDEiLCJpYXQiOjE3ODQzMDU2ODN9.QFRWGF0ph0_MhqWrf9AAJ6QQUmPQxTj5vKFvFAIDP0Q"

function XmlEscape([string] $Text) {
    return [System.Security.SecurityElement]::Escape($Text)
}

function Paragraph([string] $Text, [string] $Style = "Normal") {
    $escaped = XmlEscape $Text
    return "<w:p><w:pPr><w:pStyle w:val=`"$Style`"/></w:pPr><w:r><w:t xml:space=`"preserve`">$escaped</w:t></w:r></w:p>"
}

function CodeParagraph([string] $Text) {
    $escaped = XmlEscape $Text
    return "<w:p><w:pPr><w:pStyle w:val=`"CodeBlock`"/></w:pPr><w:r><w:t xml:space=`"preserve`">$escaped</w:t></w:r></w:p>"
}

function EndpointRow([string] $Uso, [string] $Metodo, [string] $Url, [string] $Descripcion, [bool] $Header = $false) {
    $fill = if ($Header) { "<w:shd w:fill=`"E8EEF5`"/>" } else { "" }
    $boldStart = if ($Header) { "<w:b/>" } else { "" }
    $cells = @($Uso, $Metodo, $Url, $Descripcion)
    $widths = @(1700, 1100, 4500, 2300)
    $xml = "<w:tr>"
    for ($i = 0; $i -lt $cells.Count; $i++) {
        $value = XmlEscape $cells[$i]
        $xml += "<w:tc><w:tcPr><w:tcW w:w=`"$($widths[$i])`" w:type=`"dxa`"/>$fill</w:tcPr><w:p><w:r><w:rPr>$boldStart<w:sz w:val=`"18`"/></w:rPr><w:t xml:space=`"preserve`">$value</w:t></w:r></w:p></w:tc>"
    }
    $xml += "</w:tr>"
    return $xml
}

if (Test-Path -LiteralPath $TempRoot) {
    Remove-Item -LiteralPath $TempRoot -Recurse -Force
}

New-Item -ItemType Directory -Force -Path $TempRoot | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $TempRoot "_rels") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $TempRoot "word") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $TempRoot "word\_rels") | Out-Null

$contentTypes = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
'@

$rels = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
'@

$docRels = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
'@

$styles = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:pPr><w:spacing w:after="120" w:line="264" w:lineRule="auto"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Title">
    <w:name w:val="Title"/>
    <w:pPr><w:jc w:val="center"/><w:spacing w:after="160"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="1F3A5F"/><w:sz w:val="40"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Subtitle">
    <w:name w:val="Subtitle"/>
    <w:pPr><w:jc w:val="center"/><w:spacing w:after="240"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:i/><w:color w:val="555555"/><w:sz w:val="22"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:pPr><w:spacing w:before="280" w:after="120"/><w:outlineLvl w:val="0"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="2E74B5"/><w:sz w:val="32"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:pPr><w:spacing w:before="200" w:after="100"/><w:outlineLvl w:val="1"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="2E74B5"/><w:sz w:val="26"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="CodeBlock">
    <w:name w:val="Code Block"/>
    <w:pPr><w:spacing w:before="80" w:after="80"/><w:ind w:left="240"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:color w:val="222222"/><w:sz w:val="18"/></w:rPr>
  </w:style>
</w:styles>
'@

$bodyParts = New-Object System.Collections.Generic.List[string]
$bodyParts.Add((Paragraph "Documentacion API - Direcciones Destinatario" "Title"))
$bodyParts.Add((Paragraph "Bolipost / TrackingBO" "Subtitle"))
$bodyParts.Add((Paragraph "Esta guia explica como consumir la API que entrega nombre, direccion_destinatario y ciudad de los paquetes. Todas las rutas requieren token JWT en el header Authorization."))

$bodyParts.Add((Paragraph "Token de acceso" "Heading1"))
$bodyParts.Add((Paragraph "Usar este token en Postman como Bearer Token:"))
$bodyParts.Add((CodeParagraph $Token))

$bodyParts.Add((Paragraph "Headers requeridos" "Heading1"))
$bodyParts.Add((CodeParagraph "Authorization: Bearer $Token"))
$bodyParts.Add((CodeParagraph "Accept: application/json"))
$bodyParts.Add((CodeParagraph "Content-Type: application/json  (solo para POST, PUT o PATCH)"))

$bodyParts.Add((Paragraph "Endpoints disponibles" "Heading1"))
$tableXml = "<w:tbl><w:tblPr><w:tblW w:w=`"9600`" w:type=`"dxa`"/><w:tblBorders><w:top w:val=`"single`" w:sz=`"4`" w:color=`"DADCE0`"/><w:left w:val=`"single`" w:sz=`"4`" w:color=`"DADCE0`"/><w:bottom w:val=`"single`" w:sz=`"4`" w:color=`"DADCE0`"/><w:right w:val=`"single`" w:sz=`"4`" w:color=`"DADCE0`"/><w:insideH w:val=`"single`" w:sz=`"4`" w:color=`"DADCE0`"/><w:insideV w:val=`"single`" w:sz=`"4`" w:color=`"DADCE0`"/></w:tblBorders></w:tblPr>"
$tableXml += (EndpointRow "Uso" "Metodo" "URL" "Descripcion" $true)
$tableXml += (EndpointRow "Mostrar todo" "GET" "$BaseUrl/api/direcciones-destino/todo" "Devuelve todos los registros sin paginacion.")
$tableXml += (EndpointRow "Mostrar por rango" "GET" "$BaseUrl/api/direcciones-destino/cantidad?desde=1&hasta=500" "Devuelve del registro 1 al 500, ordenado del mas nuevo al mas antiguo.")
$tableXml += "</w:tbl>"
$bodyParts.Add($tableXml)

$bodyParts.Add((Paragraph "Mostrar todos los registros" "Heading1"))
$bodyParts.Add((Paragraph "Devuelve todos los registros disponibles, ordenados del mas nuevo al mas antiguo."))
$bodyParts.Add((CodeParagraph "GET $BaseUrl/api/direcciones-destino/todo"))

$bodyParts.Add((Paragraph "Mostrar por rango" "Heading1"))
$bodyParts.Add((Paragraph "Permite pedir un tramo exacto. Por ejemplo, si existen mas de 50.000 registros, desde=1&hasta=500 devuelve del registro 1 al 500."))
$bodyParts.Add((CodeParagraph "GET $BaseUrl/api/direcciones-destino/cantidad?desde=1&hasta=500"))
$bodyParts.Add((CodeParagraph "GET $BaseUrl/api/direcciones-destino/cantidad?desde=501&hasta=1000"))

$bodyParts.Add((Paragraph "Ejemplo de respuesta" "Heading1"))
$bodyParts.Add((CodeParagraph '{ "data": [ { "nombre": "WENDY KAREN CABRERA SANCHEZ", "direccion_destinatario": "C/BENI No 356 ENTRE SANTA CRUZ Y TOMAS FRIAS Z/NORTE", "ciudad": "COCHABAMBA" } ], "desde": 1, "hasta": 500, "cantidad_solicitada": 500, "cantidad_mostrada": 500, "total_disponible": 53493, "orden": "mas_nuevo_a_mas_antiguo" }'))

$bodyParts.Add((Paragraph "Notas importantes" "Heading1"))
$bodyParts.Add((Paragraph "El token se administra desde Configuraciones > APIS."))
$bodyParts.Add((Paragraph "Si el token se da de baja, la API devuelve error 401 y deja de funcionar para ese token."))
$bodyParts.Add((Paragraph "La API solo muestra los campos nombre, direccion_destinatario y ciudad."))
$bodyParts.Add((Paragraph "El orden de consulta es del registro mas nuevo al mas antiguo."))

$documentXml = "<?xml version=`"1.0`" encoding=`"UTF-8`" standalone=`"yes`"?><w:document xmlns:w=`"http://schemas.openxmlformats.org/wordprocessingml/2006/main`"><w:body>"
$documentXml += ($bodyParts -join "")
$documentXml += "<w:sectPr><w:pgSz w:w=`"12240`" w:h=`"15840`"/><w:pgMar w:top=`"1152`" w:right=`"1152`" w:bottom=`"1152`" w:left=`"1152`" w:header=`"708`" w:footer=`"708`" w:gutter=`"0`"/></w:sectPr></w:body></w:document>"

Set-Content -LiteralPath (Join-Path $TempRoot "[Content_Types].xml") -Value $contentTypes -Encoding UTF8
Set-Content -LiteralPath (Join-Path $TempRoot "_rels\.rels") -Value $rels -Encoding UTF8
Set-Content -LiteralPath (Join-Path $TempRoot "word\_rels\document.xml.rels") -Value $docRels -Encoding UTF8
Set-Content -LiteralPath (Join-Path $TempRoot "word\styles.xml") -Value $styles -Encoding UTF8
Set-Content -LiteralPath (Join-Path $TempRoot "word\document.xml") -Value $documentXml -Encoding UTF8

if (Test-Path -LiteralPath $OutputPath) {
    Remove-Item -LiteralPath $OutputPath -Force
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($TempRoot, $OutputPath)
Write-Output $OutputPath
