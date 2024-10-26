## 批量启动一系列判题端
## 常用调用方式
# bash batch_sub_judge.sh \
#   --PATH_DATA=`pwd`/csgoj_data \
#   --OJ_NAME=test \
#   --OJ_HTTP_BASEURL=http://url:20080 \
#   --PASS_JUDGER=987654321 \
#   --OJ_OPEN_OI=0 \
#   --JUDGER_TOTAL=10
## 完整参数
# bash batch_sub_judge.sh \
#   --PATH_DATA=`pwd`/csgoj_data \
#   --OJ_NAME=test \
#   --OJ_HTTP_BASEURL=http://url:20080 \
#   --PASS_JUDGER=987654321 \
#   --OJ_OPEN_OI=0 \
#   --DOCKER_PULL_NEW=0 \
#   --JUDGER_TOTAL=10 \
#   --JUDGE_USER_NAME=judger \
#   --JUDGE_IGNORE_ESOL=1 \
#   --JUDGE_PROCESS_NUM=1 \
#   --JUDGE_SHM_RUN=0 \
#   --JUDGE_SHM_SIZE=1g \
#   --JUDGE_V_TIME_BONUS=2 \
#   --JUDGE_V_MEM_BONUS=512 \
#   --JUDGE_DOCKER_CPUS=4 \
#   --JUDGE_DOCKER_MEMORY=4g \
#   --JUDGE_DOCKER_CPU_OFFSET=2 \
#   --JUDGE_TOP_DIFF_BYTES=2048

source parse_args.sh
parse_args "$@"

echo "OJ_NAME: $OJ_NAME"
echo "OJ_HTTP_BASEURL: $OJ_HTTP_BASEURL"
echo "JUDGE_USER_NAME: $JUDGE_USER_NAME"
echo "PASS_JUDGER: $PASS_JUDGER"
echo "JUDGER_TOTAL: $JUDGER_TOTAL"
echo "OJ_OPEN_OI: $OJ_OPEN_OI"
echo "DOCKER_PULL_NEW: $DOCKER_PULL_NEW"
echo "JUDGE_IGNORE_ESOL: $JUDGE_IGNORE_ESOL"
echo "JUDGE_PROCESS_NUM: $JUDGE_PROCESS_NUM"
echo "JUDGE_SHM_RUN: $JUDGE_SHM_RUN"
echo "JUDGE_SHM_SIZE: $JUDGE_SHM_SIZE"
echo "JUDGE_V_TIME_BONUS: $JUDGE_V_TIME_BONUS"
echo "JUDGE_V_MEM_BONUS: $JUDGE_V_MEM_BONUS"
echo "JUDGE_DOCKER_CPUS: $JUDGE_DOCKER_CPUS"
echo "JUDGE_DOCKER_CPU_OFFSET: $JUDGE_DOCKER_CPU_OFFSET"
echo "JUDGE_DOCKER_MEMORY: $JUDGE_DOCKER_MEMORY"

for ((i=0; i<$JUDGER_TOTAL; i++))
do
    bash start_judge.sh \
        --PATH_DATA=$PATH_DATA \
        --OJ_NAME=$OJ_NAME \
        --OJ_HTTP_BASEURL="$OJ_HTTP_BASEURL" \
        --PASS_JUDGER="$PASS_JUDGER" \
        --OJ_OPEN_OI="$OJ_OPEN_OI" \
        --DOCKER_PULL_NEW=$DOCKER_PULL_NEW \
        --JUDGE_USER_NAME="$JUDGE_USER_NAME" \
        --JUDGE_IGNORE_ESOL=$JUDGE_IGNORE_ESOL \
        --JUDGER_TOTAL=$JUDGER_TOTAL \
        --JUDGE_PROCESS_NUM=$JUDGE_PROCESS_NUM \
        --JUDGE_SHM_RUN=$JUDGE_SHM_RUN \
        --JUDGE_SHM_SIZE=$JUDGE_SHM_SIZE \
        --JUDGE_V_TIME_BONUS=$JUDGE_V_TIME_BONUS \
        --JUDGE_V_MEM_BONUS=$JUDGE_V_MEM_BONUS \
        --JUDGE_DOCKER_CPUS=$JUDGE_DOCKER_CPUS \
        --JUDGE_DOCKER_CPU_OFFSET=$JUDGE_DOCKER_CPU_OFFSET \
        --JUDGE_DOCKER_MEMORY=$JUDGE_DOCKER_MEMORY \
        --JUDGE_TOP_DIFF_BYTES=$JUDGE_TOP_DIFF_BYTES \
        --OJ_MOD=$i
done

wait

# 批量重启： docker ps -a --filter "name=judgeprefix" -q | xargs docker restart
# 批量删除： docker ps -a --filter "name=judgeprefix" -q | xargs docker rm -f
