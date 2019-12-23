Set objShell     = CreateObject("WScript.Shell")
Set objSystemEnv = objShell.Environment("SYSTEM")
appStarted       = objShell.ExpandEnvironmentStrings("%XVHM_APP_STARTED%")
appDir           = objShell.ExpandEnvironmentStrings("%XVHM_APP_DIR%")

If ((appStarted = "true") and (not appDir = "%XVHM_APP_DIR%")) Then
    currentSystemPath = objSystemEnv("PATH")

    If (InStr(currentSystemPath, appDir & ";") = 0) and (InStr(currentSystemPath, ";" & appDir) = 0) Then
        objSystemEnv("PATH") = appDir & ";" & currentSystemPath
    End If
Else
    WScript.Echo "This script does not accept running as a standalone application." & _
        vbNewLine & "Please run application from command ""xvhosts"""
End If

Set objSystemEnv = Nothing
Set objShell     = Nothing