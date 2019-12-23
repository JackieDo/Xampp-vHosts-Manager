@echo off
title Xampp vHosts Manager
setlocal EnableExtensions EnableDelayedExpansion

:: DETECT PHP ===================================
for /F "tokens=* USEBACKQ" %%v in (`where php`) do (
    if not exist "%%v" goto phpBinNotFound
    set XVHM_PHP_DIR=%%~dpv
    if "!XVHM_PHP_DIR:~-1!"=="\" set XVHM_PHP_DIR=!XVHM_PHP_DIR:~0,-1!
)

:: TURN ON STARTED FLAG =========================
set XVHM_APP_STARTED=true

:: DEFINE APP DIRS ==============================
set XVHM_APP_DIR=%~dp0
if not "%XVHM_APP_DIR:~-2%"==":\" set XVHM_APP_DIR=%XVHM_APP_DIR:~0,-1%
set XVHM_TMP_DIR=%XVHM_APP_DIR%\tmp
set XVHM_CACERT_DIR=%XVHM_APP_DIR%\cacert
set XVHM_CACERT_GENERATE_SCRIPT=%XVHM_APP_DIR%\cacert_generate.bat
set XVHM_CACERT_GENERATE_CONFIG=%XVHM_APP_DIR%\cacert_generate.cnf
set XVHM_VHOST_CONFIG_TEMPLATE=%XVHM_APP_DIR%\templates\vhost_config\vhost.conf.tpl
set XVHM_VHOST_SSL_CONFIG_TEMPLATE=%XVHM_APP_DIR%\templates\vhost_config\vhost_ssl.conf.tpl
set XVHM_VHOST_CERT_GENERATE_SCRIPT=%XVHM_APP_DIR%\vhostcert_generate.bat
set XVHM_VHOST_CERT_GENERATE_CONFIG=%XVHM_APP_DIR%\vhostcert_generate.cnf
set XVHM_APPPATH_REGISTER=%XVHM_APP_DIR%\apppath_register.vbs
set XVHM_POWER_EXECUTOR=%XVHM_APP_DIR%\support\PowerExec.vbs

:: INSTALL VHOSTS MANAGER AS REQUIRED ===========
if "%~1"=="install" (
    cls
    goto install
)
if not exist "%XVHM_APP_DIR%\settings.ini" goto needToInstall

:: DEFINE XAMPP DIRS ============================
:defineXamppDirs
:: Xampp dir
for /F "tokens=* USEBACKQ" %%v in (
    `php -n "%XVHM_APP_DIR%\xvhosts.php" "getSetting" "DirectoryPaths" "Xampp" " "`
) do (
    set XVHM_XAMPP_DIR=%%v
    set XVHM_XAMPP_DIR=!XVHM_XAMPP_DIR:/=\!
    if "!XVHM_XAMPP_DIR:~-1!"=="\" set XVHM_XAMPP_DIR=!XVHM_XAMPP_DIR:~0,-1!
    if not exist "!XVHM_XAMPP_DIR!\xampp-control.exe" goto xamppDirNotFound
)

:: Apache dir
for /F "tokens=* USEBACKQ" %%v in (
    `php -n "%XVHM_APP_DIR%\xvhosts.php" "getSetting" "DirectoryPaths" "Apache" " "`
) do (
    if "%%v"=="" (
        set XVHM_APACHE_DIR=!XVHM_XAMPP_DIR!\apache
    ) else (
        set XVHM_APACHE_DIR=%%v
    )
    set XVHM_APACHE_DIR=!XVHM_APACHE_DIR:/=\!
    if "!XVHM_APACHE_DIR:~-1!"=="\" set XVHM_APACHE_DIR=!XVHM_APACHE_DIR:~0,-1!
    if not exist "!XVHM_APACHE_DIR!\bin\httpd.exe" goto apacheDirNotFound
)

:: Related dirs
set XVHM_VHOST_CERT_DIR=%XVHM_APACHE_DIR%\conf\extra\certs
set XVHM_VHOST_CERT_KEY_DIR=%XVHM_APACHE_DIR%\conf\extra\keys
set XVHM_VHOST_CONFIG_DIR=%XVHM_APACHE_DIR%\conf\extra\vhosts
set XVHM_VHOST_SSL_CONFIG_DIR=%XVHM_APACHE_DIR%\conf\extra\vhosts_ssl

set XVHM_OPENSSL_BIN=%XVHM_APACHE_DIR%\bin\openssl.exe
set XVHM_OPENSSL_SUBJECT_CN=Xampp Certificate Authority
set XVHM_OPENSSL_SUBJECT_O=OpenSSL Software Foundation
set XVHM_OPENSSL_SUBJECT_OU=Server Certificate Provider
if exist "%XVHM_TMP_DIR%\.installing" goto install
goto startCommand

:: ERROR NOTATION ===============================
:phpBinNotFound
echo.
echo Cannot find PHP cli.
echo Make sure you have set the path to your PHP directory in system environment variables.
call :clearEnvVars
exit /B 1

:xamppDirNotFound
echo.
echo Cannot find Xampp directory.
echo Please check the configuration path to the Xampp directory in file "%XVHM_APP_DIR%\settings.ini".
call :clearEnvVars
exit /B 1

:apacheDirNotFound
echo.
echo Cannot find Apache directory.
echo Please check the configuration path to the Apache directory in file "%XVHM_APP_DIR%\settings.ini".
call :clearEnvVars
exit /B 1

:missingArgs
echo.
echo Xampp vHosts Manager Error: Missing the command argument.
echo.
goto help
call :clearEnvVars
exit /B 1

:needToInstall
echo.
echo Xampp vHosts Manager has not been integrated into Xampp.
echo Run command "xvhosts install" in Administartor mode to integrate it.
call :clearEnvVars
exit /B 1

:: INSTALL APP ==================================
:install
FSUTIL dirty query %SystemDrive% >nul
if %errorLevel% NEQ 0 (
    echo.
    echo Sorry! This script can only be run with elevated permission.
    pause>nul|set/p =Press any key to start the process in Administrator mode...
    echo.
    cscript //NoLogo "%XVHM_POWER_EXECUTOR%" -e "%XVHM_APP_DIR%\xvhosts.bat" install
    exit /B
)
if not exist "%XVHM_TMP_DIR%\.installing" (
    php -n -d output_buffering=0 "%XVHM_APP_DIR%\xvhosts.php" "install" "start"
    if not exist "%XVHM_TMP_DIR%\.installing" (
        call :clearEnvVars
        exit /B 1
    )
    goto defineXamppDirs
)
php -n -d output_buffering=0 "%XVHM_APP_DIR%\xvhosts.php" "install" "continue"
echo.
if not exist "%XVHM_CACERT_DIR%\cacert.crt" (
    echo Installation Xampp vHosts Manager failed.
    echo Please review the instructions carefully before installation.
    echo.
    call :clearEnvVars
    exit /B 1
)
echo XAMPP VHOSTS MANAGER WAS INSTALLED SUCCESSFULLY.
echo TO START USING IT, PLEASE EXIT YOUR TERMINAL TO DELETE TEMPORARY PROCESS ENVIRONMENT VARIABLES.
echo.
pause>nul|set/p =Press any key to exit terminal...
exit

:: APP FEATURES =================================
:help
type "%XVHM_APP_DIR%\help.hlp"
call :clearEnvVars
exit /B

:newHost
php -n -d output_buffering=0 "%XVHM_APP_DIR%\xvhosts.php" "newHost" "%~2"
call :clearEnvVars
exit /B

:showHostInfo
php -n -d output_buffering=0 "%XVHM_APP_DIR%\xvhosts.php" "showHostInfo" "%~2"
call :clearEnvVars
exit /B

:listHosts
php -n -d output_buffering=0 "%XVHM_APP_DIR%\xvhosts.php" "listHosts"
call :clearEnvVars
exit /B

:removeHost
php -n -d output_buffering=0 "%XVHM_APP_DIR%\xvhosts.php" "removeHost" "%~2"
call :clearEnvVars
exit /B

:addSSL
php -n -d output_buffering=0 "%XVHM_APP_DIR%\xvhosts.php" "addSSL" "%~2"
call :clearEnvVars
exit /B

:removeSSL
php -n -d output_buffering=0 "%XVHM_APP_DIR%\xvhosts.php" "removeSSL" "%~2"
call :clearEnvVars
exit /B

:restartApache
php -n -d output_buffering=0 "%XVHM_APP_DIR%\xvhosts.php" "restartApache"
call :clearEnvVars
exit /B

:startCommand
cls

:: integrated check
if not exist "%XVHM_CACERT_DIR%\cacert.crt" goto needToInstall

:: check special input param
if "%~1"=="" goto missingArgs
if "%~1"=="help" goto help
if "%~1"=="new" goto newHost
if "%~1"=="show" goto showHostInfo
if "%~1"=="list" goto listHosts
if "%~1"=="remove" goto removeHost
if "%~1"=="add_ssl" goto addSSL
if "%~1"=="remove_ssl" goto removeSSL
if "%~1"=="restart_apache" goto restartApache

:: call the xvhosts script without param
echo.
echo Xampp vHosts Manager Error: "%~1" is invalid xvhosts command.
echo.
goto help

:: END APP ======================================
:clearEnvVars
set XVHM_APP_STARTED=
set XVHM_APP_DIR=
set XVHM_TMP_DIR=
set XVHM_CACERT_DIR=
set XVHM_CACERT_GENERATE_SCRIPT=
set XVHM_CACERT_GENERATE_CONFIG=
set XVHM_VHOST_CONFIG_TEMPLATE=
set XVHM_VHOST_SSL_CONFIG_TEMPLATE=
set XVHM_VHOST_CERT_GENERATE_SCRIPT=
set XVHM_VHOST_CERT_GENERATE_CONFIG=
set XVHM_APPPATH_REGISTER=
set XVHM_POWER_EXECUTOR=
set XVHM_XAMPP_DIR=
set XVHM_PHP_DIR=
set XVHM_APACHE_DIR=
set XVHM_VHOST_CERT_DIR=
set XVHM_VHOST_CERT_KEY_DIR=
set XVHM_VHOST_CONFIG_DIR=
set XVHM_VHOST_SSL_CONFIG_DIR=
set XVHM_OPENSSL_BIN=
set XVHM_OPENSSL_SUBJECT_CN=
set XVHM_OPENSSL_SUBJECT_O=
set XVHM_OPENSSL_SUBJECT_OU=
exit /B

endlocal