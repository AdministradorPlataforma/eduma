@echo off
REM Arranca el worker de cola de EDUMA desde la raíz del proyecto.
SET SCRIPT_DIR=%~dp0
SET PHP=php
PUSHD %SCRIPT_DIR%
%PHP% scripts/sync_worker.php --daemon --sleep=5
POPD
