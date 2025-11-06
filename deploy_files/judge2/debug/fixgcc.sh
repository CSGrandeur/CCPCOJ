#!/usr/bin/env bash
# 强力修复 WSL2 下 g++ 缺失 C 头文件（float.h 等）的问题
set -euo pipefail

need_cmd() { command -v "$1" >/dev/null 2>&1; }
require_root() {
  if [ "$(id -u)" -ne 0 ]; then
    echo "[!] 需要 root 权限，请用 sudo 运行：sudo bash $0" >&2
    exit 1
  fi
}

require_root

echo "[+] 检查包管理器..."
if ! need_cmd apt-get; then
  echo "[!] 非 Debian/Ubuntu 系发行版（未检测到 apt-get），本脚本不适用。" >&2
  exit 2
fi

export DEBIAN_FRONTEND=noninteractive
export APT_LISTCHANGES_FRONTEND=none

echo "[+] 解锁/修复 dpkg/apt 状态..."
rm -f /var/lib/dpkg/lock-frontend /var/lib/apt/lists/lock || true
dpkg --configure -a || true
apt-get -y -o Dpkg::Options::="--force-confnew" update

echo "[+] 预修复潜在的损坏依赖..."
apt-get -y -f install || true

echo "[+] 安装/重装基础构建工具链与 C 标头..."
# 核心：libc6-dev 和 linux-libc-dev 负责 /usr/include/float.h 等 C 头文件
# 同时装通用元包，避免版本不匹配：build-essential、gcc、g++、libstdc++-dev
apt-get -y install --no-install-recommends \
  build-essential gcc g++ make cmake pkg-config git \
  libc6 libc6-dev linux-libc-dev libc-dev-bin

echo "[+] 安装可用的 libstdc++-X-dev 版本（自动选择）..."
# 逐个尝试安装可用版本，成功即停止
apt-get -y install libstdc++-14-dev || \
apt-get -y install libstdc++-13-dev || \
apt-get -y install libstdc++-12-dev || \
apt-get -y install libstdc++-11-dev || \
apt-get -y install libstdc++-10-dev || \
apt-get -y install libstdc++-9-dev || true

echo "[+] 再次强制重装关键包，确保头文件落地（幂等）..."
apt-get -y --reinstall install \
  libc6:amd64 libc6-dev:amd64 linux-libc-dev:amd64 libc-dev-bin

# 可选：已经在上一步尝试了多个版本；此处无需重复安装

echo "[+] 校验关键头文件是否存在..."
if [ ! -f /usr/include/float.h ]; then
  echo "[!] 未找到 /usr/include/float.h，进行深度修复：重装相关头文件包（即将继续编译自检）..."
  # 深度修复（尽量避免大规模移除依赖）
  apt-get -y --reinstall install libc6 libc6-dev linux-libc-dev libc-dev-bin || true
fi

# 注意：有些情况下 float.h 由 GCC 内置头目录提供，不在 /usr/include 中。
# 不在这里中止，继续走编译验证与自动切换 GCC 版本的逻辑。

echo "[+] 显示版本信息（用于诊断）..."
(need_cmd lsb_release && lsb_release -a) || true
g++ --version || true
dpkg -l | egrep '(^ii\s+libc6(\:amd64)?\s|^ii\s+libc6-dev|^ii\s+linux-libc-dev|^ii\s+libstdc\+\+(-[0-9]+)?-dev)' || true

echo "[+] 修复本地化（locale）以消除安装警告（可选）..."
apt-get -y install locales || true
locale-gen C.UTF-8 || true
update-locale LANG=C.UTF-8 || true

echo "[+] 进行一次实际编译验证..."
TMPDIR="$(mktemp -d)"
cat > "${TMPDIR}/test.cpp" <<'EOF'
#include <bits/stdc++.h>
using namespace std;
int main() {
  cout << "ok: " << __cplusplus << "\n";
  return 0;
}
EOF

set +e
g++ -std=gnu++17 -O2 -pipe -Wall -Wextra "${TMPDIR}/test.cpp" -o "${TMPDIR}/a.out"
compile_rc=$?
set -e
if [ ${compile_rc} -eq 0 ]; then
  "${TMPDIR}/a.out"
  echo "[✓] 修复完成：g++ 工具链与 C 头文件工作正常。"
  exit 0
fi

echo "[!] 首次编译验证失败，尝试自动切换到 gcc-14 并重试..."
apt-get -y install gcc-14 g++-14 cpp-14 libstdc++-14-dev libgcc-14-dev || true
# 分别为 gcc/g++/cpp 建立并设置 alternatives，避免 master/slave 冲突
update-alternatives --install /usr/bin/gcc gcc /usr/bin/gcc-14 100 || true
update-alternatives --install /usr/bin/g++ g++ /usr/bin/g++-14 100 || true
update-alternatives --install /usr/bin/cpp cpp /usr/bin/cpp-14 100 || true
update-alternatives --set gcc /usr/bin/gcc-14 || true
update-alternatives --set g++ /usr/bin/g++-14 || true
update-alternatives --set cpp /usr/bin/cpp-14 || true

echo "[+] 使用 gcc/g++-14 重新验证编译..."
TMPDIR2="$(mktemp -d)"
cat > "${TMPDIR2}/test.cpp" <<'EOF'
#include <bits/stdc++.h>
using namespace std;
int main() {
  cout << "ok: " << __cplusplus << "\n";
  return 0;
}
EOF
g++ -std=gnu++17 -O2 -pipe -Wall -Wextra "${TMPDIR2}/test.cpp" -o "${TMPDIR2}/a.out"
"${TMPDIR2}/a.out"
echo "[✓] 通过切换 gcc-14 已修复。"