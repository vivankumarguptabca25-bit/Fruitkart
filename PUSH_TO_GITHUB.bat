@echo off
title FruitKart - Push to GitHub
color 0B
echo ====================================================================
echo             FRUITKART - PUSH TO GITHUB REPOSITORY
echo ====================================================================
echo.
echo Target Repository: https://github.com/vivankumarguptabca25-bit/Fruitkart
echo.
echo Pushing code to main branch...
echo (Agar browser login window open ho, toh kripya "Sign in with your browser" par click karein)
echo.

set "PATH=%LOCALAPPDATA%\Programs\Git\cmd;%PATH%"

git push -u origin main

if errorlevel 1 (
    echo.
    echo ====================================================================
    echo [ERROR] Push fail ho gaya. Kripya GitHub login check karein.
    echo ====================================================================
) else (
    echo.
    echo ====================================================================
    echo [SUCCESS] Website code successfully pushed to GitHub!
    echo Check karein: https://github.com/vivankumarguptabca25-bit/Fruitkart
    echo ====================================================================
)

echo.
pause
