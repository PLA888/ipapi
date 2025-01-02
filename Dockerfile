FROM php:8.0-apache

# 安装必要的包和PHP扩展
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    default-mysql-server \
    && docker-php-ext-install zip mysqli pdo_mysql

# 启用 Apache 模块
RUN a2enmod rewrite

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件
COPY . /var/www/html/

# 创建启动脚本
RUN echo '#!/bin/bash\n\
# 启动 MySQL\n\
service mysql start\n\
\n\
# 等待 MySQL 启动\n\
while ! mysqladmin ping --silent; do\n\
    sleep 1\n\
done\n\
\n\
# 初始化数据库\n\
mysql -e "CREATE DATABASE IF NOT EXISTS ipapi;"\n\
mysql -e "CREATE USER IF NOT EXISTS '\''ipapi'\''@'\''localhost'\'' IDENTIFIED BY '\''FNsJJWhwijx5azke'\'';\n\
         GRANT ALL PRIVILEGES ON ipapi.* TO '\''ipapi'\''@'\''localhost'\'';\n\
         FLUSH PRIVILEGES;"\n\
\n\
# 导入数据库结构\n\
mysql -u ipapi -pFNsJJWhwijx5azke ipapi < /var/www/html/sql/create_table.sql\n\
\n\
# 启动 Apache\n\
apache2-foreground' > /usr/local/bin/startup.sh

# 设置脚本权限
RUN chmod +x /usr/local/bin/startup.sh

# 设置目录权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 配置 Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 修改 config.php 中的数据库连接信息
RUN sed -i 's/getenv(.*):".*"/localhost:3306/g' /var/www/html/config.php

# 暴露端口
EXPOSE 80

# 启动服务
CMD ["/usr/local/bin/startup.sh"] 