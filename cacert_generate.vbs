Set objArgs  = WScript.Arguments
Set objShell = CreateObject("WScript.Shell")
appStarted   = objShell.ExpandEnvironmentStrings("%XVHM_APP_STARTED%")
batchProcess = objShell.ExpandEnvironmentStrings("%XVHM_CACERT_GENERATE_BATCH_PROCESS%")

If ((appStarted = "true") and (not batchProcess = "%XVHM_CACERT_GENERATE_BATCH_PROCESS%")) Then
    ' objShell.Run "pathToBatchFile", 0, true
    objShell.Run """" & batchProcess & """", 0, true
Else
    WScript.Echo "This script does not accept running as a standalone application." & _
        vbNewLine & "Please run application from command ""xvhosts"""
End If

Set objArgs  = Nothing
Set objShell = Nothing