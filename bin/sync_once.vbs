' ===============================================================
' sync_once.vbs - Lanzador sin ventana para el Programador de tareas
' ===============================================================
' Ejecutar php.exe directamente desde una tarea hace parpadear una
' consola cada pocos minutos. Este shim la oculta.
'
' El tercer argumento de Run (True) hace que espere al proceso, para
' que el Programador vea la duracion real y su propia proteccion
' contra instancias multiples funcione. El codigo de salida de PHP se
' propaga tal cual, asi que "Ultimo resultado" en el Programador
' significa lo mismo que en bin/sync_once.php.
'
' Archivo en ASCII puro a proposito: wscript lee .vbs como ANSI y
' cualquier caracter no-ASCII se corrompe.
' ===============================================================

Option Explicit

Dim fso, sh, binDir, php, script, comando, rc

Set fso = CreateObject("Scripting.FileSystemObject")
Set sh  = CreateObject("WScript.Shell")

binDir = fso.GetParentFolderName(WScript.ScriptFullName)
script = fso.BuildPath(binDir, "sync_once.php")
php    = "C:\AppServ\php7\php.exe"

If Not fso.FileExists(php) Then
    WScript.Quit 3
End If

If Not fso.FileExists(script) Then
    WScript.Quit 3
End If

' -d display_errors=0 silencia los warnings de arranque de php.ini
' (lineas extension= apuntando a DLLs de sqlsrv que no existen)
comando = """" & php & """ -d display_errors=0 """ & script & """"

rc = sh.Run(comando, 0, True)

WScript.Quit rc
