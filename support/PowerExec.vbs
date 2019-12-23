Option Explicit

' Define global variables
Dim objArgs: Set objArgs = WScript.Arguments
Dim objShell: Set objShell = CreateObject("WScript.Shell")
Dim objFSO: Set objFSO = CreateObject("Scripting.FileSystemObject")
Dim scriptBaseName: scriptBaseName = objFSO.GetBaseName(WScript.ScriptName)
Dim scriptExt: scriptExt = LCase(objFSO.GetExtensionName(WScript.ScriptName))
Dim noOutput: noOutput = false

' Define subroutines
Private Sub ShowHelp()
    Dim callMethod

    If (scriptExt = "vbs") Then
        callMethod = "cscript " & WScript.ScriptName
    Else
        callMethod = scriptBaseName
    End If

    Dim helpMsg

    helpMsg = ""  & _
        vbNewLine & "Usage:" & _
        vbNewLine & "  " & callMethod & " [-k] [-p] [-w] [-e] [(-m | -a)] [-i] [-n] [-h] <command>" & _
        vbNewLine & _
        vbNewLine & "Options:" & _
        vbNewLine & "  -k  Keep the command prompt window after execution; equivalent to ""cmd /k command""." & _
        vbNewLine & "  -p  Change working directory to the current one before executing the command." & _
        vbNewLine & "  -w  Wait until the execution is terminated and the execution window is closed." & _
        vbNewLine & "  -e  Execute command with elevated permission (run as Administrator)." & _
        vbNewLine & "  -m  Minimize command execution window (visible mode)." & _
        vbNewLine & "  -a  Maximize command execution window (visible mode)." & _
        vbNewLine & "  -i  Hide command execution window (invisible mode)." & _
        vbNewLine & "  -n  Do not present " & scriptBaseName & "'s messages (error or success) except the help." & _
        vbNewLine & "  -h  Display " & scriptBaseName & "'s help message." & _
        vbNewLine & _
        vbNewLine & "Notes:" & _
        vbNewLine & "  - The [-k] parameter will not work if the application will be executed" & _
        vbNewLine & "    actively closing the window." & _
        vbNewLine & "  - Cannot use the [-m] and [-a] parameters at the same time." & _
        vbNewLine & "  - Using the parameter [-i] will omit the parameters [-m] and [-a]." & _
        vbNewLine & "  - Using the [-w] and [-i] parameters together requires the assurance of" & _
        vbNewLine & "    completion of the application will be executed. Be careful when using." & _
        vbNewLine

    WScript.Echo helpMsg
End Sub

Private Sub ShowHelpWithMessage(ByVal message)
    If ((Not message = vbNullString) and (Not message = vbNull)) Then
        WScript.Echo message
    End If

    ShowHelp
End Sub

Private Sub Quit(errorCode)
    Set objFSO   = Nothing
    Set objShell = Nothing
    Set objArgs  = Nothing
    WScript.Quit errorCode
End Sub

' Define functions
Private Function ParseArguments(ByVal arguments)
    Dim isElevateOption: isElevateOption = true
    Dim objParams: Set objParams = CreateObject("Scripting.Dictionary")
    objParams.CompareMode = vbTextCompare

    objParams.Add "CmdOptions", "/C"
    objParams.Add "ShowWindow", true
    objParams.Add "WindowSize", "Normal"
    objParams.Add "WaitForFinish", false
    objParams.Add "PushdCurrDir", false
    objParams.Add "Elevated", false
    objParams.Add "NoOutput", false
    objParams.Add "Command", ""

    Dim i
    For i = 0 To arguments.Count - 1
        Dim currentArg: currentArg = arguments.item(i)
        Dim argFirstChar: argFirstChar = Left(currentArg, 1)
        Dim argRemainder: argRemainder = Mid(currentArg, 2)
        Dim lcaseCurrArg: lcaseCurrArg = LCase(currentArg)

        If (isElevateOption and (not argFirstChar = "/") and (not argFirstChar = "-")) Then
            isElevateOption = false
        End If

        If (isElevateOption) Then
            If (lcaseCurrArg = "/?" or lcaseCurrArg = "/h" or lcaseCurrArg = "/help" or lcaseCurrArg = "-h" or lcaseCurrArg = "--help") Then
                ShowHelpWithMessage "Execute commands with more advanced controls."
                Quit 0
            End If

            Select Case LCase(argRemainder)
                Case "k"
                    objParams("CmdOptions") = Replace(objParams("CmdOptions"), "/C", "/K")
                Case "p"
                    objParams("PushdCurrDir") = true
                Case "w"
                    objParams("WaitForFinish") = true
                Case "e"
                    objParams("Elevated") = true
                Case "m"
                    If (Not objParams("WindowSize") = "Normal") Then
                        ShowHelpWithMessage scriptBaseName & " error: Cannot use the [-m] and [-a] parameters at the same time. Cancel the operation."
                        Quit 1
                    End If

                    objParams("WindowSize") = "Minimized"
                Case "a"
                    If (Not objParams("WindowSize") = "Normal") Then
                        ShowHelpWithMessage scriptBaseName & " error: Cannot use the [-m] and [-a] parameters at the same time. Cancel the operation."
                        Quit 1
                    End If

                    objParams("WindowSize") = "Maximized"
                Case "i"
                    objParams("ShowWindow") = false
                Case "n"
                    objParams("NoOutput") = true
                Case Else
                    ShowHelpWithMessage scriptBaseName & " error: Unknown argument """ & currentArg & """. Cancel the operation."
                    Quit 1
            End Select
        Else
            If (InStr(currentArg, " ")) Then
                currentArg = """" & currentArg & """"
            End If

            If (objParams("Command") = "") Then
                objParams("Command") = currentArg
            Else
                objParams("Command") = objParams("Command") & " " & currentArg
            End If
        End If
    Next

    Set ParseArguments = objParams
End Function

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

Private Function RandomString()
    Randomize()

    Dim CharacterSetArray
    CharacterSetArray = Array(_
        Array(7, "abcdefghijklmnopqrstuvwxyz"), _
        Array(1, "0123456789") _
    )

    Dim i
    Dim j
    Dim Count
    Dim Chars
    Dim Index
    Dim Temp

    For i = 0 to UBound(CharacterSetArray)
        Count = CharacterSetArray(i)(0)
        Chars = CharacterSetArray(i)(1)

        For j = 1 to Count
            Index = Int(Rnd() * Len(Chars)) + 1
            Temp = Temp & Mid(Chars, Index, 1)
        Next
    Next

    Dim TempCopy

    Do Until Len(Temp) = 0
        Index = Int(Rnd() * Len(Temp)) + 1
        TempCopy = TempCopy & Mid(Temp, Index, 1)
        Temp = Mid(Temp, 1, Index - 1) & Mid(Temp, Index + 1)
    Loop

    RandomString = TempCopy
End Function

' Main program
If (DetectScriptMode() = "Window") Then
    WScript.Echo "Sorry! This application only runs in console mode." & vbNewLine & _
        "Please run this application with the ""cscript"" command."
    Quit 1
End If

If (objArgs.Count > 0) Then
    Dim objParams: Set objParams = ParseArguments(objArgs)

    If (Not objParams("Command") = "") Then
        noOutput = objParams("NoOutput")

        If (objParams("PushdCurrDir")) Then
            objParams("Command") = "pushd """ & objShell.CurrentDirectory & """" & " & " & objParams("Command")
        End If

        Dim tempFolder: tempFolder = objShell.ExpandEnvironmentStrings("%TEMP%")
        Dim commandErrorLog: commandErrorLog = tempFolder & "\" & LCase(scriptBaseName) & "-command-error-" & RandomString() & ".log"

        objParams("Command") = objParams("Command") & " 2>""" & commandErrorLog & """"
        objParams("Command") = Replace(objParams("Command"), """", "\""")

        Dim powerShellWait
        If (objParams("WaitForFinish")) Then
            powerShellWait = " -Wait"
        Else
            powerShellWait = ""
        End If

        Dim powerShellWindow
        If (objParams("ShowWindow")) Then
            powerShellWindow = " -WindowStyle " & objParams("WindowSize")
        Else
            powerShellWindow = " -WindowStyle Hidden"
        End If

        Dim powerShellRunAs
        If (objParams("Elevated")) Then
            powerShellRunAs = " -Verb RunAs"
        Else
            powerShellRunAs = ""
        End If

        Dim powerShellCommand
        powerShellCommand = "powershell -Command ""Start-Process cmd -ArgumentList {""" & objParams("CmdOptions") & " \""" & objParams("Command") & "\""""}" & _
                            powerShellRunAs & powerShellWait & powerShellWindow & """"

        Set objParams = Nothing

        ' Method 1: Use Run function if we do not need to store the process ID
        Dim exitCode: exitCode = objShell.Run(powerShellCommand, 0, true)

        If (exitCode = 1) Then
            If (Not noOutput) Then
                WScript.Echo "The operation was canceled by the user."
            End If

            Quit 1
        End If

        ' Method 2: Use Exec function if want to store process ID
        ' Dim objExec: Set objExec = objShell.Exec(powerShellCommand)
        ' Dim processID: processID = objExec.ProcessID

        ' While (objExec.Status = 0)
        '     WScript.Sleep 50

        '     If (objExec.ExitCode = 1) Then
        '         If (Not noOutput) Then
        '            WScript.Echo "The operation was canceled by the user."
        '         End If

        '         Set objExec = Nothing
        '         Quit 1
        '     End If
        ' Wend

        ' Set objExec = Nothing

        ' Check the command execution
        Dim openFile: Set openFile = objFSO.OpenTextFile(commandErrorLog, 1, true)
        Dim errorContent

        If Not openFile.AtEndOfStream Then
            ' errorContent = openFile.ReadAll()
            errorContent = errorContent & openFile.ReadAll()
        Else
            errorContent = ""
        End If

        Set openFile = Nothing
        objFSO.DeleteFile(commandErrorLog)

        If (Not errorContent = "") Then
            If (Not noOutput) Then
                WScript.Echo "The execution of the command occurred an error:"
                WScript.Echo errorContent
            End If

            Quit 1
        End If

        ' Finish command excution
        If (Not noOutput) Then
            WScript.Echo "The execution of the command has been completed successfully."
        End If
    Else
        ShowHelpWithMessage scriptBaseName & " error: Missing arguments."
        Quit 1
    End If
Else
    ShowHelpWithMessage scriptBaseName & " error: Missing arguments."
    Quit 1
End If

' Clear global objects
Set objFSO   = Nothing
Set objShell = Nothing
Set objArgs  = Nothing