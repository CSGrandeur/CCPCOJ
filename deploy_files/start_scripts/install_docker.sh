#!/bin/bash

echo "##################################################"
echo "Initing Docker"
if command -v docker &> /dev/null; then
    echo "Docker ready"
else
    echo "Start to install docker-ce"
    for pkg in docker.io docker-doc docker-compose podman-docker containerd runc; do sudo apt-get remove $pkg; done
    sudo apt-get update
    sudo apt-get install -y ca-certificates curl gnupg
    sudo install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://mirrors.aliyun.com/docker-ce/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    sudo chmod a+r /etc/apt/keyrings/docker.gpg
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://mirrors.aliyun.com/docker-ce/linux/ubuntu \
      "$(. /etc/os-release && echo "$VERSION_CODENAME")" stable" | \
      sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
    sudo apt-get update
    sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    sudo groupadd docker
    sudo gpasswd -a $USER docker
    newgrp docker
    docker ps
    daemonjson='{
        "registry-mirrors": [
            "https://docker.m.daocloud.io", 
            "https://docker.jianmuhub.com",
            "https://huecker.io",
            "https://dockerhub.timeweb.cloud",
            "https://dockerhub1.beget.com",
            "https://noohub.ru"
        ]
    }'
    sudo bash -c "echo '$daemonjson' > /etc/docker/daemon.json"
    sudo service docker restart
    echo "Docker inited"
fi
