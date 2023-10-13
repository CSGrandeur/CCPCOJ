#!/bin/bash
EXTENSION=`echo "$1" | cut -d'.' -f2`
FIRST=""
for i in `ls ../data/$2/ac/*.$EXTENSION`
do
        echo "i:$i"
        if [ ! -e "/usr/bin/sim_$EXTENSION" ]
        then
                EXTENSION="text";
        fi
        sim=`/usr/bin/sim_$EXTENSION -p $1 $i |grep ^$1|awk '{print $4}'`
        if [ ! -z "$sim" ] && [ "$sim" -gt 80 ]
        then
                sim_s_id=`basename $i`
                echo "$sim $sim_s_id" >sim
                exit $sim
        fi
        FIRST="false"
done
if [ -z "$FIRST" ] ;then
    echo "first answer"
#     echo "0 0" > sim # zhblue更新了judge_client.cc，要求 sim 文件存在才会保存ac代码，但只在这里输出的话，非FIRST的代码都不会被保存了
else
        echo $FIRST
fi
echo "0 0" > sim   # 要在这里输出到sim. 20230904
exit 0;
