#!/bin/sh
# init nginx config
if [ ! -f /etc/nginx/conf.d/oj-$OJ_NAME.conf ]; then
    cp -rf /nginx_conf/oj.conf /etc/nginx/conf.d/oj-$OJ_NAME.conf
    sed -i "s#public/csgoj/upload/#public/$BELONG_TO/upload/#g" /etc/nginx/conf.d/oj-$OJ_NAME.conf
    sed -i "s#csgoj#$OJ_NAME#g"                                 /etc/nginx/conf.d/oj-$OJ_NAME.conf
    sed -i "s#fastcgi_pass.*:#fastcgi_pass php-$OJ_NAME:#g"     /etc/nginx/conf.d/oj-$OJ_NAME.conf
    sed -i "s#listen.*;#listen $PORT_OJ;#g"                     /etc/nginx/conf.d/oj-$OJ_NAME.conf
    chmod 744 /etc/nginx/conf.d/oj-$OJ_NAME.conf
fi
# init db
php /ojweb/dbinit.php
#init ojweb
PATH_OJWEB_BASE=/ojweb
if [ ! -e /var/www/baseoj/public/static ]; then
    mkdir -p /var/www/baseoj/public/static
    cp -ruf $PATH_OJWEB_BASE/public/static/* /var/www/baseoj/public/static/
elif [ "$OJ_UPDATE_STATIC" = "1" ]; then
    cp -ruf $PATH_OJWEB_BASE/public/static/* /var/www/baseoj/public/static/
fi
# init env 文件不存在或为空时写入配置
mkdir -p /var/www/$OJ_NAME
chown www-data:www-data /var/www/$OJ_NAME
chmod 777 /var/www/$OJ_NAME
if [ ! -f "/var/www/$OJ_NAME/.env" ] || [ ! -s "/var/www/$OJ_NAME/.env" ]; then
    echo "app_debug = false" > /var/www/$OJ_NAME/.env
    echo "OJ_SESSION=$OJ_SESSION"   >> /var/www/$OJ_NAME/.env
    echo "OJ_NAME=$(echo $OJ_NAME | tr '[:lower:]' '[:upper:]')" >> /var/www/$OJ_NAME/.env
    echo "OJ_CDN=$OJ_CDN"           >> /var/www/$OJ_NAME/.env
    echo "OJ_MODE=$OJ_MODE"         >> /var/www/$OJ_NAME/.env
    echo "OJ_STATIC=$OJ_STATIC"     >> /var/www/$OJ_NAME/.env
    echo "DB_HOSTNAME=$DB_HOSTNAME" >> /var/www/$OJ_NAME/.env
    echo "DB_DATABASE=$DB_DATABASE" >> /var/www/$OJ_NAME/.env
    echo "DB_USERNAME=$DB_USERNAME" >> /var/www/$OJ_NAME/.env
    echo "DB_PASSWORD=$DB_PASSWORD" >> /var/www/$OJ_NAME/.env
    echo "DB_HOSTPORT=$DB_HOSTPORT" >> /var/www/$OJ_NAME/.env
fi
if [ -f "$PATH_OJWEB_BASE/.env" ] || [ -L "$PATH_OJWEB_BASE/.env" ]; then
    rm "$PATH_OJWEB_BASE/.env"
fi
ln -s /var/www/$OJ_NAME/.env $PATH_OJWEB_BASE/.env
chmod 777 /var/www/$OJ_NAME/.env

chown www-data:www-data $PATH_OJWEB_BASE
chmod 777 $PATH_OJWEB_BASE

# init upload public
mkdir -p /var/www/public
chown www-data:www-data /var/www/public
chmod 777 -R /var/www/public

# init judge data folder
mkdir -p /home/judge/data
chown www-data:www-data /home/judge/data
chmod 777 -R /home/judge/data

exec docker-php-entrypoint "$@"
