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

if [ ! -d /volume/data ]; then  
    cp -rp /home/judge/data /volume/data;  
fi 

rm -rf /home/judge/backup   
rm -rf /home/judge/data 
rm -rf /home/judge/etc
ln -s /volume/data   /home/judge/data   
ln -s /volume/etc    /home/judge/etc
chmod 777 -R /volume/etc
/usr/bin/judged
# judged /home/judge debug
sleep infinity
