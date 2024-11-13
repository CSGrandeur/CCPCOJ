set -xe

if [ ! -d /volume/etc ]; then
    cp -rp /home/judge/etc /volume/etc;
fi

if [ ! -f /volume/etc/judge.conf ]; then
    cp -rfp /home/judge/etc/* /volume/etc/;
fi
if [ $OJ_HTTP_BASEURL ];        then sed -i "s#OJ_HTTP_BASEURL=.*#OJ_HTTP_BASEURL=$OJ_HTTP_BASEURL#g"           /volume/etc/judge.conf; fi
if [ $OJ_HTTP_PASSWORD ];       then sed -i "s#OJ_HTTP_PASSWORD=.*#OJ_HTTP_PASSWORD=$OJ_HTTP_PASSWORD#g"        /volume/etc/judge.conf; fi
if [ $OJ_HTTP_USERNAME ];       then sed -i "s#OJ_HTTP_USERNAME=.*#OJ_HTTP_USERNAME=$OJ_HTTP_USERNAME#g"        /volume/etc/judge.conf; fi
if [ $JUDGE_PROCESS_NUM ];      then sed -i "s#OJ_RUNNING=.*#OJ_RUNNING=$JUDGE_PROCESS_NUM#g"                   /volume/etc/judge.conf; fi
if [ $JUDGE_IGNORE_ESOL ];      then sed -i "s#OJ_IGNORE_ESOL=.*#OJ_IGNORE_ESOL=$JUDGE_IGNORE_ESOL#g"           /volume/etc/judge.conf; fi
if [ $JUDGE_TOP_DIFF_BYTES ];   then sed -i "s#OJ_TOP_DIFF_BYTES=.*#OJ_TOP_DIFF_BYTES=$JUDGE_TOP_DIFF_BYTES#g"  /volume/etc/judge.conf; fi
if [ $JUDGE_SHM_RUN ];          then sed -i "s#OJ_SHM_RUN=.*#OJ_SHM_RUN=$JUDGE_SHM_RUN#g"                       /volume/etc/judge.conf; fi
if [ $OJ_OPEN_OI ];             then sed -i "s#OJ_OI_MODE=.*#OJ_OI_MODE=$OJ_OPEN_OI#g"                          /volume/etc/judge.conf; fi

if [ $JUDGE_V_TIME_BONUS ];     then sed -i "s#OJ_JAVA_TIME_BONUS=.*#OJ_JAVA_TIME_BONUS=$JUDGE_V_TIME_BONUS#g"     /volume/etc/judge.conf; fi
if [ $JUDGE_V_MEM_BONUS ];      then sed -i "s#OJ_JAVA_MEMORY_BONUS=.*#OJ_JAVA_MEMORY_BONUS=$JUDGE_V_MEM_BONUS#g"  /volume/etc/judge.conf; fi

if [ ! -d /volume/data ]; then  
    cp -rp /home/judge/data /volume/data;  
fi 

rm -rf /home/judge/backup   
rm -rf /home/judge/data 
rm -rf /home/judge/etc
ln -s /volume/data   /home/judge/data   
ln -s /volume/etc    /home/judge/etc
chmod 777 -R /volume/etc
# create safe env
if mountpoint -q /mnt/overlay_judge/run/dev/shm; then
    umount /mnt/overlay_judge/run/dev/shm
fi
if mountpoint -q /mnt/overlay_judge/run/volume; then
    umount /mnt/overlay_judge/run/volume
fi

if mountpoint -q /mnt/overlay_judge/run; then
    umount -R /mnt/overlay_judge/run
fi
rm -rf /mnt/overlay_judge
mkdir -p /mnt/overlay_judge/run
mkdir -p /mnt/overlay_judge/empty
mount --bind / /mnt/overlay_judge/run
mount --bind /mnt/overlay_judge/empty /mnt/overlay_judge/run/volume
mkdir -p /mnt/overlay_judge/run/dev/shm
mount --bind /dev/shm /mnt/overlay_judge/run/dev/shm
# start judge
/usr/bin/judged
# judged /home/judge debug
sleep infinity
