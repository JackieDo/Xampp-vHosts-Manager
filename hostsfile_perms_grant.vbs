Set objShell = CreateObject("WScript.Shell")
systemRoot   = objShell.ExpandEnvironmentStrings("%SystemRoot%")
appStarted   = objShell.ExpandEnvironmentStrings("%XVHM_APP_STARTED%")

If (appStarted = "true") Then
    Set objApplication = CreateObject("Shell.Application")
    objApplication.ShellExecute "cmd", "/C icacls """ & systemRoot & "\System32\drivers\etc\hosts"" /grant Users:MRXRW", "", "runas", 0
    Set objApplication = Nothing
Else
    WScript.Echo "This script does not accept running as a standalone application." & _
        vbNewLine & "Please run application from command ""xvhosts"""
End If

Set objShell = Nothing