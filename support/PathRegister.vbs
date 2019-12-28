Option Explicit

' Define global variables
Dim objArgs: Set objArgs = WScript.Arguments
Dim objShell: Set objShell = CreateObject("WScript.Shell")
Dim objSystemEnv: Set objSystemEnv = objShell.Environment("SYSTEM")

' Define subroutines
Private Sub ClearVars()
    Set objSystemEnv = Nothing
    Set objShell     = Nothing
    Set objArgs      = Nothing
End Sub

Private Sub WriteMsg(ByVal message, ByVal writeAs)
    If (DetectScriptMode = "Window") Then
        MsgBox message
    Else
        Select Case writeAs
            Case "output"
                WScript.StdOut.WriteLine message
            Case "error"
                WScript.StdErr.WriteLine message
            Case "echo"
                WScript.Echo message
        End Select
    End If
End Sub

Private Sub Quit(errorCode)
    ClearVars
    WScript.Quit errorCode
End Sub

' Define functions
Private Function DetectScriptMode()
    Dim scriptMode

    If InStr(1, WScript.FullName, "cscript", vbTextCompare) Then
        scriptMode = "Console"
    ElseIf InStr(1, WScript.FullName, "wscript", vbTextCompare) Then
        scriptMode = "Window"
    Else
        scriptMode = "Unknown"
    End If

    DetectScriptMode = scriptMode
End Function

' Check Elevated permission
Dim objExec: Set objExec = objShell.Exec("cmd /C ""FSUTIL dirty query %SystemDrive%>nul""")
Do While (objExec.Status = 0)
    WScript.Sleep 50
Loop
If (objExec.ExitCode = 1) Then
    WriteMsg "This process can only be run with elevated permission.", "error"
    WriteMsg "Please run this process in Administartor mode.", "error"
    Set objExec = Nothing
    Quit 1
End If
Set objExec = Nothing

' The path argument is missing
If (objArgs.Count <= 0) Then
    WriteMsg "The ""path"" argument is missing.", "error"
    Quit 1
End If

' Start process
Dim currentPaths: currentPaths = objSystemEnv("PATH")
Dim path: path = Trim(objArgs.item(0))

If (InStr(currentPaths, path & ";") = 0) and (InStr(currentPaths, ";" & path) = 0) Then
    objSystemEnv("PATH") = path & ";" & currentPaths
End If

WriteMsg "The path has been added into Windows Path Environment Variable", "output"
Quit 0