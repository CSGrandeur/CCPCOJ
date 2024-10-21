pkill -9 judged
userdel judge
bash setup.sh
chmod 777 -R /volume
bash /entrypoint.sh
