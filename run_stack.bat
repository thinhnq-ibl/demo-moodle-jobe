@echo off
chcp 65001 >nul
echo ==========================================================
echo  KHOI CHAY HE THONG MOODLE + JOBE PORTABLE STACK (WINDOWS)
echo ==========================================================

docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [Loi] Docker chua duoc cai dat hoac Docker Desktop chua duoc bat!
    echo Vui long mo Docker Desktop va thu lai.
    pause
    exit /b 1
)

docker image inspect moodle-portable:latest >nul 2>&1
if %errorlevel% neq 0 (
    if exist moodle_stack_images.tar.gz (
        echo [1/2] Dang nap Docker Images tu moodle_stack_images.tar.gz (co the mat vai phut)...
        docker load -i moodle_stack_images.tar.gz
    ) else if exist moodle-portable.tar.gz (
        echo [1/2] Dang nap tung Docker Image...
        docker load -i moodle-portable.tar.gz
        docker load -i mariadb-portable.tar.gz
        docker load -i jobe-portable.tar.gz
    ) else (
        echo [Loi] Khong tim thay file image *.tar.gz trong thu muc!
        pause
        exit /b 1
    )
) else (
    echo Docker images da san sang.
)

echo [2/2] Dang khoi dong cac container...
docker compose -f docker-compose.portable.yml up -d

echo.
echo ==========================================================
echo HE THONG DA KHOI CHAY THANH CONG!
echo Moodle LMS: http://localhost:8080
echo Tai khoan:  admin
echo Mat khau:   AdminPassword123!
echo Dashboard:  http://localhost:8080/testcase_dashboard.php
echo ==========================================================
pause
