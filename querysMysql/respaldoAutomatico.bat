@echo off
:: Configuración
set FECHA=%DATE:~6,4%-%DATE:~3,2%-%DATE:~0,2%_%TIME:~0,2%-%TIME:~3,2%
set FECHA=%FECHA: =0%
set DIR_RESPALDOS=C:\Users\1511r\Documents\dumps
set USUARIO=root
set CLAVE=qwe123
set BASEDATOS=tienda
set RUTA_MYSQL=C:\Program Files\MySQL\MySQL Server 9.3\bin

:: Crear carpeta de respaldos si no existe
if not exist "%DIR_RESPALDOS%" mkdir "%DIR_RESPALDOS%"

:: Ejecutar el respaldo
"%RUTA_MYSQL%\mysqldump.exe" -u%USUARIO% -p%CLAVE% --databases %BASEDATOS% > "%DIR_RESPALDOS%\respaldo_%BASEDATOS%_%FECHA%.sql"

:: Mensaje opcional
echo Respaldo de %BASEDATOS% creado en %DIR_RESPALDOS%
