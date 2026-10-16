# Opens a COPY of the authority docx in real Word, read-only.
# The copy is deliberate: an earlier invisible Word instance had the original locked, and a
# lock makes "Word refused it" and "Word could not open it" indistinguishable.

$ErrorActionPreference = 'Stop'
function Get-RandomName { ([guid]::NewGuid().ToString('N').Substring(0,8)) }
$src = 'C:\Users\ADMIN\OneDrive\Documents\3rd_Year\EnrollmentManagementSystem\Documentation\EMS Complete Documentation.docx'
$tmp = Join-Path $env:TEMP ('ems-word-check-' + (Get-RandomName) + '.docx')
$out = 'C:\Users\ADMIN\OneDrive\Documents\3rd_Year\EnrollmentManagementSystem\storage\app\scratch\word-open-report.txt'

Copy-Item -LiteralPath $src -Destination $tmp -Force
$bytes = (Get-FileHash -LiteralPath $src).Hash
$hashCopy = (Get-FileHash -LiteralPath $tmp).Hash

$lines = @()
$lines += "source sha256 : $bytes"
$lines += "copy  sha256  : $hashCopy  identical=$($bytes -eq $hashCopy)"

$before = @(Get-Process WINWORD -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Id)
$lines += "WINWORD pids before: $($before -join ', ')"

try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $mine = @(Get-Process WINWORD -ErrorAction SilentlyContinue | ForEach-Object { $_.Id } | Where-Object { $before -notcontains $_ })
    $lines += "instance this script started: $($mine -join ', ')"

    $doc = $word.Documents.Open($tmp, $false, $true)
    $word.ActiveDocument.Repaginate()

    $lines += "OPENED OK"
    $lines += "pages     : $($doc.ComputeStatistics(2))"
    $lines += "words     : $($doc.ComputeStatistics(0))"
    $lines += "paragraphs: $($doc.Paragraphs.Count)"
    $lines += "tables    : $($doc.Tables.Count)"

    $doc.Close($false)
    if ($mine.Count -gt 0) { $word.Quit(); $lines += "quit the instance this script started" }
    else { $lines += "left the pre-existing instance alone" }
}
catch {
    $lines += "WORD REPORTED A PROBLEM: $($_.Exception.Message)"
}
finally {
    Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
    $after = @(Get-Process WINWORD -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Id)
    $lines += "WINWORD pids after : $($after -join ', ')"
}

Set-Content -Path $out -Value ($lines -join "`n") -Encoding UTF8
Write-Output "report at $out"
