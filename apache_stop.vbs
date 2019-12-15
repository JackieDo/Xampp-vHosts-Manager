Set objShell = CreateObject("WScript.Shell")
appStarted   = objShell.ExpandEnvironmentStrings("%XVHM_APP_STARTED%")
xamppDir     = objShell.ExpandEnvironmentStrings("%XVHM_XAMPP_DIR%")
apacheDir    = objShell.ExpandEnvironmentStrings("%XVHM_APACHE_DIR%")

If ((appStarted = "true") and (not xamppDir = "%XVHM_XAMPP_DIR%")) Then
    ' Method 1
    ' objShell.Run "taskkill /IM httpd.exe /F", 0, true
    ' objShell.Run "cmd /C del /Q """ & apacheDir & "\logs\httpd.pid""", 0, true

    ' Method 2
    objShell.Run """" & xamppDir & "\apache_stop.bat""", 0, true
Else
    WScript.Echo "This script does not accept running as a standalone application." & _
        vbNewLine & "Please run application from command ""xvhosts"""
End If

Set objShell = Nothing