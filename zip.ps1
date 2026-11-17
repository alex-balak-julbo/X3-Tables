$ProjectName = (Get-Item .).Name

$Excludes = Get-Content .gitignore |
    Where-Object {
        $_.Trim() -ne "" -and
        -not $_.StartsWith("#")
    } |
    ForEach-Object {
        "-x!$($_.Replace('/', '\'))"
    }

& "C:\Program Files\7-Zip\7z.exe" a "$ProjectName.zip" . $Excludes