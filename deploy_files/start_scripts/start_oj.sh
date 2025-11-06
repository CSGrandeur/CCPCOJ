#!/bin/bash
# # nginx and mysql should be started ahead, and baseoj should be inited
# # PORT_OJ 需要保证 nginx-server 映射了对应端口，否则可能需要重新开 nginx-server 容器进行映射
# bash start_oj.sh \
#   --PATH_DATA=`pwd`/csgoj_data \
#   --OJ_NAME=ccpc \
#   --PORT_OJ=20080 \
#   --OJ_CDN=local \
#   --OJ_MODE=cpcsys \
#   --SQL_USER=csgcpc \
#   --SQL_HOST='db' \
#   --PASS_SQL_USER=987654321 \
#   --PORT_OJ_DB=3306 \
#   --PASS_ADMIN=987654321 \
#   --PASS_JUDGER=987654321 \
#   --OJ_UPDATE_STATIC=0

source parse_args.sh
parse_args "$@"

echo "##################################################"
echo "Initing oj"
if [ ! -d $PATH_DATA/var/www/$OJ_NAME ]; then
    mkdir -p $PATH_DATA/var/www/$OJ_NAME
fi
if [ "$(docker ps -aq -f name=/php-$OJ_NAME$)" ]; then
    echo "php-$OJ_NAME ready"
else
    WEB_MOUNT=""
    if [ "$DOCKER_PULL_NEW" = "1" ] && ([ -z "$CSGOJ_DEV" ] || [ "$CSGOJ_DEV" != "1" ]); then
        docker pull csgrandeur/ccpcoj-web2:$CSGOJ_VERSION # 先pull以确保镜像最新
    fi
    if [ "$CSGOJ_DEV" = "1" ]; then
        WEB_MOUNT="-v `pwd`/../../ojweb/application:/ojweb/application 
        -v `pwd`/../../ojweb/public:/ojweb/public  
        -v `pwd`/../../ojweb/extend:/ojweb/extend  
        -v `pwd`/../../ojweb/vendor:/ojweb/vendor  
        -v `pwd`/../../ojweb/thinkphp:/ojweb/thinkphp
        -v `pwd`/../../ojweb/entrypoint.sh:/ojweb/entrypoint.sh
        -v `pwd`/../../ojweb/dbinit.php:/ojweb/dbinit.php
        "
    fi
    docker run -dit $LINK_LOCAL \
        --name php-$OJ_NAME \
        -e DB_HOSTNAME=$SQL_HOST \
        -e DB_DATABASE=${SQL_USER}_${BELONG_TO} \
        -e DB_USERNAME=$SQL_USER \
        -e DB_PASSWORD=$PASS_SQL_USER \
        -e DB_HOSTPORT=$PORT_OJ_DB \
        -e PORT_OJ=$PORT_OJ \
        -e OJ_SESSION=$OJ_NAME \
        -e OJ_NAME=$OJ_NAME \
        -e OJ_CDN=$OJ_CDN \
        -e OJ_MODE=$OJ_MODE \
        -e OJ_STATUS=$OJ_STATUS \
        -e OJ_STATIC=/var/www/public/$BELONG_TO \
        -e PASS_ADMIN=$PASS_ADMIN \
        -e PASS_JUDGER=$PASS_JUDGER \
        -e OJ_UPDATE_STATIC=$OJ_UPDATE_STATIC \
        -e BELONG_TO=$BELONG_TO \
        -v $PATH_DATA/var/www:/var/www $WEB_MOUNT \
        -v $PATH_DATA/var/data/judge-$BELONG_TO:/home/judge \
        -v $PATH_DATA/nginx/nginx_conf.d:/etc/nginx/conf.d \
        --restart=unless-stopped \
        csgrandeur/ccpcoj-web2:$CSGOJ_VERSION
    echo "php-$OJ_NAME inited"
    docker restart nginx-server 2>/dev/null
fi
