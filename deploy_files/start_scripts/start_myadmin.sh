#!/bin/bash
# bash start_myadmin.sh --WEB_PORT_START=20050
source parse_args.sh
parse_args "$@"

echo "##################################################"
echo "Initing myadmin"
if [ "$(docker ps -aq -f name=/myadmin$)" ]; then
    echo "myadmin ready"
else
    mkdir -p $PATH_DATA/nginx/attach/
    mkdir -p $PATH_DATA/nginx/nginx_conf.d
    echo "admin:$(openssl passwd -apr1 $PASS_MYADMIN_PAGE)" > $PATH_DATA/nginx/attach/pass
    echo 'server {
        listen '"$PORT_MYADMIN"';
        location / {
                proxy_redirect          off;
                proxy_set_header        Host      $http_host;
                proxy_set_header        X-Real-IP $remote_addr;
                proxy_set_header        X-Forwarded-For $proxy_add_x_forwarded_for;
                proxy_pass              http://myadmin;
                auth_basic              "验证后访问";
                auth_basic_user_file    "/etc/nginx/attach/pass";
        }
        access_log /var/log/nginx/myadmin_access.log;
    }' > $PATH_DATA/nginx/nginx_conf.d/myadmin.conf

    docker run -dit $LINK_LOCAL \
        -e PMA_HOST=$SQL_HOST \
        -e UPLOAD_LIMIT=128M \
        --name myadmin \
        --restart=unless-stopped \
        -d phpmyadmin:5.2.1
    echo "myadmin inited"
fi

