@echo off
rem Point d'entree de l'environnement local : voir scripts\dev.ps1 (.\dev pour l'aide).
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\dev.ps1" %*
