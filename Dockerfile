FROM moodlehq/moodle-php-apache:8.2

# Cài đặt các công cụ cần thiết
RUN apt-get update && apt-get install -y git curl tar unzip && rm -rf /var/lib/apt/lists/*

# Tải mã nguồn Moodle 4.4 Stable
RUN curl -sL "https://packaging.moodle.org/stable404/moodle-latest-404.tgz" | tar -xz -C /var/www/ \
    && rm -rf /var/www/html \
    && mv /var/www/moodle /var/www/html

# Tải plugin CodeRunner và Question Behaviour cho CodeRunner
RUN git clone --depth 1 -b master https://github.com/trampgeek/moodle-qtype_coderunner.git /var/www/html/question/type/coderunner \
    && git clone --depth 1 -b master https://github.com/trampgeek/moodle-qbehaviour_adaptive_adapted_for_coderunner.git /var/www/html/question/behaviour/adaptive_adapted_for_coderunner

# Tạo thư mục moodledata và phân quyền
RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata /var/www/html \
    && chmod -R 777 /var/www/moodledata

# Cấu hình PHP tối ưu cho Moodle
RUN { \
        echo "max_input_vars = 5000"; \
        echo "upload_max_filesize = 128M"; \
        echo "post_max_size = 128M"; \
        echo "memory_limit = 512M"; \
        echo "max_execution_time = 300"; \
    } > /usr/local/etc/php/conf.d/moodle.ini

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
