# CSGOJ judge2 评测机 Docker 镜像
FROM csgrandeur/judge2_base:latest

# 创建工作目录
RUN mkdir -p /core && \
    mkdir -p /judge/logs && \
    mkdir -p /judge/data && \
    mkdir -p /judge/workspace

# 复制评测机核心文件
COPY core/ /core/

# 复制评测库文件
COPY judge_lib/ /judge_lib/

# 设置C/C++头文件查找路径
ENV CPLUS_INCLUDE_PATH=/judge_lib
ENV C_INCLUDE_PATH=/judge_lib

# 编译默认比对器
RUN g++ -o /core/default_check /core/default_check.cc -O2

# 设置权限
RUN chmod +x /core/default_check

# 注意：保持 root 用户运行
# 原因：
# 1. 评测机需要 mount/chroot/cgroups/seccomp 等系统级权限
# 2. 容器已通过 --privileged 隔离，容器内 root 的安全性由容器隔离层保障
# 3. 评测机进程本身是受信任的系统组件
# 设置工作目录
WORKDIR /core

# 设置启动命令
CMD ["python3", "judge_host.py"]
