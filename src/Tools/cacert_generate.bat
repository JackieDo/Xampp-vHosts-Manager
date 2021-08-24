@echo off
cls
setlocal EnableExtensions

rem Check necessary data ------------------------
if "%XVHM_APP_DIR%"=="" goto missing
if "%XVHM_TMP_DIR%"=="" goto missing
if "%XVHM_CACERT_DIR%"=="" goto missing
if "%XVHM_OPENSSL_BIN%"=="" goto missing
if "%XVHM_OPENSSL_SUBJECT_CN%"=="" goto missing
if "%XVHM_OPENSSL_SUBJECT_O%"=="" goto missing
if "%XVHM_OPENSSL_SUBJECT_OU%"=="" goto missing
goto startGenerate

rem ---------------------------------------------
:missing
echo.
echo Missing environment variables or input parameters.
echo Please run application from command "xvhost"
exit /B 1

rem ---------------------------------------------
:occuredError
echo.
echo The certificate generation process has been occurred error.
echo Cancel the action.
exit /B 1

rem ---------------------------------------------
:startGenerate
if exist "%XVHM_TMP_DIR%" del /Q "%XVHM_TMP_DIR%\."
if not exist "%XVHM_TMP_DIR%" mkdir "%XVHM_TMP_DIR%"
if not exist "%XVHM_CACERT_DIR%" mkdir "%XVHM_CACERT_DIR%"

echo.
echo ----------------------------------------
echo Start generate new CA certificate bundle
echo.

set subjectArgs="/CN=%XVHM_OPENSSL_SUBJECT_CN%/O=%XVHM_OPENSSL_SUBJECT_O%/OU=%XVHM_OPENSSL_SUBJECT_OU%"
set pemFile=%XVHM_TMP_DIR%\cacert.pem
set keyPemFile=%XVHM_TMP_DIR%\cacert.key.pem
set crtFile=%XVHM_TMP_DIR%\cacert.crt
set keyFile=%XVHM_TMP_DIR%\cacert.key

set OPENSSL_CONF=%XVHM_CACERT_GENERATE_CONFIG%
%XVHM_OPENSSL_BIN% req -batch -x509 -newkey rsa:2048 -sha256 -nodes -subj %subjectArgs% -days 18250 -out "%pemFile%" -outform PEM -keyout "%keyPemFile%" || goto occuredError
%XVHM_OPENSSL_BIN% x509 -outform der -in "%pemFile%" -out "%crtFile%" || goto occuredError
%XVHM_OPENSSL_BIN% rsa -in "%keyPemFile%" -out "%keyFile%" || goto occuredError

echo.
echo ----------------------------------------
echo The CA certificate bundle was generated.
echo Moving them to %XVHM_CACERT_DIR%
echo.

move /Y "%XVHM_TMP_DIR%\cacert.*" "%XVHM_CACERT_DIR%"

echo.
echo ----------------------------------------
echo Clear temporary data
echo.

set OPENSSL_CONF=
set subjectArgs=
set pemFile=
set keyPemFile=
set crtFile=
set keyFile=
del /Q "%XVHM_TMP_DIR%\."

echo.
echo ----------------------------------------
echo The certificate generation process is complete.

endlocal