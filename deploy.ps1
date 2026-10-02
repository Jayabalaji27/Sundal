cd "c:\Users\vgopalakrishnan\Desktop\AI\sundal\main-file"

Compress-Archive -Path (
    Get-ChildItem -Force | Where-Object {
        $_.Name -notin @('node_modules', '.git', '.env', 'DEV-LOG.md', 'MODULES.md')
    } | Select-Object -ExpandProperty FullName
) -DestinationPath "..\swatle-deploy.zip"