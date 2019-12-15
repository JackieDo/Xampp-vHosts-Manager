Set objArgs  = WScript.Arguments
Set objShell = CreateObject("WScript.Shell")
appStarted   = objShell.ExpandEnvironmentStrings("%XVHM_APP_STARTED%")
batchProcess = objShell.ExpandEnvironmentStrings("%XVHM_VHOST_CERT_GENERATE_BATCH_PROCESS%")

If ((appStarted = "true") and (not batchProcess = "%XVHM_VHOST_CERT_GENERATE_BATCH_PROCESS%") and (objArgs.Count >= 1)) Then
    hostName  = objArgs.item(0)

    ' objShell.Run "pathToBatchFile hostName storeCertDir storeCertKeyDir", 0, true
    objShell.Run """" & batchProcess & """ """ & hostName & """", 0, true
Else
    WScript.Echo "This script does not accept running as a standalone application." & _
        vbNewLine & "Please run application from command ""xvhosts"""
End If

Set objArgs  = Nothing
Set objShell = Nothing