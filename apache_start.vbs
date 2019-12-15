Set objShell = CreateObject("WScript.Shell")
appStarted   = objShell.ExpandEnvironmentStrings("%XVHM_APP_STARTED%")
xamppDir     = objShell.ExpandEnvironmentStrings("%XVHM_XAMPP_DIR%")

If ((appStarted = "true") and (not xamppDir = "%XVHM_XAMPP_DIR%")) Then
    objShell.Run """" & xamppDir & "\apache_start.bat""", 0, false
Else
    WScript.Echo "This script does not accept running as a standalone application." & _
        vbNewLine & "Please run application from command ""xvhosts"""
End If

Set objShell = Nothing