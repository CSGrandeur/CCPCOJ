# CCPCOJ - 一站式XCPC比赛系统

> [CSGOJ](https://github.com/CSGrandeur/CSGOJ) 分支版本

集成抽签、气球、打印、滚榜

线上版本：https://cpc.csgrandeur.cn/

使用方法见：[用户说明书](doc/user_doc.md)

技术交流QQ群：703241234


### 1. 依赖环境

推荐：Ubuntu22.04及以上

也可以 windows11以上 + wsl2 + docker for windows

**部署脚本在 `deploy_files/start_scripts`**

**部署前请确保目标目录（默认为脚本所在目录）有所有读写权限。**

### 2. 试用部署

```bash
cd deploy_files/start_scripts
bash auto_deploy.sh --WITH_JUDGE=1
```

执行完毕后访问 `127.0.0.1:20080`，管理员账号 `admin: 987654321`

### 3. 基本部署

#### 3.1 Web端

```bash
bash auto_deploy.sh \
    --PASS_SQL_ROOT="<数据库root密码>" \
    --PASS_SQL_USER="<数据库业务用户csgcpc的密码>" \
    --PASS_JUDGER="<评测机账号judger的密码>" \
    --PASS_ADMIN="<OJ超级管理员admin的密码>" \
    --PASS_MYADMIN_PAGE="<数据库phpmyadmin的页面访问用户admin的密码>" \
    --PORT_OJ="<自定义OJ Web的端口号>"
```

例如：

```bash
bash auto_deploy.sh \
    --PASS_SQL_ROOT="123456" \
    --PASS_SQL_USER="123456789" \
    --PASS_JUDGER="999999" \
    --PASS_ADMIN="666666" \
    --PASS_MYADMIN_PAGE="333333" \
    --PORT_OJ=80
```

该脚本会自动安装 docker，并部署 mysql、nginx的容器。

如果系统已有docker环境，mysql、nginx服务器，可以仅启动 OJ Web 容器提供 php-fpm 服务，自行在mysql和nginx做相应配置。

```bash
bash start_oj.sh \
    --SQL_HOST="<MySQL IP>" \
    --SQL_USER="<数据库用户名>" \
    --PASS_SQL_USER="<你的数据库密码>" \
    --PASS_ADMIN="666666" \
    --PASS_JUDGER="999999" \
    --PORT_OJ=80
```


#### 3.2 评测机

如果评测机在新环境部署，先安装 docker：

```bash
bash install_docker.sh
```

也可自行参照docker官网安装。

##### 3.2.1 单机多pod

内核多、内存大、硬盘快的高性能评测机使用

```bash
bash batch_sub_judge.sh \
  --OJ_HTTP_BASEURL=<OJ地址> \
  --PASS_JUDGER=<judger的密码> \
  --JUDGER_TOTAL=<启动的pod数>
```

例如

```bash
bash batch_sub_judge.sh \
  --OJ_HTTP_BASEURL=http://url:20080 \
  --PASS_JUDGER="999999" \
  --JUDGER_TOTAL=2
```

建议参数（估算pod个数的方法）： 

满载情况 每个默认pod应有 6个CPU逻辑处理器、6GB可用内存。

如果题目压力不大不至于满载，也可以相对开多几个pod。如果题目压力非常大，可酌情参照“**3.2.4 一些常用的其它定制参数**”进行深度定制。



##### 3.2.2 单机单pod

性能较差的多台评测机使用

```bash
bash start_judge.sh \
  --OJ_HTTP_BASEURL=http://url:20080 \
  --PASS_JUDGER="999999"
```

##### 3.2.3 多机判题

直接在多个机器分别启pod即可，空闲pod会自行拉取任务。


##### 3.2.4 一些常用的其它定制参数

`JUDGE_USER_NAME`

在web端的后台设置了更多评测账号后，可以给不同评测机使用不同评测账号，便于遇到问题时定位评测机

![](doc/deploy_doc_image/user_gen.png)


![](doc/deploy_doc_image/user_gen_judger.png)

`JUDGE_PROCESS_NUM`

每个 pod 内的并行评测进程数，默认为 2，如果设备配置很差，可改为 1

`JUDGE_DOCKER_CPUS`

每个 pod 限制使用的逻辑处理器个数，默认为 6，对应 `JUDGE_PROCESS_NUM=2` 时的数量需求，每个判题进程至少需要 3 个逻辑处理器，在此基础上可酌情增加。

`JUDGE_DOCKER_MEMORY`

每个 pod 限制使用的内存大小，默认`6g`，对应 `JUDGE_PROCESS_NUM=2` 两个评测进程的内存，可酌情增加，值为整数加字母“g”，比如 `--JUDGE_DOCKER_MEMORY=8g`。

`JUDGE_DOCKER_CPU_OFFSET`

是否让容器绑定 CPU 逻辑处理器，默认为 0 ，表示不绑定，系统自动动态分配逻辑处理器。

如果CPU逻辑处理器数量不多，题目评测压力较大，在设置不满载的情况下仍然出现抢夺CPU资源导致评测波动的情况下，启用该参数，将作为绑定逻辑处理器编号的起始偏移量。

例如将`JUDGE_DOCKER_CPU_OFFSET`设为 2，`JUDGE_DOCKER_CPUS=6`，则第一个pod绑定编号为 `2,3,4,5,6,7` 的逻辑处理器，后续pod顺延。

`JUDGER_TOTAL`

使用`batch_sub_judge.sh`批量启动评测pod时使用该参数设置pod总数，如上文估算方式所述。

`JUDGE_SHM_RUN`

默认为0

如果评测机硬盘较差，可以提供该参数设为 1，让评测机每次复制数据到内存后再执行评测

使用参考：

```bash
# 单机单pod
bash start_judge.sh \
  --OJ_HTTP_BASEURL=http://url:20080 \
  --PASS_JUDGER="999999" \
  --JUDGE_USER_NAME="judger2" \
  --JUDGE_SHM_RUN=1
# 单机多pod
bash batch_sub_judge.sh \
  --OJ_HTTP_BASEURL=http://url:20080 \
  --PASS_JUDGER="999999" \
  --JUDGER_TOTAL=2 \
  --JUDGE_USER_NAME="judger2" \
  --JUDGE_SHM_RUN=1
```

`JUDGE_SHM_SIZE`

配合`JUDGE_SHM_RUN`使用，每个pod为评测数据提供的内存文件区大小，默认 `1g`，基本无需修改。使用时传参也为整数加字母“g”，例如`--JUDGE_SHM_RUN=2g`。


上述JUDGE参数更具体的建议：

```
JUDGE_PROCESS_NUM ∈ {1, 2}
JUDGE_DOCKER_CPUS >= JUDGE_PROCESS_NUM * 3
JUDGER_TOTAL=⌊ ({CPU逻辑处理器总数}> - 2) / JUDGE_DOCKER_CPUS ⌋
JUDGE_SHM_RUN=0时：
JUDGE_DOCKER_MEMORY * JUDGER_TOTAL < {系统内存}, 
JUDGE_SHM_RUN=1时：
(JUDGE_DOCKER_MEMORY + JUDGE_SHM_SIZE) * JUDGER_TOTAL < {系统内存}, 
```

SSD环境下大数据题目30次测试统计，仅供参考：

| 模式   | 常规                | SHM                                | CPU绑定                                        | CPU绑定+SHM                                                      |
|------|-------------------|------------------------------------|----------------------------------------------|----------------------------------------------------------------|
| 参数   | --JUDGER_TOTAL=3<br/> | --JUDGER_TOTAL=3<br/>--JUDGE_SHM_RUN=1 | --JUDGER_TOTAL=3<br/>--JUDGE_DOCKER_CPU_OFFSET=2 | --JUDGER_TOTAL=3<br/>--JUDGE_DOCKER_CPU_OFFSET=2<br/>--JUDGE_SHM_RUN=1 |
| 耗时均值   | 1451.35           | 1443.55                            | 1435.35                                      | 1436.94                                                        |
| 标准差  | 28.01             | 27.67                              | 25.92                                        | 24.93                                                          |
| 浮动比率 | 1.93%             | 1.92%                              | 1.81%                                        | 1.73%                                                          |

建议在使用多核较强大的工作站时使用多pod多进程评测。

在普通PC机作为评测机时，运算压力大的题目并行评测的时间波动几乎无法消除，稳妥的方式减少pod（JUDGER_TOTAL）、减少并行进程（JUDGE_PROCESS_NUM）。

##### 3.2.5 关于虚拟机

建议使用实体机作为评测机，不建议使用虚拟机。

虚拟机可能存在虚拟CPU资源波动、虚拟硬盘读写性能差导致高压力题目评测波动的可能。

如果仍要使用虚拟机，优先考虑VMware vsphere Hypervisor等硬件虚拟化能够真实映射CPU逻辑处理器的虚拟平台。


#### 3.3 参数日志

第一次执行脚本后会生成一个配置日志目录 “`config_log`”，如果所有参数都敲定了，可以将满意的配置（形如“`csgoj_config_1698330181.cfg`”的文件）复制到与脚本同一级目录并改名为“`csgoj_config.cfg`”，后续执行脚本可不再输入任何参数，参数的调整也可以直接修改`csgoj_config.cfg`文件。

#### 3.4 注意事项


- Web服务器一定要保证较好的硬件配置
    - 否则出现性能瓶颈时，评测机与Web通讯不佳会导致评测结果出问题。
- 评测机性能没有特殊要求，但和web服务器之间的网络务必通畅
    - 按照上述cpu、内存需求量估算启动的pod数即可，当然还是建议用性能好一点的
    - 任何不符合预期的评测问题，优先考虑评测机数据同步问题，如果不能定位评测机具体数据，可在评测机上直接删评测数据目录（`csgoj_data/var/data/judge-csgoj/data`）并重启judge容器（`docker restart judge-xxxx`）
    - 评测机建议用网线连入网络，不要用wifi，以减少网络波动
- 初始启动时的所有密码（几个”PASS_”开头的参数）建议只用数字字母，以免特殊符号的密码在配置传递中跨系统时识别出错

脚本默认为 latest 版本，如果需要特定版本，可设置 `--CSGOJ_VERSION`参数指定docker镜像版本


### 4. 自行编译及本地部署

```bash
cd deploy_files/build_judge
bash docker_build_base.sh 
cd ../build_php
bash dockerbuild_script.sh
cd ..
bash release.sh build judge web
export CSGOJ_DEV=1
bash auto_deploy.sh <your parameters>
```