@echo off
cls
setlocal EnableExtensions

:: Check necessary data
if not "%XVHM_APP_STARTED%"=="true" goto missing
if "%XVHM_APP_DIR%"=="" goto missing
if "%XVHM_TMP_DIR%"=="" goto missing
if "%XVHM_CACERT_DIR%"=="" goto missing
if "%XVHM_OPENSSL_BIN%"=="" goto missing
if "%XVHM_OPENSSL_SUBJECT_CN%"=="" goto missing
if "%XVHM_OPENSSL_SUBJECT_O%"=="" goto missing
if "%XVHM_OPENSSL_SUBJECT_OU%"=="" goto missing
goto startGenerate

:missing
echo.
echo Missing environment variables or input parameters.
echo Please run application from command "xvhosts"
exit /B

:startGenerate
if exist "%XVHM_TMP_DIR%" del /Q "%XVHM_TMP_DIR%\."
if not exist "%XVHM_TMP_DIR%" mkdir "%XVHM_TMP_DIR%"
if not exist "%XVHM_CACERT_DIR%" mkdir "%XVHM_CACERT_DIR%"

echo.
echo ========================================
echo Start generate new CA certificate bundle

set subjectArgs="/CN=%XVHM_OPENSSL_SUBJECT_CN%/O=%XVHM_OPENSSL_SUBJECT_O%/OU=%XVHM_OPENSSL_SUBJECT_OU%"
set pemFile=%XVHM_TMP_DIR%\cacert.pem
set keyPemFile=%XVHM_TMP_DIR%\cacert.key.pem
set crtFile=%XVHM_TMP_DIR%\cacert.crt
set keyFile=%XVHM_TMP_DIR%\cacert.key
set nameFile=%XVHM_TMP_DIR%\cacert.name

set OPENSSL_CONF=%XVHM_CACERT_GENERATE_CONFIG%
%XVHM_OPENSSL_BIN% req -batch -x509 -newkey rsa:2048 -sha256 -nodes -subj %subjectArgs% -days 18250 -out "%pemFile%" -outform PEM -keyout "%keyPemFile%"
%XVHM_OPENSSL_BIN% x509 -outform der -in "%pemFile%" -out "%crtFile%"
%XVHM_OPENSSL_BIN% rsa -in "%keyPemFile%" -out "%keyFile%"

if not exist "%nameFile%" type nul > "%nameFile%"
echo|set /p="%XVHM_OPENSSL_SUBJECT_CN%" > "%nameFile%"

echo.
echo ========================================
echo The CA certificate bundle was generated.
echo Moving them to %XVHM_CACERT_DIR%

move /Y "%XVHM_TMP_DIR%\cacert.*" "%XVHM_CACERT_DIR%"

echo.
echo ========================================
echo Clear temporary data

set OPENSSL_CONF=
set subjectArgs=
set pemFile=
set keyPemFile=
set crtFile=
set keyFile=
set nameFile=
del /Q "%XVHM_TMP_DIR%\."

echo.
echo ========================================
echo Installing the CA certificate

cscript //NoLogo "%XVHM_CACERT_INSTALLER%" "%XVHM_CACERT_DIR%\cacert.crt"

echo.
echo ========================================
echo The "%XVHM_OPENSSL_SUBJECT_CN%" was registered to system.
echo And certificate bundle of this CA is being stored at %XVHM_CACERT_DIR%

endlocal