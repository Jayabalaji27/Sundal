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

rem public\hot is written by `npm run dev` and makes Laravel load assets from
rem 127.0.0.1:5173 instead of public\build. It must never reach the server.
if exist "%SRC%public\hot" del /f /q "%SRC%public\hot"
if exist "%SRC%storage\framework\vite.hot" del /f /q "%SRC%storage\framework\vite.hot"

echo Building production assets...
pushd "%SRC%"
call npm run build
if %ERRORLEVEL% NEQ 0 (
  popd
  echo NPM BUILD FAILED - aborting deploy
  exit /b 1
)
popd

echo Cleaning staging folder...
if exist "%STAGE%" rmdir /s /q "%STAGE%"
mkdir "%STAGE%"

echo Copying project files (excludes applied)...
robocopy "%SRC%." "%STAGE%" /E /MT:16 /NFL /NDL /NJH /NJS /NC /NS ^
  /XD "node_modules" ".git" "vendor" "bootstrap\cache" ^
      "storage\framework\cache" "storage\framework\sessions" "storage\framework\views" ^
      "storage\logs" "storage\app\public" "public\storage" ^
      "tests" "e2e" "test-results" ".vscode" ".idea" ".claude" ".playwright-mcp" ".qa-tmp" "graphify-out" ^
  /XF ".env" ".env.*" "env.example" "*.log" ".DS_Store" "Thumbs.db" "*.zip" "*.7z*"

rem robocopy exit codes 0-7 are all "success" states, 8+ means real errors
if %ERRORLEVEL% GEQ 8 (
  echo ROBOCOPY FAILED with code %ERRORLEVEL%
  exit /b 1
)

if exist "%STAGE%\public\hot" del /f /q "%STAGE%\public\hot"
if exist "%STAGE%\storage\framework\vite.hot" del /f /q "%STAGE%\storage\framework\vite.hot"

echo Zipping (store-only, no compression)...
if exist "%OUT%" del /f /q "%OUT%"

set "SEVENZIP=C:\Program Files\7-Zip\7z.exe"
if exist "%SEVENZIP%" (
  rem -mx0 = store only, -mmt = multi-threaded: far faster than Compress-Archive on large trees
  "%SEVENZIP%" a -tzip -mx0 -mmt "%OUT%" "%STAGE%\*" >nul
) else (
  rem Not Compress-Archive: on Windows PowerShell 5.1 it stores "app\Providers\x.php"
  rem paths, which Linux extracts as single files with backslashes in the name.
  rem The built-in bsdtar writes "/" separators and its * includes dotfiles.
  echo 7-Zip not found at "%SEVENZIP%", falling back to Windows tar...
  pushd "%STAGE%"
  "%SystemRoot%\System32\tar.exe" -a -cf "%OUT%" --options zip:compression=store *
  rem "if errorlevel" is evaluated at run time; %ERRORLEVEL% inside ( ) is not.
  if errorlevel 1 (
    popd
    echo ZIP FAILED
    exit /b 1
  )
  popd
)

echo Cleaning up staging folder...
rmdir /s /q "%STAGE%"

echo.
echo Done: %OUT%
endlocal
