@echo off
title Xampp vHosts Manager
setlocal EnableExtensions EnableDelayedExpansion

rem ---------------------------------------------
for /F "tokens=* USEBACKQ" %%v in (`where php`) do (
    if not exist "%%v" goto phpBinNotFound
)

rem ---------------------------------------------
set XVHM_APP_DIR=%~dp0
if not "%XVHM_APP_DIR:~-2%"==":\" set XVHM_APP_DIR=%XVHM_APP_DIR:~0,-1%

set XVHM_POWER_EXECUTOR=%XVHM_APP_DIR%\support\PowerExec.vbs
set XVHM_PHP_CONTROLLER=%XVHM_APP_DIR%\xvhosts.php
goto startCommand

rem ---------------------------------------------
:clearEnvVars
set XVHM_APP_DIR=
set XVHM_POWER_EXECUTOR=
set XVHM_PHP_CONTROLLER=
exit /B

rem ---------------------------------------------
:phpBinNotFound
echo.
echo Cannot find PHP cli.
echo Make sure you have add the path to your PHP directory into Windows Path Environment Variable.
call :clearEnvVars
exit /B 1

rem ---------------------------------------------
:installationFailed
echo.
echo Installation Xampp vHosts Manager failed.
echo Please review the instructions carefully before installation.
echo.
pause>nul|set/p =Press any key to exit terminal...
call :clearEnvVars
exit 1

rem ---------------------------------------------
:missingArgs
echo.
echo Xampp vHosts Manager error: The "command" argument is missing.
echo.
goto help
call :clearEnvVars
exit /B 1

rem ---------------------------------------------
:install
FSUTIL dirty query %SystemDrive%>nul
if %errorLevel% NEQ 0 (
    echo.
    echo This process can only be run with elevated permission.
    pause>nul|set/p =Press any key to start this process in Administrator mode...
    echo.
    cscript //NoLogo "%XVHM_POWER_EXECUTOR%" -e -x -n "%~fx0" "install"
    if errorLevel 1 (
        echo The installation was canceled by user.
        exit /B 1
    ) else (
        echo The installation started in new window with Elevated permission.
        exit /B
    )
)
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "install"
if errorLevel 1 goto installationFailed
echo.
pause>nul|set/p =Press any key to exit terminal...
call :clearEnvVars
exit

rem ---------------------------------------------
:help
type "%XVHM_APP_DIR%\help.hlp"
call :clearEnvVars
exit /B

rem ---------------------------------------------
:newHost
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "newHost" "%~2"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:showHostInfo
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "showHostInfo" "%~2"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:listHosts
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "listHosts"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:removeHost
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "removeHost" "%~2"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:addSSL
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "addSSL" "%~2"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:removeSSL
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "removeSSL" "%~2"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:changeDocRoot
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "changeDocRoot" "%~2"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:registerPath
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "registerPath"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:stopApache
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "stopApache"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:startApache
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "startApache"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:restartApache
php -n -d output_buffering=0 "%XVHM_PHP_CONTROLLER%" "restartApache"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:startCommand
cls
if "%~1"=="" goto missingArgs
if "%~1"=="help" goto help
if "%~1"=="install" goto install
if "%~1"=="new" goto newHost
if "%~1"=="show" goto showHostInfo
if "%~1"=="list" goto listHosts
if "%~1"=="remove" goto removeHost
if "%~1"=="add_ssl" goto addSSL
if "%~1"=="remove_ssl" goto removeSSL
if "%~1"=="change_docroot" goto changeDocRoot
if "%~1"=="register_path" goto registerPath
if "%~1"=="stop_apache" goto stopApache
if "%~1"=="start_apache" goto startApache
if "%~1"=="restart_apache" goto restartApache

rem Call command with unknown param -------------
echo.
echo Xampp vHosts Manager error: "%~1" is invalid xvhosts command.
echo.
goto help

endlocal