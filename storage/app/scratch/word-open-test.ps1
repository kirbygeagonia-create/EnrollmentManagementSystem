# Opens the authority docx in real Word, read-only, and writes what Word sees to a file.
# The running WINWORD instance owned by the user is never touched: this script records the
# process ids before and after, and quits only the instance it started.

$ErrorActionPreference = 'Stop'
$docx = 'C:\Users\ADMIN\OneDrive\Documents\3rd_Year\EnrollmentManagementSystem\Documentation\EMS Complete Documentation.docx'
$out = 'C:\Users\ADMIN\OneDrive\Documents\3rd_Year\EnrollmentManagementSystem\storage\app\scratch\word-open-report.txt'

$before = @(Get-Process WINWORD -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Id)
$lines = @()
$lines += "WINWORD pids before: $($before -join ', ')"

try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0

    $mine = @(Get-Process WINWORD -ErrorAction SilentlyContinue | ForEach-Object { $_.Id } | Where-Object { $before -notcontains $_ })
    $lines += "instance this script started: $($mine -join ', ')"

    $doc = $word.Documents.Open($docx, $false, $true)   # ConfirmConversions=false, ReadOnly=true
    $doc.Activate()
    $word.ActiveDocument.Repaginate()

    $lines += "OPENED OK"
    $lines += "name            : $($doc.Name)"
    $lines += "pages           : $($doc.ComputeStatistics(2))"    # wdStatisticPages
    $lines += "words           : $($doc.ComputeStatistics(0))"    # wdStatisticWords
    $lines += "paragraphs      : $($doc.Paragraphs.Count)"
    $lines += "tables          : $($doc.Tables.Count)"
    $lines += "sections        : $($doc.Sections.Count)"
    $lines += "inline shapes   : $($doc.InlineShapes.Count)"

    # A table in the middle of the rebuilt chapter, read back through Word.
    $t = $doc.Tables.Item($doc.Tables.Count)
    $lines += "last table      : $($t.Rows.Count) rows x $($t.Columns.Count) cols"

    $doc.Close($false)
    if ($mine.Count -gt 0) {
        $word.Quit()
        $lines += "CLOSED, and quit the instance this script started"
    } else {
        # No new process appeared, which means COM attached to an instance that was already
        # running. Quitting that one would close the user's Word, so the document is only closed.
        $lines += "CLOSED; left the pre-existing Word instance running, because this script did not start it"
    }
}
catch {
    $lines += "WORD REPORTED A PROBLEM: $($_.Exception.Message)"
}
finally {
    $after = @(Get-Process WINWORD -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Id)
    $lines += "WINWORD pids after : $($after -join ', ')"
    $stillThere = @($before | Where-Object { $after -contains $_ })
    $lines += "the user's own instance(s) still running: $($stillThere -join ', ')"
}

Set-Content -Path $out -Value ($lines -join "`n") -Encoding UTF8
Write-Output "report written to $out"
