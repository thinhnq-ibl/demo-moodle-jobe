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

if not exist .loaded_image_hash (
    set NEED_LOAD=1
) else (
    docker image inspect moodle-portable:latest >nul 2>&1
    if %errorlevel% neq 0 (
        set NEED_LOAD=1
    ) else (
        set NEED_LOAD=0
    )
)

if "%NEED_LOAD%"=="1" (
    if exist moodle_stack_images.tar.gz (
        echo [1/3] Dang nap/cap nhat Docker Images tu moodle_stack_images.tar.gz...
        docker load -i moodle_stack_images.tar.gz
        echo loaded > .loaded_image_hash
    ) else if exist moodle-portable.tar.gz (
        echo [1/3] Dang nap tung Docker Image...
        docker load -i moodle-portable.tar.gz
        if exist mariadb-portable.tar.gz docker load -i mariadb-portable.tar.gz
        if exist jobe-portable.tar.gz docker load -i jobe-portable.tar.gz
        echo loaded > .loaded_image_hash
    ) else (
        echo [Canh bao] Khong tim thay file image *.tar.gz, kiem tra cac image san co...
    )
) else (
    echo [1/3] Docker images chinh da san sang.
)

rem Kiem tra Jobe sandbox image
docker image inspect jobe-portable:latest >nul 2>&1
if %errorlevel% neq 0 (
    docker image inspect trampgeek/jobeinabox:latest >nul 2>&1
    if %errorlevel% equ 0 (
        echo [2/3] Gan nhan jobe-portable:latest tu trampgeek/jobeinabox:latest...
        docker tag trampgeek/jobeinabox:latest jobe-portable:latest
    ) else if exist jobe-portable.tar.gz (
        echo [2/3] Dang nap jobe-portable.tar.gz...
        docker load -i jobe-portable.tar.gz
    ) else (
        echo [2/3] Dang tai Jobe sandbox tu Docker Hub (trampgeek/jobeinabox:latest)...
        docker pull trampgeek/jobeinabox:latest
        docker tag trampgeek/jobeinabox:latest jobe-portable:latest
    )
) else (
    echo [2/3] Jobe sandbox da san sang.
)

echo [3/3] Dang khoi dong cac container...
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
