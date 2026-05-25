@echo off
chcp 65001 >nul
title D'Salud - Sistema de Ventas e Inventario
cd /d "%~dp0"

echo ============================================================
echo            SISTEMA D'SALUD - INICIANDO
echo ============================================================
echo.
echo  IMPORTANTE: Antes de continuar, asegurate de tener
echo  Laragon ENCENDIDO (para la base de datos MySQL).
echo.
echo  Se abriran dos ventanas negras. NO las cierres mientras
echo  uses el sistema.
echo.
pause

echo.
echo  Iniciando el servidor de la aplicacion...
start "D'Salud - Servidor (no cerrar)" cmd /k "php artisan serve"

echo  Iniciando los recursos visuales...
start "D'Salud - Recursos (no cerrar)" cmd /k "npm run dev"

echo.
echo  Esperando unos segundos a que todo arranque...
timeout /t 7 >nul

echo  Abriendo el sistema en tu navegador...
start "" http://localhost:8000

echo.
echo ============================================================
echo  LISTO. El sistema se abrio en: http://localhost:8000
echo.
echo  Para APAGAR el sistema: cerra las dos ventanas negras
echo  que se abrieron.
echo ============================================================
echo.
pause
