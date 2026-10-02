@echo off
setlocal

rem ---------------------------------------------------------------
rem Fast, uncompressed deploy zip. Stages a filtered copy of the repo
rem into a temp folder (robocopy, so excludes are reliable), then
rem zips that folder with NO compression (store-only) for speed.
rem
rem Excluded on purpose (won't crash the server, just keeps zip lean):
rem   node_modules, .git, vendor            -> run `composer install` on the server
rem   .env, env.example                     -> never overwrite server secrets
rem   bootstrap\cache                       -> stale compiled config/routes
rem   storage\framework\{cache,sessions,views}, storage\logs -> runtime-only
rem   storage\app\public, public\storage    -> live user uploads / symlink, don't overwrite
rem   tests, e2e, test-results, .vscode, .idea -> dev-only
rem ---------------------------------------------------------------

set "SRC=%~dp0"
set "STAGE=%TEMP%\sundal-deploy-stage"
set "OUT=%~dp0..\sundal-deploy.zip"

echo Cleaning staging folder...
if exist "%STAGE%" rmdir /s /q "%STAGE%"
mkdir "%STAGE%"

echo Copying project files (excludes applied)...
robocopy "%SRC%." "%STAGE%" /E /MT:16 /NFL /NDL /NJH /NJS /NC /NS ^
  /XD "node_modules" ".git" "vendor" "bootstrap\cache" ^
      "storage\framework\cache" "storage\framework\sessions" "storage\framework\views" ^
      "storage\logs" "storage\app\public" "public\storage" ^
      "tests" "e2e" "test-results" ".vscode" ".idea" ".playwright-mcp" ".qa-tmp" "graphify-out" ^
  /XF ".env" "env.example" ".env.example" "*.log" ".DS_Store" "Thumbs.db" "*.zip" "*.7z*"

rem robocopy exit codes 0-7 are all "success" states, 8+ means real errors
if %ERRORLEVEL% GEQ 8 (
  echo ROBOCOPY FAILED with code %ERRORLEVEL%
  exit /b 1
)

echo Zipping (store-only, no compression)...
if exist "%OUT%" del /f /q "%OUT%"

set "SEVENZIP=C:\Program Files\7-Zip\7z.exe"
if exist "%SEVENZIP%" (
  rem -mx0 = store only, -mmt = multi-threaded: far faster than Compress-Archive on large trees
  "%SEVENZIP%" a -tzip -mx0 -mmt "%OUT%" "%STAGE%\*" >nul
) else (
  echo 7-Zip not found at "%SEVENZIP%", falling back to Compress-Archive ^(this will be slower^)...
  powershell -NoProfile -Command ^
    "Compress-Archive -Path '%STAGE%\*' -DestinationPath '%OUT%' -CompressionLevel NoCompression -Force"
)

echo Cleaning up staging folder...
rmdir /s /q "%STAGE%"

echo.
echo Done: %OUT%
endlocal
