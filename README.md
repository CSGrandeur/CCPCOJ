# CCPCOJ - 一站式XCPC比赛系统

> 基于 [CSGOJ](https://github.com/CSGrandeur/CSGOJ) 的分支版本，集成抽签、气球、打印、滚榜等比赛管理功能

🌐 **线上版本**：[https://cpc.csgrandeur.cn/](https://cpc.csgrandeur.cn/)

📖 **用户使用说明**：[用户说明书](doc/user_doc.md)

💬 **技术交流**：QQ群 703241234

---

## 📋 目录

- [系统介绍](#系统介绍)
- [快速开始](#快速开始)
- [部署指南](#部署指南)
- [常见问题](#常见问题)
- [开发者指南](#开发者指南)

---

## 系统介绍

CCPCOJ 是一个功能完整的在线判题系统（Online Judge），专为 XCPC（ICPC/CCPC）等程序设计竞赛设计，提供：

- ✅ **题目管理**：支持多种题型（传统、特判、交互式）
- ✅ **比赛管理**：支持正式赛、训练赛、加密赛等多种模式
- ✅ **评测系统**：分布式评测，支持多种编程语言，可扩展
- ✅ **比赛功能**：抽签、气球分发、打印服务、滚榜、集成外榜
- ✅ **用户系统**：角色权限管理、团队管理、个人主页

### 系统要求

- **推荐**：Ubuntu 24.04 及以上
- **可选**：Windows 11 + WSL2 + Docker Desktop
- **必需**：Docker（Ubuntu下部署脚本会自动安装）

---

## 快速开始

### Web 服务器部署

在项目根目录执行：

```bash
bash csgoj_deploy.sh web
```

首次运行会进入交互式配置，按提示填写参数即可。部署完成后访问 `http://<服务器IP>:<端口号>`，首次访问会引导设置管理员账号。

### 评测机节点部署

```bash
bash csgoj_deploy.sh judge \
    --CSGOJ_SERVER_BASE_URL=http://<OJ服务器IP或域名>:<端口号> \
    --CSGOJ_SERVER_USERNAME=<评测机账号> \
    --CSGOJ_SERVER_PASSWORD=<评测机密码>
```

⚠️ **重要提示**：`CSGOJ_SERVER_BASE_URL` 必须使用评测机容器能访问的 IP 地址或域名，**不要使用 `localhost` 或 `127.0.0.1`**。

---

## 部署指南

### 部署脚本说明

`csgoj_deploy.sh` 是统一的部署脚本，支持部署 **Web 服务器** 和 **评测机节点** 两种模式。

### 三种使用模式

#### 1. 命令模式（推荐）

**Web 部署：**
```bash
bash csgoj_deploy.sh web [参数...]
```

**评测机部署：**
```bash
bash csgoj_deploy.sh judge [参数...]
```

#### 2. 交互模式

无参数运行，脚本会引导选择部署内容并配置参数：

```bash
bash csgoj_deploy.sh
```

#### 3. 非交互模式

使用默认值或报错，不进入交互式配置：

```bash
bash csgoj_deploy.sh --noninteractive [参数...]
```

### 常用参数

#### Web 模式关键参数

| 参数 | 说明 | 默认值 |
|------|------|--------|
| `--PATH_DATA` | 数据目录绝对路径 | `$(pwd)/data/csgoj_data` |
| `--OJ_NAME` | OJ 名称 | `ccpc` |
| `--PORT_OJ` | Web 服务端口 | `20080` |
| `--WITH_MYSQL` | 是否部署 MySQL 容器 | `1` |
| `--SQL_HOST` | MySQL 主机（本地部署为 `db`） | `db` |
| `--PASS_SQL_USER` | MySQL 业务用户密码 | `987654321` |

#### 评测机模式必需参数

| 参数 | 说明 |
|------|------|
| `--CSGOJ_SERVER_BASE_URL` | OJ Web 服务器地址（必填） |
| `--CSGOJ_SERVER_USERNAME` | 评测机账号（必填） |
| `--CSGOJ_SERVER_PASSWORD` | 评测机密码（必填） |
| `--JUDGE_POD_COUNT` | 启动的评测机 pod 数量（默认：`1`） |

#### 评测机 Pod 数量说明

**什么是 Pod？**

Pod 是评测机的工作单元，每个 pod 是一个独立的 Docker 容器，可以并发处理评测任务。启动多个 pod 可以显著提高评测系统的并发处理能力。

**资源要求**

每个 pod 的固定资源需求：
- **CPU**：4 逻辑核心（固定值）
- **内存**：4GB + 1GB 共享内存 = 5GB 总计（固定值）

**Pod 数量估算公式**

使用以下公式计算系统可支持的最大 pod 数量：

```
最大 pod 数 = min((CPU 逻辑核心 - 2) / 4, 内存 GB / 5)
```

其中：
- `CPU 逻辑核心 - 2`：保留 2 个核心给系统使用，可用核心用于评测
- `内存 GB`：总内存（共享内存已包含在 5GB 中，无需额外减去）
- `除以 4`：每个 pod 需要 4 个 CPU 核心（固定值）
- `除以 5`：每个 pod 需要 5GB 内存（4GB + 1GB 共享内存，固定值）

**说明**：每个 pod 的资源需求是固定的，不会根据系统资源动态调整。

**示例计算**：

- **小型评测机**（8 CPU / 16GB 内存）：
  - 基于 CPU：`(8 - 2) / 4 = 1.5` → **1 个 pod**
  - 基于内存：`16 / 5 = 3.2` → **3 个 pod**
  - **实际最大**：`min(1, 3) = 1` 个 pod

- **中型评测机**（16 CPU / 32GB 内存）：
  - 基于 CPU：`(16 - 2) / 4 = 3.5` → **3 个 pod**
  - 基于内存：`32 / 5 = 6.4` → **6 个 pod**
  - **实际最大**：`min(3, 6) = 3` 个 pod

- **大型评测机**（32 CPU / 64GB 内存）：
  - 基于 CPU：`(32 - 2) / 4 = 7.5` → **7 个 pod**
  - 基于内存：`64 / 5 = 12.8` → **12 个 pod**
  - **实际最大**：`min(7, 12) = 7` 个 pod

**推荐配置**：
- **小型评测机**（8 CPU / 16GB）：建议 1 个 pod
- **中型评测机**（16 CPU / 32GB）：建议 3 个 pod
- **大型评测机**（32 CPU / 64GB）：建议 7 个 pod

**资源检查**

脚本会自动检查系统资源，如果资源不足：
- 如果无法启动任何 pod：直接报错退出
- 如果可以启动部分 pod：发出警告，并提示可启动的最大数量，询问是否继续

**使用示例**

```bash
# 启动单个 pod（默认）
bash csgoj_deploy.sh judge \
    --CSGOJ_SERVER_BASE_URL=http://192.168.1.100:20080 \
    --CSGOJ_SERVER_USERNAME=judger \
    --CSGOJ_SERVER_PASSWORD=your_password

# 启动多个 pod（推荐用于高性能评测机）
bash csgoj_deploy.sh judge \
    --CSGOJ_SERVER_BASE_URL=http://192.168.1.100:20080 \
    --CSGOJ_SERVER_USERNAME=judger \
    --CSGOJ_SERVER_PASSWORD=your_password \
    --JUDGE_POD_COUNT=4
```

### 配置文件

部署完成后，脚本会在 `./data/csgoj_config.cfg` 保存所有配置参数。

**使用方式**：
- **不提供参数**：自动使用配置文件中的参数
- **提供参数**：参数会覆盖配置文件中的对应项
- **忽略配置文件**：使用 `--ignore-config` 参数

**配置文件位置**：
```
./data/
├── csgoj_config.cfg      # 配置文件（支持同时包含 Web 和 Judge 两套配置）
├── config_log/           # 配置历史日志
└── csgoj_data/          # 数据目录
    ├── var/
    │   ├── www/         # Web 文件
    │   ├── mysql/       # MySQL 数据
    │   ├── data/        # 评测数据
    │   │   └── judge-<OJ名称>/  # 评测机数据目录
    │   │       └── data/        # 评测工作目录（映射到容器 /judge/data）
    │   └── log/         # 日志文件
    └── nginx/           # Nginx 配置
```

**备份**：只需备份 `./data` 目录即可保留所有配置和数据。

### 常用操作

#### 查看帮助

```bash
bash csgoj_deploy.sh --help
```

#### 重启所有评测机

```bash
bash csgoj_deploy.sh judge --restart-all
```

#### 重开所有评测机（删除重建）

```bash
bash csgoj_deploy.sh judge --rebuild-all \
    --CSGOJ_SERVER_BASE_URL=... \
    --CSGOJ_SERVER_USERNAME=... \
    --CSGOJ_SERVER_PASSWORD=...
```

### 部署流程

#### Web 服务器部署

1. **运行脚本**：`bash csgoj_deploy.sh web`
2. **首次部署**：按提示配置参数（MySQL、端口等）
3. **启动服务**：脚本会自动安装 Docker、启动 MySQL、Nginx、PHP 等容器
4. **访问系统**：浏览器打开 `http://<服务器IP>:20080`
5. **初始化账号**：首次访问会引导设置管理员和评测机账号

#### 评测机节点部署

1. **准备账号**：在 Web 管理界面创建评测机账号
2. **运行脚本**：
   ```bash
   # 启动单个 pod（默认）
   bash csgoj_deploy.sh judge \
       --CSGOJ_SERVER_BASE_URL=http://<Web服务器IP>:<端口号> \
       --CSGOJ_SERVER_USERNAME=<评测机账号> \
       --CSGOJ_SERVER_PASSWORD=<评测机密码>
   
   # 启动多个 pod（推荐用于高性能评测机）
   bash csgoj_deploy.sh judge \
       --CSGOJ_SERVER_BASE_URL=http://<Web服务器IP>:<端口号> \
       --CSGOJ_SERVER_USERNAME=<评测机账号> \
       --CSGOJ_SERVER_PASSWORD=<评测机密码> \
       --JUDGE_POD_COUNT=4
   ```
3. **资源检查**：脚本会自动检查 CPU 和内存资源，并根据 pod 数量计算每个 pod 的资源分配
4. **资源不足处理**：如果资源不足以启动目标数量的 pod，脚本会：
   - 显示可启动的最大 pod 数量
   - 提示是否继续启动可用的 pod 数量
   - 或直接报错退出（如果无法启动任何 pod）
5. **启动 pod**：脚本会自动启动指定数量的评测机 pod，每个 pod 独立运行，可以并发处理评测任务

### 查看日志

```bash
# Web 服务日志
docker logs php-ccpc

# Nginx 日志
docker logs nginx-server

# MySQL 日志
docker logs db

# 评测机日志
docker logs judge-ccpc
```

---

## 常见问题

### 容器启动失败

查看容器日志：
```bash
docker logs <容器名>
```

### 端口冲突

检查端口占用：
```bash
netstat -tuln | grep <端口号>
```

修改端口配置：
```bash
bash csgoj_deploy.sh web --PORT_OJ=8080
```

### 配置文件问题

忽略配置文件重新配置：
```bash
bash csgoj_deploy.sh --ignore-config [其他参数...]
```

### 注意事项

1. **网络配置**：评测机的 `CSGOJ_SERVER_BASE_URL` 必须是评测机容器能访问的地址（不要用 localhost）
2. **资源要求**：
   - 每个评测机 pod 固定需要 **4 CPU 核心** 和 **5GB 内存**（4GB + 1GB 共享内存）
   - Pod 数量估算公式：`min((CPU 逻辑核心 - 2) / 4, 内存 GB / 5)`
   - 脚本会自动检查资源并验证是否足够启动指定数量的 pod
3. **Pod 数量**：
   - 单 pod 适合小型评测机或低并发场景
   - 多 pod 可显著提高并发处理能力，适合大型比赛
   - 脚本会自动检查资源，资源不足时会提示可启动的最大 pod 数量
4. **配置文件**：脚本会自动保存配置到 `./data/csgoj_config.cfg`，下次运行可直接使用
5. **数据备份**：定期备份 `./data` 目录即可保留所有配置和数据

---

## 开发者指南

### 自行编译及本地部署

如需从源码编译并部署，请参考以下步骤：

```bash
# 编译评测机镜像
cd deploy_files/judge2
bash docker_build_base.sh
bash dockerbuild.sh

# 编译 Web 镜像
cd ../build_php
bash dockerbuild_script.sh

# 构建发布包
cd ..
bash release.sh build judge web sh

# 开发模式部署（挂载源码目录）
export CSGOJ_DEV=1
bash csgoj_deploy.sh web
```

### 发布流程

```bash
cd deploy_files
bash release.sh <版本号> build push web judge sh tag
```

---

## 许可证

本项目基于 CSGOJ，请参考原项目许可证。

---

## 贡献

欢迎提交 Issue 和 Pull Request！

---

**最后更新**：请查看 [更新日志](doc/更新日志.log)
