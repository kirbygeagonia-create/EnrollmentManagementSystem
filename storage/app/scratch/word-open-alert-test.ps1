# Opens a COPY of a docx in real Word with alerts ENABLED.
#
# The earlier check set DisplayAlerts = 0, which lets Word silently repair a damaged file and
# report success — so "OPENED OK" proved nothing about whether the owner sees a repair prompt.
# With alerts on, a file that needs repair leaves a modal dialog waiting, which shows up as this
# script being killed by a timeout rather than printing a verdict.
#
# Usage: powershell -File word-open-alert-test.ps1 <path-to.docx> <label>

param([string]$Path, [string]$Label = 'file')

$ErrorActionPreference = 'Stop'
$tmp = Join-Path $env:TEMP ('ems-alert-' + [guid]::NewGuid().ToString('N').Substring(0,8) + '.docx')
Copy-Item -LiteralPath $Path -Destination $tmp -Force

$before = @(Get-Process WINWORD -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Id)

try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    # DisplayAlerts is deliberately left at its default (all alerts on). Setting it to 0 is what
    # hid the truth in the earlier test; value 1 is rejected by this Word build as out of range.
    $doc = $word.Documents.Open($tmp, $false, $true)

    $lines = @(
        "VERDICT: OPENED CLEAN",
        "label    : $Label",
        "pages    : $($doc.ComputeStatistics(2))",
        "tables   : $($doc.Tables.Count)"
    )
    $doc.Close($false)
}
catch {
    $lines = @("VERDICT: WORD REPORTED A PROBLEM", "label    : $Label", "error    : $($_.Exception.Message)")
}
finally {
    $mine = @(Get-Process WINWORD -ErrorAction SilentlyContinue | ForEach-Object { $_.Id } | Where-Object { $before -notcontains $_ })
    foreach ($id in $mine) {
        try { (Get-Process -Id $id -ErrorAction SilentlyContinue).Kill(); (Get-Process -Id $id -ErrorAction SilentlyContinue).WaitForExit(5000) } catch {}
    }
    Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
}

$lines | ForEach-Object { Write-Output $_ }
