#!/bin/bash
jpodname=$1

if [ -z "$jpodname" ]; then
    echo "Usage: $0 <jpodname>"
    exit 1
fi

docker exec $jpodname rm -rf /judgecore

docker cp . $jpodname:/judgecore

docker cp /judgecore/entrypoint.sh $jpodname:/entrypoint.sh

docker exec $jpodname cp -f /judgecore/entrypoint.sh /entrypoint.sh
docker exec $jpodname pkill -9 judged
docker exec $jpodname userdel judge
docker exec $jpodname bash /judgecore/setup.sh
docker exec $jpodname chmod 777 -R /volume
docker exec $jpodname bash /entrypoint.sh