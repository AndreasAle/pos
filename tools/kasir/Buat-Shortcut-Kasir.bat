@echo off
REM Membuat shortcut "Kasir POS" di Desktop: Chrome yang langsung mencetak
REM struk ke printer default Windows tanpa dialog print (--kiosk-printing).
REM
REM Pakai profil Chrome terpisah, karena flag kiosk diabaikan kalau Chrome
REM biasa sudah terbuka dengan profil yang sama. Akibatnya kasir perlu login
REM POS sekali di jendela ini.
REM
REM Sebelum dipakai: jadikan printer struk sebagai printer DEFAULT Windows
REM (Settings > Printers & scanners > matikan "Let Windows manage my default
REM printer" > pilih printer > Set as default).

setlocal
set "URL=https://pos.conweb.id/kasir"

set "CHROME=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not exist "%CHROME%" set "CHROME=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not exist "%CHROME%" set "CHROME=%LocalAppData%\Google\Chrome\Application\chrome.exe"
if not exist "%CHROME%" (
    echo Google Chrome tidak ditemukan. Pasang Chrome dulu.
    pause
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$s = (New-Object -ComObject WScript.Shell).CreateShortcut([Environment]::GetFolderPath('Desktop') + '\Kasir POS.lnk');" ^
  "$s.TargetPath = '%CHROME%';" ^
  "$s.Arguments = '--kiosk-printing --user-data-dir=\"%LocalAppData%\KasirPOS\" --app=%URL%';" ^
  "$s.IconLocation = '%CHROME%,0';" ^
  "$s.Save()"

if errorlevel 1 (
    echo Gagal membuat shortcut.
    pause
    exit /b 1
)

echo Shortcut "Kasir POS" sudah dibuat di Desktop.
echo Buka dari shortcut itu, login, lalu struk langsung tercetak tanpa dialog.
pause
