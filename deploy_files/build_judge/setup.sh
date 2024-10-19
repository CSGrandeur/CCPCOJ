set -xe

# CSGOJ basic file system
useradd -m -u 1536 judge
mkdir -p /home/judge/data
mkdir -p /home/judge/etc
mkdir -p /home/judge/log
mkdir -p /home/judge/backup
mkdir -p /var/log/csgoj
chmod -R 700 /home/judge/etc
chmod -R 700 /home/judge/backup

# Judge daemon and client
make      -C /judgecore/core/judged
make      -C /judgecore/core/judge_client
make exes -C /judgecore/core/sim/sim_3_01
cp /judgecore/core/judged/judged                /usr/bin/judged
cp /judgecore/core/judge_client/judge_client    /usr/bin/judge_client 
cp /judgecore/core/sim/sim_3_01/sim_c.exe       /usr/bin/sim_c
cp /judgecore/core/sim/sim_3_01/sim_c++.exe     /usr/bin/sim_cc
cp /judgecore/core/sim/sim_3_01/sim_java.exe    /usr/bin/sim_java
cp /judgecore/core/sim/sim.sh                   /usr/bin/sim.sh
chmod +x /usr/bin/judged
chmod +X /usr/bin/judge_client
chmod +x /usr/bin/sim_c
chmod +X /usr/bin/sim_cc
chmod +x /usr/bin/sim_java
chmod +x /usr/bin/sim.sh


# Adjust system configuration
cp /judgecore/install/pycheck.py  /home/judge/etc/
cp /judgecore/install/java0.policy  /home/judge/etc/
cp /judgecore/install/judge.conf    /home/judge/etc/
chmod 777 -R /home/judge

# # To Install without docker, use two lines below
# update-rc.d csgoj defaults
# systemctl enable csgoj