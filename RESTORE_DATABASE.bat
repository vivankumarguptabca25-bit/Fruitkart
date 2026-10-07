@echo off
title FruitKart - 1-Click Database Restore
color 0A
echo ====================================================================
echo             FRUITKART - 1-CLICK AUTOMATIC DATABASE RESTORE
echo ====================================================================
echo.
echo Pehle database check kiya ja raha hai...
if exist "C:\xampp\mysql\bin\mysql.exe" (
    echo [OK] XAMPP MySQL binary found at C:\xampp\mysql\bin\mysql.exe
) else (
    echo [ERROR] C:\xampp\mysql\bin\mysql.exe nahi mila. Kripya XAMPP check karein.
    pause
    exit /b
)

echo.
echo Database create & restore ho raha hai...
"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS fruit_shopping_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if exist "database\full_database_backup.sql" (
    "C:\xampp\mysql\bin\mysql.exe" -u root fruit_shopping_db < "database\full_database_backup.sql"
    echo.
    echo ====================================================================
    echo [SUCCESS] Database successfully restored from full_database_backup.sql!
    echo Saare tables, users, products, categories, orders wapas restore ho gaye!
    echo ====================================================================
) else if exist "database\schema.sql" (
    "C:\xampp\mysql\bin\mysql.exe" -u root fruit_shopping_db < "database\schema.sql"
    echo.
    echo ====================================================================
    echo [SUCCESS] Database successfully restored from schema.sql!
    echo ====================================================================
) else (
    echo [ERROR] SQL backup file nahi mili.
)

echo.
pause
