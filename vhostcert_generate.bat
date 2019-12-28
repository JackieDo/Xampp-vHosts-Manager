@echo off
cls
setlocal EnableExtensions

:: Check necessary data
if "%XVHM_APP_DIR%"=="" goto missing
if "%XVHM_TMP_DIR%"=="" goto missing
if "%XVHM_CACERT_DIR%"=="" goto missing
if "%XVHM_VHOST_CERT_DIR%"=="" goto missing
if "%XVHM_VHOST_CERT_KEY_DIR%"=="" goto missing
if "%XVHM_OPENSSL_BIN%"=="" goto missing

if "%~1"=="" goto missing
goto startGenerate

:missing
echo.
echo Missing environment variables or input parameters.
echo Please run application from command "xvhosts"
exit /B 1

:genReqError
echo.
echo The certificate request generation process has been occurred error.
echo Cancel the action.
exit /B 1

:authReqError
echo.
echo The authentication the request process to issue SSL certificate has been occurred error.
echo Cancel the action.
exit /B 1

:startGenerate
set XVHM_HOSTNAME=%~1

if not exist "%XVHM_TMP_DIR%" mkdir "%XVHM_TMP_DIR%"
if not exist "%XVHM_VHOST_CERT_DIR%" mkdir "%XVHM_VHOST_CERT_DIR%"
if not exist "%XVHM_VHOST_CERT_KEY_DIR%" mkdir "%XVHM_VHOST_CERT_KEY_DIR%"

echo.
echo ----------------------------------------
echo Generate the certificate request and security key for virtual host "%XVHM_HOSTNAME%".
echo.

set OPENSSL_CONF=%XVHM_VHOST_CERT_GENERATE_CONFIG%
%XVHM_OPENSSL_BIN% req -newkey rsa:2048 -sha256 -subj "/CN=%XVHM_HOSTNAME%" -nodes -keyout "%XVHM_TMP_DIR%\%XVHM_HOSTNAME%.key" -out "%XVHM_TMP_DIR%\%XVHM_HOSTNAME%.csr" || goto genReqError

echo.
echo ----------------------------------------
echo The certificate request and security key for virtual host "%XVHM_HOSTNAME%" has been generated.
echo Start authenticating this request and issue an SSL certificate (include its SANs).
echo.

if not exist "%XVHM_TMP_DIR%\index.txt" type nul > "%XVHM_TMP_DIR%\index.txt"
if not exist "%XVHM_TMP_DIR%\index.txt.attr" type nul > "%XVHM_TMP_DIR%\index.txt.attr"
if not exist "%XVHM_TMP_DIR%\serial.txt" type nul > "%XVHM_TMP_DIR%\serial.txt"
if not exist "%XVHM_TMP_DIR%\serial.txt.attr" type nul > "%XVHM_TMP_DIR%\serial.txt.attr"
for /F "tokens=* USEBACKQ" %%a in (`php -r "echo md5('%XVHM_HOSTNAME%');"`) do (echo %%a> "%XVHM_TMP_DIR%\serial.txt")

set OPENSSL_CONF=%XVHM_CACERT_GENERATE_CONFIG%
%XVHM_OPENSSL_BIN% ca -batch -policy signing_policy -extensions signing_req -days 3650 -out "%XVHM_TMP_DIR%\%XVHM_HOSTNAME%.cert" -infiles "%XVHM_TMP_DIR%\%XVHM_HOSTNAME%.csr" || goto authReqError

echo.
echo ----------------------------------------
echo The request has been authenticated and the SSL certificate has been issued.
echo Relocating the certificate and private key to the storage location.
echo.

move /y "%XVHM_TMP_DIR%\%XVHM_HOSTNAME%.cert" "%XVHM_VHOST_CERT_DIR%"
move /y "%XVHM_TMP_DIR%\%XVHM_HOSTNAME%.key" "%XVHM_VHOST_CERT_KEY_DIR%"

echo.
echo ----------------------------------------
echo Clear temporary data
echo.

set OPENSSL_CONF=
set XVHM_HOSTNAME=
del /Q "%XVHM_TMP_DIR%\."

echo.
echo ----------------------------------------
echo All process is complete.
echo.

endlocal