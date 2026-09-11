$ErrorActionPreference = 'Stop'
$xlsxPath = (Get-ChildItem -LiteralPath 'outputs\certi_almacen_20260911' -Filter 'Ventanilla Certificados DD (*.xlsx' |
    Sort-Object LastWriteTime -Descending |
    Select-Object -First 1).FullName
$previewDir = (Resolve-Path -LiteralPath 'outputs\certi_almacen_20260911').Path
$excel = $null
$workbook = $null

try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $excel.DisplayAlerts = $false
    $workbook = $excel.Workbooks.Open($xlsxPath, 0, $true)
    $sheet = $workbook.Worksheets.Item(1)
    $lastRow = $sheet.UsedRange.Rows.Count
    $lastStart = [Math]::Max(2, $lastRow - 19)

    foreach ($preview in @(
        @{ Range = 'A1:I22'; File = 'preview_inicio.png' },
        @{ Range = "A${lastStart}:I${lastRow}"; File = 'preview_final.png' }
    )) {
        $range = $sheet.Range($preview.Range)
        $sheet.Activate() | Out-Null
        $excel.Goto($range, $true)
        $range.Select() | Out-Null
        $range.CopyPicture(1, -4147)
        Start-Sleep -Milliseconds 500
        $chartObject = $sheet.ChartObjects().Add(0, 0, $range.Width + 8, $range.Height + 8)
        $chartObject.Activate() | Out-Null
        $chartObject.Chart.Paste() | Out-Null
        Start-Sleep -Milliseconds 500
        $chartObject.Chart.Export((Join-Path $previewDir $preview.File), 'PNG') | Out-Null
        $chartObject.Delete()
    }
}
finally {
    if ($workbook -ne $null) { $workbook.Close($false) }
    if ($excel -ne $null) { $excel.Quit() }
    if ($sheet -ne $null) { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($sheet) | Out-Null }
    if ($workbook -ne $null) { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($workbook) | Out-Null }
    if ($excel -ne $null) { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
