#!/bin/bash
# nginx and mysql should be started ahead, and baseoj should be inited
# bash start_judge.sh \
# --PATH_DATA=`pwd`/csgoj_data \
# --OJ_NAME=csgoj \
# --OJ_HTTP_BASEURL='http://nginx-server:20080' \
# --PASS_JUDGER='999999'  \
# --JUDGE_USER_NAME='judger' \
# --JUDGE_DOCKER_CPUS=6 \
# --JUDGE_DOCKER_MEMORY=6g \
# --JUDGE_PROCESS_NUM=2 \
# --JUDGE_SHM_RUN=0 \
# --JUDGER_TOTAL=1 \
# --OJ_MOD=0 

source parse_args.sh
parse_args "$@"


echo "##################################################"
echo "Initing judge"
if [ "$JUDGER_TOTAL" -eq 1 ] && [ "$(docker ps -a -q -f name=/judge-$OJ_NAME$)" ]; then
    echo "judge-$OJ_NAME ready"
else
    echo "JUDGER_TOTAL: $JUDGER_TOTAL"
    echo "JUDGER_IDX: $OJ_MOD"

    if [ ! -e "$PATH_DATA/var/data/judge-$OJ_NAME" ]; then
        mkdir -p $PATH_DATA/var/data/judge-$OJ_NAME
    fi

    if [ $JUDGER_TOTAL -gt 1 ]; then
        CHANGE_ETC="etc-$OJ_MOD"
        if [ -d $PATH_DATA/var/data/judge-$OJ_NAME/$CHANGE_ETC ]; then
            sudo rm -rf $PATH_DATA/var/data/judge-$OJ_NAME/$CHANGE_ETC
        fi
        mkdir -p $PATH_DATA/var/data/judge-$OJ_NAME/$CHANGE_ETC
        SIDE_ETC="-v $PATH_DATA/var/data/judge-$OJ_NAME/$CHANGE_ETC:/volume/etc"  # 这里要映射整个etc目录，因为etc下的judge.pid识别judge占用
        CONTAINER_NAME=judge-$OJ_NAME-$OJ_MOD
    else
        if [ -d $PATH_DATA/var/data/judge-$OJ_NAME/etc ]; then
            sudo rm -rf $PATH_DATA/var/data/judge-$OJ_NAME/etc
        fi
        SIDE_ETC=""
        CHANGE_ETC="etc"
        CONTAINER_NAME=judge-$OJ_NAME
    fi
    SHM_CONFIG=""
    if [ -n "$JUDGE_SHM_RUN" ] && [ "$JUDGE_SHM_RUN" != "0" ]; then
        SHM_CONFIG="--shm-size $JUDGE_SHM_SIZE"
    fi
    
    if [ "$DOCKER_PULL_NEW" = "1" ] && ([ -z "$CSGOJ_DEV" ] || [ "$CSGOJ_DEV" != "1" ]); then
        docker pull csgrandeur/ccpcoj-judge:$CSGOJ_VERSION   # 先pull以确保镜像最新
    fi

    docker run -dit $LINK_LOCAL \
        --name $CONTAINER_NAME \
        -e OJ_HTTP_BASEURL="$OJ_HTTP_BASEURL" \
        -e OJ_HTTP_PASSWORD=$PASS_JUDGER \
        -e OJ_HTTP_USERNAME=$JUDGE_USER_NAME \
        -e OJ_OPEN_OI=$OJ_OPEN_OI \
        -e JUDGE_PROCESS_NUM=$JUDGE_PROCESS_NUM \
        -e JUDGE_IGNORE_ESOL=$JUDGE_IGNORE_ESOL \
        -e JUDGE_TOP_DIFF_BYTES=$JUDGE_TOP_DIFF_BYTES \
        -e JUDGE_SHM_RUN=$JUDGE_SHM_RUN \
        -v $PATH_DATA/var/data/judge-$OJ_NAME:/volume $SIDE_ETC \
        --cpus=$JUDGE_DOCKER_CPUS \
        --memory=$JUDGE_DOCKER_MEMORY \
        --cap-add=SYS_PTRACE $SHM_CONFIG \
        --restart unless-stopped \
        csgrandeur/ccpcoj-judge:$CSGOJ_VERSION

    echo "judge-$OJ_NAME inited"
fi
