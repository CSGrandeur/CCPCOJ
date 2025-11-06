# CSGOJ 启动脚本开发文档

本文档面向开发者（和 AI 助手），详细介绍 `start_scripts` 目录下各脚本的功能设计、架构和修改指南。

## 目录结构

```
start_scripts/
├── csgoj_deploy.sh          # 主部署脚本（由 build_deploy.sh 生成）
├── build_deploy.sh           # 构建脚本（合并所有脚本生成 csgoj_deploy.sh）
├── parse_args.sh             # 参数解析和配置管理核心模块
├── deploy_dev.sh             # 开发环境部署脚本（伪合并版本）
├── configure.sh              # 交互式配置向导（独立工具，开发者使用）
├── system_check.sh           # 系统环境检查脚本（专用于评测机部署）
├── start_judge2.sh           # 评测机启动脚本（独立工具）
├── start_ojweb.sh            # OJ Web 容器启动脚本
├── start_nginx.sh             # Nginx 容器启动脚本
├── start_db.sh                # MySQL 容器启动脚本
├── start_myadmin.sh           # PHPMyAdmin 容器启动脚本
├── install_docker.sh          # Docker 安装脚本
└── data/                      # 运行时数据和配置目录
    ├── csgoj_config.cfg      # 配置文件（支持 Web + Judge 双模式）
    ├── config_log/           # 配置历史日志
    └── csgoj_data/           # 数据目录
```

## 核心架构

### 设计原则

1. **函数化封装**：所有脚本的主要逻辑封装为函数，便于合并和复用
2. **职责分离**：`parse_args.sh` 负责参数解析，不包含副作用操作
3. **配置优先级**：命令行参数 > 配置文件 > 默认值
4. **配置合并**：配置文件支持同时包含 Web 和 Judge 两套配置
5. **智能回退**：镜像拉取失败时自动回退到本地镜像

### 参数解析流程

```
命令行参数
    ↓
parse_args() [parse_args.sh]
    ↓
load_config_files() [parse_args.sh]
    ├─ 检查 IGNORE_CONFIG 标志
    ├─ 加载 DEFAULT_CONFIG (./data/csgoj_config.cfg)
    └─ 加载 CONFIG_FILE (用户指定)
    ↓
设置派生配置（如 BELONG_TO）
```

### 主脚本执行流程

```
main() [csgoj_deploy.sh]
    ├─ 命令模式判断（web/judge）
    ├─ 交互模式（无参数时）
    ├─ 隐式模式（根据参数推断）
    ↓
parse_args() [参数解析]
    ↓
部署模式判断
    ├─ deploy_web()     [Web 服务器部署]
    │   ├─ 加载配置文件（如未忽略）
    │   ├─ 检查参数完整性
    │   ├─ 交互式补全（如不完整）
    │   ├─ install_docker()
    │   ├─ start_db()
    │   ├─ start_myadmin()
    │   ├─ start_ojweb()
    │   ├─ start_nginx()
    │   └─ write_full_config_file("web")
    │
    └─ deploy_judge()   [评测机节点部署]
        ├─ 加载配置文件（如未忽略）
        ├─ 参数验证和补全
        ├─ system_check() [系统环境检查，仅在启动新 pod 时执行]
        │   ├─ check_ubuntu_version() [检查 Ubuntu >= 22.04]
        │   └─ check_memory_peak() [检查 cgroup v2 和 memory.peak 支持]
        ├─ 资源检查和计算
        ├─ start_single_pod() [循环启动 pod]
        └─ write_full_config_file("judge")
```

## 核心文件详解

### `parse_args.sh`

**职责**：
- 定义所有配置参数的默认值
- 解析命令行参数
- 加载配置文件（支持 `--ignore-config` 忽略）
- 提供帮助信息

**关键函数**：

- `parse_args()`: 解析命令行参数，调用 `load_config_files()`
- `load_config_files()`: 加载配置文件（受 `IGNORE_CONFIG` 控制）
- `write_full_config_file()`: 写入配置文件，支持追加模式（保留另一套配置）
- `init_config_log()`: 初始化配置日志目录
- `init_docker_network()`: 初始化 Docker 网络
- `init_data_directory()`: 初始化数据目录

**配置合并逻辑**：

`write_full_config_file()` 函数支持追加模式：
1. 读取已有配置文件
2. 提取另一套配置（如果存在）
3. 写入基础配置
4. 写入当前模式配置（web 或 judge）
5. 保留另一套配置

### `build_deploy.sh`

**职责**：
- 合并所有脚本生成 `csgoj_deploy.sh`
- 提取函数定义并嵌入主脚本
- 生成交互式配置函数

**合并流程**：

1. **提取 `parse_args.sh`**：直接嵌入（包含所有函数和变量定义）
2. **提取服务脚本函数**：从 `install_docker.sh`、`start_db.sh` 等提取函数定义
3. **提取系统检查函数**：从 `system_check.sh` 提取所有检查函数
4. **生成交互式函数**：`interactive_configure()`、`interactive_configure_web_first_time()` 等
5. **生成主逻辑**：`main()` 函数和 `deploy_web()`、`deploy_judge()` 函数

**函数提取方法**：

使用 `awk` 进行括号匹配，提取完整的函数定义：
```bash
awk '/^[a-zA-Z_][a-zA-Z0-9_]*\(\) \{/,/^}$/ {print}' script.sh
```

### `csgoj_deploy.sh`

**生成方式**：由 `build_deploy.sh` 自动生成，包含所有功能。

**特点**：
- 单一文件，自包含
- 无需外部依赖（除 Docker）
- 支持所有部署模式

**使用**：直接运行，无需额外脚本。

### `configure.sh`

**职责**：独立的交互式配置向导，供开发者单独使用。

**特点**：
- 复用 `parse_args.sh` 的默认值（消除默认值冗余）
- 使用 `parse_args.sh` 的 `write_full_config_file()` 函数（消除配置写入逻辑冗余）
- 只生成配置文件，不执行部署
- 开发者可以独立运行，无需部署整个系统

**用途**：
- 开发者单独生成配置文件
- 测试配置逻辑
- 快速配置而不触发部署

**使用方式**：
```bash
bash configure.sh  # 交互式生成配置文件
```

### `deploy_dev.sh`

**职责**：开发环境的“伪合并”版本，用于开发测试。

**特点**：
- 通过 `source` 加载各个脚本
- 调用脚本中的函数
- 行为与 `csgoj_deploy.sh` 一致，但保留模块化结构

**用途**：开发时快速测试，无需每次运行 `build_deploy.sh`。

### `system_check.sh`

**职责**：系统环境检查脚本，专用于评测机部署。

**执行时机**：
- 仅在 `deploy_judge()` 中执行
- 仅在需要启动新 pod 时执行（`RESTART_ALL = false` 且 `REMOVE_ALL = false`）
- Web 服务器部署不执行系统检查

**关键功能**：
- Ubuntu 版本检查（需要 22.04 或更高）
- cgroup v2 启用检查
- 内核 memory.peak 支持检查（需要内核 5.19+）
- 内核升级功能（支持交互式升级）

**函数**：
- `check_ubuntu_version()`: 检查 Ubuntu 版本是否为 22.04 以上
- `check_cgroup_v2_enabled()`: 检查 cgroup v2 是否启用
- `check_memory_peak_support()`: 检查内核是否支持 memory.peak
- `check_memory_peak()`: 主检查函数，包含 cgroup v2 和 memory.peak 检查
- `upgrade_kernel()`: 升级内核到支持 memory.peak 的版本
- `system_check()`: 主入口函数，依次执行所有检查

**检查逻辑**：
1. 如果 Ubuntu 版本 < 22.04，直接报错退出
2. 如果 cgroup v2 未启用，报错并提示启用方法
3. 如果内核不支持 memory.peak：
   - 交互模式：询问用户是否升级内核
   - 非交互模式：直接报错并提示手动升级

**注意**：
- 所有提示信息使用通俗易懂的语言，避免专业术语（如 "memory.peak"）
- 内核升级需要重启系统才能生效

### `start_judge2.sh`

**职责**：独立的评测机启动脚本。

**关键功能**：
- 资源检查和计算（CPU、内存）
- 智能 pod 索引查找
- 支持 `--restart-all` 和 `--rebuild-all`
- 镜像选择（先尝试 latest，失败回退到本地）

**函数**：
- `show_help()`: 显示帮助信息
- `calculate_cpu_offset()`: 计算 CPU 偏移量
- `validate_and_calculate_resources()`: 验证和计算资源
- `find_available_pod_indices()`: 查找可用 pod 索引
- `restart_all_judge_pods()`: 重启所有 pod
- `rebuild_all_judge_pods()`: 删除并重建所有 pod
- `start_single_pod()`: 启动单个 pod

### 服务启动脚本

所有服务启动脚本（`start_*.sh`）遵循相同模式：

1. **函数封装**：主要逻辑封装为函数（如 `start_nginx()`）
2. **容器检查**：检查容器是否已存在，存在则跳过
3. **镜像处理**：
   - 官方镜像（如 nginx）：自动 pull，不检查本地
   - 自定义镜像（如 ccpcoj-web）：先尝试拉取，失败后回退到本地
4. **目录创建**：确保所需目录存在
5. **错误捕获**：捕获 `docker run` 错误并显示详细信息

**示例结构**：
```bash
start_service() {
    # 检查容器
    if [ -n "$(docker ps -aq -f name=^service-name$)" ]; then
        echo "✅ 容器已存在"
        return 0
    fi
    
    # 镜像选择逻辑
    # 目录创建
    # docker run
    # 错误检查
}
```

## 配置文件管理

### 配置文件格式

`./data/csgoj_config.cfg` 支持同时包含 Web 和 Judge 两套配置：

```bash
# ==================== 基础配置 ====================
PATH_DATA="..."
OJ_NAME="..."
# ...

# ==================== OJ Web Server 配置 ====================
WITH_MYSQL=1
SQL_HOST="db"
PORT_OJ=20080
# ...

# ==================== Judge Node 配置 ====================
CSGOJ_SERVER_BASE_URL="http://..."
CSGOJ_SERVER_USERNAME="..."
# ...
```

### 配置写入逻辑

`write_full_config_file(deploy_mode)` 的合并策略：

1. **读取已有配置**：使用 `sed` 提取另一套配置
2. **写入基础配置**：始终更新基础配置部分
3. **写入当前模式配置**：根据 `deploy_mode` 写入对应配置
4. **保留另一套配置**：如果存在，追加到文件末尾

### 配置加载逻辑

`load_config_files()` 的加载顺序：

1. **检查 `IGNORE_CONFIG`**：如果为 `true`，跳过所有配置文件
2. **加载默认配置**：`./data/csgoj_config.cfg`（如果存在）
3. **加载指定配置**：`--CONFIG_FILE` 指定的配置文件（优先级更高）

## 镜像管理策略

### 官方镜像（nginx）

- **版本常量**：`NGINX_IMAGE_VERSION="1.29.1-alpine"`（定义在函数内）
- **拉取策略**：Docker 自动拉取（不使用 `--pull=never`）
- **错误处理**：依赖 Docker 的自动拉取机制

### 自定义镜像（ccpcoj-web、ccpcoj-judge2）

- **拉取策略**：
  1. 先尝试拉取指定版本（`CSGOJ_VERSION`，通常是 `latest`）
  2. 拉取失败 → 检查本地镜像（验证有效性，大小 > 1MB）
  3. 本地有有效镜像 → 使用本地镜像并提示用户
  4. 本地无有效镜像 → 报错并提示手动拉取

## 交互式配置流程

### 主选择流程

`interactive_configure()`：
1. 显示部署内容选择（web/judge）
2. 根据选择进入对应配置流程
3. Judge 模式：在函数内调用 `deploy_judge()` 并 `exit`
4. Web 模式：设置 `INTERACTIVE_SELECTED_MODE="web"` 并 `return 0`

### Web 模式首次配置

`interactive_configure_web_first_time()`：
- 加载已有配置文件（如果存在）
- 逐个参数提示，支持回车使用当前值
- 区分 MySQL 本地部署和外连模式

### Judge 模式参数补全

`interactive_complete_judge_params()`：
- 检查缺失的必需参数
- 逐个提示输入
- 验证参数有效性

## 部署模式检测

### 检测顺序

1. **命令模式**：第一个参数是 `web` 或 `judge`
2. **隐式检测**：命令行参数中包含 `CSGOJ_SERVER_BASE_URL` 或 `CSGOJ_SERVER_PASSWORD`
3. **交互选择**：无明确指示时进入交互式选择

### 模式判断逻辑

```bash
if [ "$DEPLOY_MODE" = "judge" ]; then
    # Judge 模式
elif [ "$DEPLOY_MODE" = "web" ]; then
    # Web 模式
elif [ -z "$DEPLOY_MODE" ]; then
    # 隐式检测或默认 Web 模式
fi
```

## 参数优先级

```
命令行参数 > --CONFIG_FILE 指定的配置文件 > DEFAULT_CONFIG > 默认值
```

**特殊标志**：
- `--ignore-config`：跳过所有配置文件，仅使用命令行参数和默认值

## 修改指南

### 添加新参数

1. **在 `parse_args.sh` 中**：
   - 添加默认值定义
   - 在 `show_help()` 中添加说明
   - 在 `parse_args()` 的参数解析中添加 `case` 分支

2. **在 `build_deploy.sh` 中**：
   - 如果是 Web 模式参数，在 `interactive_configure_web_first_time()` 中添加提示
   - 如果是 Judge 模式参数，在 `interactive_complete_judge_params()` 中添加提示
   - 在 `write_full_config_file()` 中添加配置写入逻辑

3. **在服务脚本中**（如需要）：
   - 使用新参数配置容器

### 配置相关修改

如果修改了配置文件格式或交互式配置流程：

1. **修改 `parse_args.sh`**：更新默认值和配置写入逻辑
2. **修改 `build_deploy.sh`**：
   - 更新 `interactive_configure_web_first_time()` 函数
   - 更新 `configure` 模式的逻辑（如果适用）
3. **同步文档**：更新本文档和 README.md

### 添加新服务脚本

1. **创建脚本文件**（如 `start_newservice.sh`）
2. **封装函数**：主要逻辑封装为 `start_newservice()` 函数
3. **遵循模式**：检查容器、处理镜像、创建目录、启动容器、错误检查
4. **在 `build_deploy.sh` 中提取**：添加到函数提取列表
5. **在主逻辑中调用**：在 `deploy_web()` 中调用（如适用）

### 添加新的系统检查

如果需要添加新的系统环境检查：

1. **在 `system_check.sh` 中添加检查函数**：
   ```bash
   check_new_feature() {
       # 检查逻辑
       if [ 条件不满足 ]; then
           echo "❌ 错误: ..."
           # 提供解决方案或询问用户
           exit 1
       fi
       echo "✅ 检查通过"
   }
   ```

2. **在 `system_check()` 函数中调用**：
   ```bash
   system_check() {
       check_ubuntu_version
       check_memory_peak
       check_new_feature  # 添加新检查
   }
   ```

3. **在 `build_deploy.sh` 中提取新函数**：
   - 添加提取新函数的代码块（参考现有的函数提取模式）

4. **注意事项**：
   - 系统检查仅在评测机部署时执行
   - 所有提示信息使用通俗易懂的语言，避免专业术语
   - 如果检查失败需要用户操作（如升级内核），提供清晰的步骤说明

### 修改配置文件格式

如果修改配置文件的写入格式：

1. **更新 `write_full_config_file()`**：修改写入逻辑
2. **更新 `load_config_files()`**：确保能正确加载新格式
3. **向后兼容**：考虑旧格式配置文件的兼容性

### 修改镜像选择逻辑

镜像选择逻辑在以下位置：

- **nginx**: `start_nginx.sh` 中的 `NGINX_IMAGE_VERSION` 常量
- **ccpcoj-web**: `start_ojweb.sh` 中的镜像选择和回退逻辑
- **ccpcoj-judge2**: `start_judge2.sh` 中的镜像选择和回退逻辑

修改时注意：
- 保持回退机制
- 提供清晰的用户提示
- 验证镜像有效性（大小检查）

## 调试技巧

### 语法检查

```bash
bash -n script.sh
```

### 调试模式

在脚本中添加 `set -x` 查看执行过程：
```bash
bash -x csgoj_deploy.sh web
```

### 测试配置文件写入

```bash
# 测试 write_full_config_file
source parse_args.sh
write_full_config_file "./test.cfg" "web"
cat test.cfg
```

### 测试参数解析

```bash
source parse_args.sh
parse_args --PATH_DATA=/test --OJ_NAME=test
echo "PATH_DATA=$PATH_DATA"
echo "OJ_NAME=$OJ_NAME"
```

## 常见问题

### Q: 为什么配置文件会同时包含 Web 和 Judge 配置？

A: 支持在同一台机器上先后部署 Web 和 Judge，配置文件会合并保存，避免覆盖已有配置。

### Q: `--ignore-config` 的作用是什么？

A: 当配置文件中的参数与当前需求不符时，使用此参数忽略配置文件，仅使用命令行参数和默认值。

### Q: 为什么镜像选择要先尝试拉取而不是直接用本地？

A: 确保使用最新版本，同时保留本地回退机制以应对网络问题。

### Q: 函数提取逻辑是如何工作的？

A: 使用 `awk` 进行括号匹配，提取从函数定义开始到匹配的闭合括号之间的所有内容。

### Q: `deploy_dev.sh` 和 `csgoj_deploy.sh` 的区别？

A: `deploy_dev.sh` 通过 `source` 加载模块化脚本，用于开发测试；`csgoj_deploy.sh` 是完全合并的单文件版本，用于发布。

### Q: 为什么系统检查只在评测机部署时执行？

A: 系统检查（Ubuntu 版本和 memory.peak）是评测机的特殊要求。评测机需要监控程序内存使用情况，这需要 Ubuntu 22.04+ 和内核 5.19+ 的支持。Web 服务器部署不需要这些功能，因此跳过检查以提高部署效率。

### Q: 系统检查失败时如何处理？

A: 
- Ubuntu 版本过低：直接报错退出，提示用户升级系统
- cgroup v2 未启用：报错并提示启用方法
- 内核不支持 memory.peak：
  - 交互模式：询问用户是否升级内核，确认后自动升级（需要重启）
  - 非交互模式：报错并提示手动升级命令

## 注意事项

1. **函数命名**：避免与系统命令冲突
2. **变量作用域**：使用 `local` 限制函数内变量作用域
3. **错误处理**：关键操作都要检查返回码
4. **用户提示**：操作失败时提供清晰的错误信息和解决建议
5. **配置兼容**：修改配置格式时考虑向后兼容
6. **文档同步**：修改功能时同步更新 `README.md` 和本文档
7. **系统检查时机**：系统检查仅在评测机部署时执行，且仅在需要启动新 pod 时执行（重启/删除操作跳过）
8. **用户友好性**：所有用户可见的提示信息使用通俗易懂的语言，避免专业术语（如 "memory.peak"、"cgroup v2"）

