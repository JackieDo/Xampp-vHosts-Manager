@echo off
cls
setlocal EnableExtensions

:: Check necessary data
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
exit /B 1

:occuredError
echo.
echo The certificate generation process has been occurred error.
echo Cancel the action.
exit /B 1

:startGenerate
if exist "%XVHM_TMP_DIR%" del /Q "%XVHM_TMP_DIR%\."
if not exist "%XVHM_TMP_DIR%" mkdir "%XVHM_TMP_DIR%"
if not exist "%XVHM_CACERT_DIR%" mkdir "%XVHM_CACERT_DIR%"

if exist "%XVHM_CACERT_DIR%\cacert.crt" (
    if exist "%XVHM_CACERT_DIR%\cacert.name" (
        for /F "tokens=* USEBACKQ" %%v in ("%XVHM_CACERT_DIR%\cacert.name") do (
            if "%XVHM_OPENSSL_SUBJECT_CN%"=="%%v" (
                echo.
                echo The CA certificate bundle exists. Skip the new generation.
                exit /B
            )
        )
    )
)

echo.
echo ----------------------------------------
echo Start generate new CA certificate bundle
echo.

set subjectArgs="/CN=%XVHM_OPENSSL_SUBJECT_CN%/O=%XVHM_OPENSSL_SUBJECT_O%/OU=%XVHM_OPENSSL_SUBJECT_OU%"
set pemFile=%XVHM_TMP_DIR%\cacert.pem
set keyPemFile=%XVHM_TMP_DIR%\cacert.key.pem
set crtFile=%XVHM_TMP_DIR%\cacert.crt
set keyFile=%XVHM_TMP_DIR%\cacert.key
set nameFile=%XVHM_TMP_DIR%\cacert.name

set OPENSSL_CONF=%XVHM_CACERT_GENERATE_CONFIG%
%XVHM_OPENSSL_BIN% req -batch -x509 -newkey rsa:2048 -sha256 -nodes -subj %subjectArgs% -days 18250 -out "%pemFile%" -outform PEM -keyout "%keyPemFile%" || goto occuredError
%XVHM_OPENSSL_BIN% x509 -outform der -in "%pemFile%" -out "%crtFile%" || goto occuredError
%XVHM_OPENSSL_BIN% rsa -in "%keyPemFile%" -out "%keyFile%" || goto occuredError

if not exist "%nameFile%" type nul > "%nameFile%"
echo|set /p="%XVHM_OPENSSL_SUBJECT_CN%" > "%nameFile%"

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
set nameFile=
del /Q "%XVHM_TMP_DIR%\."

echo.
echo ----------------------------------------
echo The certificate generation process is complete.

endlocal