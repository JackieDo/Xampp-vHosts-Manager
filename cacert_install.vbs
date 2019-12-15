Set objArgs  = WScript.Arguments
Set objShell = CreateObject("WScript.Shell")
appStarted   = objShell.ExpandEnvironmentStrings("%XVHM_APP_STARTED%")

If (appStarted = "true" and objArgs.Count >= 1) Then
    Set objApp = CreateObject("Shell.Application")
    objApp.ShellExecute "cmd", "/C CERTUTIL -addstore -enterprise -f -v ""Root"" """ & objArgs.item(0) & """", "", "runas", 0
    Set objApp = Nothing
Else
    WScript.Echo "This script does not accept running as a standalone application." & _
        vbNewLine & "Please run application from command ""xvhosts"""
End If

Set objArgs  = Nothing
Set objShell = Nothing