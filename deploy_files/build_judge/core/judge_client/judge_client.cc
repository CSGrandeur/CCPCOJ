// File:   judge_client.cc
// Author: sempr, zhblue
// refacted by CSGrandeur
/*
 * Copyright 2008 sempr <iamsempr@gmail.com>, zhblue<newsclan@gmail.com>
 *
 * Refacted and modified by CSGrandeur<csgrandeur@gmail.com>
 *
 *
 * This file is part of CSGOJ.

 * You should have received a copy of the GNU General Public License
 * along with CSGOJ. if not, see <http://www.gnu.org/licenses/>.
 */

#include <stdio.h>
#include <syslog.h>
#include <errno.h>
#include <fcntl.h>
#include <stdlib.h>
#include <string.h>
#include <dirent.h>
#include <unistd.h>
#include <time.h>
#include <stdarg.h>
#include <ctype.h>
#include <sys/wait.h>
#include <sys/ptrace.h>
#include <sys/types.h>
#include <sys/user.h>
#include <sys/syscall.h>
#include <sys/time.h>
#include <sys/resource.h>
#include <sys/signal.h>
// #include <sys/types.h>
#include <sys/stat.h>
#include <assert.h>
#include "okcalls.h"


#define STD_MB 1048576LL
#define STD_T_LIM 2
#define STD_F_LIM (STD_MB << 5) // default file size limit 32m ,2^5=32
#define STD_M_LIM (STD_MB << 7) // default memory limit 128m ,2^7=128
#define BUFFER_SIZE 4096        // default size of char buffer 5120 bytes
#define BUFFER_SIZE_SM 512
#define LOCKMODE (S_IRUSR | S_IWUSR | S_IRGRP | S_IROTH)

#define OJ_WT0 0 // 提交排队
#define OJ_WT1 1 // 重判排队
#define OJ_CI 2  // 编译中（任务已派发）
#define OJ_RI 3  // 运行中
#define OJ_AC 4  // 答案正确
#define OJ_PE 5  // 格式错误
#define OJ_WA 6  // 答案错误
#define OJ_TL 7  // 时间超限
#define OJ_ML 8  // 内存超限
#define OJ_OL 9  // 输出超限
#define OJ_RE 10 // 运行错误
#define OJ_CE 11 // 编译错误
#define OJ_CO 12 // 编译完成
#define OJ_TR 13 // 测试运行结束
#define OJ_MC 14 // 等待裁判手工确认

#ifdef __i386 // 32位x86寄存器
#define REG_SYSCALL orig_eax
#define REG_RET eax
#define REG_ARG0 ebx
#define REG_ARG1 ecx
#endif

#ifdef __x86_64__ // 64位x86寄存器
#define REG_SYSCALL orig_rax
#define REG_RET rax
#define REG_ARG0 rdi
#define REG_ARG1 rsi

#endif

static int DEBUG = 0;
static char oj_home[BUFFER_SIZE_SM];                                       // 判题系统主目录
static char data_list[BUFFER_SIZE][BUFFER_SIZE];                           // 测试数据列表
static int data_list_len = 0;                                              // 列表长度
static char lock_file[BUFFER_SIZE] = "/home/judge/run0/judge_client.pid";  // 工作目录锁定文件
static char lock_data_folder[BUFFER_SIZE] = "/home/judge/data/0000/lock";  // 测试数据目录锁定文件 20231006
static char local_spj_code_file[BUFFER_SIZE], local_spj_file[BUFFER_SIZE]; // 编译spj使用
static int lock_data_fd = -1;

static int max_running;
static int sleep_time;
static int java_time_bonus = 5;
static int java_memory_bonus = 512;
static char java_xms[BUFFER_SIZE_SM];
static char java_xmx[BUFFER_SIZE_SM];
static char java_xss[BUFFER_SIZE_SM];
static int sim_enable = 0;
static int oi_mode = 0;
static int full_diff = 0;
static int top_diff_bytes = 2048;
static int use_max_time = 0;
static int time_limit_to_total = 1;
static int total_time = 0;

static int http_judge = 1;
static char http_baseurl[BUFFER_SIZE_SM];
static char http_apipath[BUFFER_SIZE_SM];
static char http_loginpath[BUFFER_SIZE_SM];
static char http_username[BUFFER_SIZE_SM];
static char http_password[BUFFER_SIZE_SM];
static int http_download = 1;
static double cpu_compensation = 1.0;

static int shm_run = 0;
static char data_work_dir[BUFFER_SIZE_SM]; // 使用 SHM 时的数据目录
static char data_in_tmp[BUFFER_SIZE_SM];
static char data_out_tmp[BUFFER_SIZE_SM];
static char data_err_tmp[BUFFER_SIZE_SM];

static char record_call = 0;
static int use_ptrace = 1;
static int ignore_esol = 1;
static int turbo_mode = 0;
static const char *tbname = "solution";
static char cc_opt[BUFFER_SIZE_SM];
static char cc_std[BUFFER_SIZE_SM];
static char cpp_std[BUFFER_SIZE_SM];

// global info
int now_sol_id, now_p_id, now_cid;
char now_user_id[BUFFER_SIZE_SM];

int num_of_test = 0;
// static int sleep_tmp;

static int py2 = 1; // caution: py2=1 means default using py3

#define ZOJ_COM

static char jresult[15][4] = {"PD", "PR", "CI", "RJ", "AC", "PE", "WA", "TLE", "MLE", "OLE", "RE", "CE", "CO", "TR", "MC"};
static char lang_ext[22][8] = {"c", "cc", "pas", "java", "rb", "sh", "py",
                               "php", "pl", "cs", "m", "bas", "scm", "c", "cc", "lua", "js", "go", "sql", "f95", "m", "cob"};
// static char buf[BUFFER_SIZE];

int lockfile(int fd)
{
    struct flock fl;
    fl.l_type = F_WRLCK;
    fl.l_start = 0;
    fl.l_whence = SEEK_SET;
    fl.l_len = 0;
    return (fcntl(fd, F_SETLK, &fl));
}
void releasefile(int fd)
{
    struct flock fl;
    fl.l_type = F_UNLCK;
    fl.l_start = 0;
    fl.l_whence = SEEK_SET;
    fl.l_len = 0;
    fcntl(fd, F_SETLK, &fl);
    close(fd);
}
int already_running()
{
    int fd;
    char buf[16];
    fd = open(lock_file, O_RDWR | O_CREAT, LOCKMODE);
    if (fd < 0)
    {
        if (DEBUG)
            printf("%s open fail.\n", lock_file);
        exit(1);
    }
    if (lockfile(fd) < 0)
    {
        if (errno == EACCES || errno == EAGAIN)
        {
            close(fd);
            return 1;
        }

        if (DEBUG)
            printf("%s lock fail.\n", lock_file);
        exit(1);
    }
    if (ftruncate(fd, 0))
        printf("close file fail 0 \n");
    sprintf(buf, "%d", getpid());
    if (write(fd, buf, strlen(buf) + 1) >= BUFFER_SIZE)
        printf("buffer size overflow!...\n");
    return (0);
}
int judgedata_loading()
{
    char buf[16];
    lock_data_fd = open(lock_data_folder, O_RDWR | O_CREAT, LOCKMODE);
    if (lock_data_fd < 0)
    {
        if (DEBUG)
            printf("%s 评测数据锁打开失败.\n", lock_data_folder);
        exit(1);
    }
    if (lockfile(lock_data_fd) < 0)
    {
        if (errno == EACCES || errno == EAGAIN)
        {
            close(lock_data_fd);
            return 1;
        }

        if (DEBUG)
            printf("%s 评测数据锁定失败.\n", lock_data_folder);
        exit(1);
    }
    return 0;
}
void lockdatafolder(int p_id, const char *lock_name)
{
    // ********** 20231006: 目录加锁避免多进程同写
    sprintf(lock_data_folder, "%s/data/%d/datalock", oj_home, p_id);
    int wait_lock_cnt = 0;
    while (judgedata_loading())
    {
        if (wait_lock_cnt++ % 5 == 0)
        {
            printf("%d 数据目录 %s 正在写入，稍后进行评测. %ds\n", p_id, lock_name, wait_lock_cnt << 1);
        }
        sleep(2);
    }
}
void print_arm_regs(long long unsigned int *d)
{
    for (int i = 0; i < 32; i++)
    {
        printf("[%d]:%lld ", i, d[i] % CALL_ARRAY_SIZE);
    }
    printf("\n");
}
int data_list_has(const char file[])
{
    for (int i = 0; i < data_list_len; i++)
    {
        if (strcmp(data_list[i], file) == 0)
            return 1;
    }
    return 0;
}
int data_list_add(char *file)
{
    if (data_list_len < BUFFER_SIZE - 1)
    {
        strcpy(data_list[data_list_len], file);
        data_list_len++;
        return 0;
    }
    else
    {
        return 1;
    }
}
long get_file_size(const char *filename)
{
    struct stat f_stat;

    if (stat(filename, &f_stat) == -1)
    {
        return 0;
    }

    return (long)f_stat.st_size;
}

long get_file_time(const char *filename)
{
    struct stat f_stat;

    if (stat(filename, &f_stat) == -1)
    {
        return 0;
    }

    return (long)f_stat.st_mtime;
}
void write_log(const char *_fmt, ...)
{
    va_list ap;
    char fmt[BUFFER_SIZE];
    strncpy(fmt, _fmt, BUFFER_SIZE);
    char buffer[BUFFER_SIZE];
    //      time_t          t = time(NULL);
    // int l;
    sprintf(buffer, "%s/log/client.log", oj_home);
    FILE *fp = fopen(buffer, "ae+");
    if (fp == NULL)
    {
        fprintf(stderr, "%s/log/client.log openfile error!\n", oj_home);
        exit(-6);
    }
    va_start(ap, _fmt);
    // l =
    vsprintf(buffer, fmt, ap);
    fprintf(fp, "%s\n", buffer);
    if (DEBUG)
        printf("%s\n", buffer);
    va_end(ap);
    fclose(fp);
}
int execute_cmd(const char *fmt, ...)
{
    // 执行命令获得返回值
    char cmd[BUFFER_SIZE];

    int ret = 0;
    va_list ap;

    va_start(ap, fmt);
    vsprintf(cmd, fmt, ap);
    if (DEBUG)
    {
        printf("%s\n", cmd);
    }
    ret = system(cmd);
    va_end(ap);
    return ret;
}

const int call_array_size = CALL_ARRAY_SIZE;
unsigned int call_id = 0;
int call_counter[call_array_size] = {0};
static char LANG_NAME[BUFFER_SIZE];
void init_syscalls_limits(int lang)
{
    // 白名单初始化
    int i;
    memset(call_counter, 0, sizeof(call_counter));
    if (DEBUG)
        write_log("init_call_counter:%d", lang);
    if (record_call)
    { // recording for debuging
        for (i = 0; i < call_array_size; i++)
        {
            call_counter[i] = 0;
        }
    }
    else if (lang <= LANG_CPP || lang == LANG_CLANG || lang == LANG_CLANGPP)
    { // C & C++
        for (i = 0; i == 0 || LANG_CV[i]; i++)
        {
            call_counter[LANG_CV[i]] = HOJ_MAX_LIMIT;
        }
    }
    else if (lang == LANG_PASCAL)
    { // Pascal
        for (i = 0; i == 0 || LANG_PV[i]; i++)
            call_counter[LANG_PV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_JAVA)
    { // Java
        for (i = 0; i == 0 || LANG_JV[i]; i++)
            call_counter[LANG_JV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_RUBY)
    { // Ruby
        for (i = 0; i == 0 || LANG_RV[i]; i++)
            call_counter[LANG_RV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_BASH)
    { // Bash
        for (i = 0; i == 0 || LANG_BV[i]; i++)
            call_counter[LANG_BV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_PYTHON)
    { // Python
        for (i = 0; i == 0 || LANG_YV[i]; i++)
            call_counter[LANG_YV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_PHP)
    { // php
        for (i = 0; i == 0 || LANG_PHV[i]; i++)
            call_counter[LANG_PHV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_PERL)
    { // perl
        for (i = 0; i == 0 || LANG_PLV[i]; i++)
            call_counter[LANG_PLV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_CSHARP)
    { // mono c#
        for (i = 0; i == 0 || LANG_CSV[i]; i++)
            call_counter[LANG_CSV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_OBJC)
    { // objective c
        for (i = 0; i == 0 || LANG_OV[i]; i++)
            call_counter[LANG_OV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_FREEBASIC)
    { // free basic
        for (i = 0; i == 0 || LANG_BASICV[i]; i++)
            call_counter[LANG_BASICV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_SCHEME)
    { // scheme guile
        for (i = 0; i == 0 || LANG_SV[i]; i++)
            call_counter[LANG_SV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_LUA)
    { // lua
        for (i = 0; i == 0 || LANG_LUAV[i]; i++)
            call_counter[LANG_LUAV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_JS)
    { // nodejs
        for (i = 0; i == 0 || LANG_JSV[i]; i++)
            call_counter[LANG_JSV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_GO)
    { // go
        for (i = 0; i == 0 || LANG_GOV[i]; i++)
            call_counter[LANG_GOV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_SQL)
    { // go
        for (i = 0; i == 0 || LANG_SQLV[i]; i++)
            call_counter[LANG_SQLV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_FORTRAN)
    { // go
        for (i = 0; i == 0 || LANG_FV[i]; i++)
            call_counter[LANG_FV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_MATLAB)
    { // matlab
        for (i = 0; i == 0 || LANG_MV[i]; i++)
            call_counter[LANG_MV[i]] = HOJ_MAX_LIMIT;
    }
    else if (lang == LANG_COBOL)
    { // cobol
        for (i = 0; i == 0 || LANG_CBV[i]; i++)
            call_counter[LANG_CBV[i]] = HOJ_MAX_LIMIT;
    }

    call_counter[SYS_execve % call_array_size] = 1;

    printf("SYS_execve:%d\n", SYS_execve % call_array_size);
}

int after_equal(char *c)
{
    int i = 0;
    for (; c[i] != '\0' && c[i] != '='; i++)
        ;
    return ++i;
}
void trim(char *c)
{
    char buf[BUFFER_SIZE];
    char *start, *end;
    strcpy(buf, c);
    start = buf;
    while (isspace(*start))
        start++;
    end = start;
    while (!isspace(*end))
        end++;
    *end = '\0';
    strcpy(c, start);
}
bool read_buf(char *buf, const char *key, char *value)
{
    if (strncmp(buf, key, strlen(key)) == 0)
    {
        strcpy(value, buf + after_equal(buf));
        trim(value);
        if (DEBUG)
            printf("%s=%s\n", key, value);
        return 1;
    }
    return 0;
}
void read_double(char *buf, const char *key, double *value)
{
    char buf2[BUFFER_SIZE];
    if (read_buf(buf, key, buf2))
        if (1 != sscanf(buf2, "%lf", value))
            printf("double value read fail\n");
}

void read_int(char *buf, const char *key, int *value)
{
    char buf2[BUFFER_SIZE];
    if (read_buf(buf, key, buf2))
        if (1 != sscanf(buf2, "%d", value))
            printf("int value read fail\n");
}

FILE *read_cmd_output(const char *fmt, ...)
{
    char cmd[BUFFER_SIZE];

    FILE *ret = NULL;
    va_list ap;

    va_start(ap, fmt);
    vsprintf(cmd, fmt, ap);
    va_end(ap);
    if (DEBUG)
        printf("%s\n", cmd);
    ret = popen(cmd, "r");

    return ret;
}
// read the configue file
void init_judge_conf() // 读取判题主目录etc中的配置文件judge.conf
{
    FILE *fp = NULL;
    char buf[BUFFER_SIZE];
    max_running = 3;
    sleep_time = 3;
    strcpy(java_xms, "-Xms32m");
    strcpy(java_xmx, "-Xmx256m");
    strcpy(java_xss, "-Xss64m");
    strcpy(cc_std, "-std=c99");
    strcpy(cc_opt, "-O2");
    if (__GNUC__ > 9 || (__GNUC__ == 9 && __GNUC_MINOR__ >= 3))
    {
        // ubuntu20.04 introduce g++9.3
        strcpy(cc_std, "-std=c17");
        strcpy(cpp_std, "-std=c++14"); // CCF NOI change settings for NOIP to C++14 on 2021-9-1
    }
    else
    {
        strcpy(cc_std, "-std=c99");
        strcpy(cpp_std, "-std=c++11");
    }
    sprintf(buf, "%s/etc/judge.conf", oj_home);
    fp = fopen("./etc/judge.conf", "re");
    if (fp != NULL)
    {
        while (fgets(buf, BUFFER_SIZE - 1, fp))
        {
            read_int(buf, "OJ_JAVA_TIME_BONUS", &java_time_bonus);
            read_int(buf, "OJ_JAVA_MEMORY_BONUS", &java_memory_bonus);
            read_int(buf, "OJ_SIM_ENABLE", &sim_enable);
            read_buf(buf, "OJ_JAVA_XMS", java_xms);
            read_buf(buf, "OJ_JAVA_XMX", java_xmx);
            read_buf(buf, "OJ_JAVA_XSS", java_xss);
            read_int(buf, "OJ_HTTP_JUDGE", &http_judge);
            read_buf(buf, "OJ_HTTP_BASEURL", http_baseurl);
            read_buf(buf, "OJ_HTTP_APIPATH", http_apipath);
            read_buf(buf, "OJ_HTTP_LOGINPATH", http_loginpath);
            read_buf(buf, "OJ_HTTP_USERNAME", http_username);
            read_buf(buf, "OJ_HTTP_PASSWORD", http_password);
            read_int(buf, "OJ_HTTP_DOWNLOAD", &http_download);
            read_int(buf, "OJ_OI_MODE", &oi_mode);
            read_int(buf, "OJ_FULL_DIFF", &full_diff);
            read_int(buf, "OJ_TOP_DIFF_BYTES", &top_diff_bytes);
            read_int(buf, "OJ_SHM_RUN", &shm_run);
            read_int(buf, "OJ_USE_MAX_TIME", &use_max_time);
            read_int(buf, "OJ_TIME_LIMIT_TO_TOTAL", &time_limit_to_total);
            read_int(buf, "OJ_USE_PTRACE", &use_ptrace);
            read_int(buf, "OJ_IGNORE_ESOL", &ignore_esol);
            read_int(buf, "OJ_TURBO_MODE", &turbo_mode);
            read_double(buf, "OJ_CPU_COMPENSATION", &cpu_compensation);
            read_buf(buf, "OJ_CC_STD", cc_std);
            read_buf(buf, "OJ_CPP_STD", cpp_std);
            read_buf(buf, "OJ_CC_OPT", cc_opt);
        }
        fclose(fp);
    }
    if (strcmp(http_username, "IP") == 0)
    {
        FILE *fjobs = fopen("/etc/hostname", "r");
        if (1 != fscanf(fjobs, "%s", http_username))
            printf("IP/HOSTNAME read fail...\n");
        pclose(fjobs);
    }
    if (turbo_mode == 2)
        tbname = "solution2";
}
int isInFile(const char fname[])
{
    int l = strlen(fname);
    if (l <= 3 || strcmp(fname + l - 3, ".in") != 0)
        return 0;
    else
        return l - 3;
}
int inFile(const struct dirent *dp)
{ // 获得测试数据目录中测试数据列表
    int l = strlen(dp->d_name);
    if (DEBUG)
        printf("file name:%s\n", dp->d_name);
    if (DEBUG)
        printf("ext name:%s\n", dp->d_name + l - 3);
    int in_file = isInFile(dp->d_name);
    int valid_data = data_list_has(dp->d_name);
    if (DEBUG)
        printf("in_file:%d\tvalid_data:%d\n", in_file, valid_data);
    return in_file && valid_data;
}

void find_next_nonspace(int &c1, int &c2, FILE *&f1, FILE *&f2, int &ret)
{
    // Find the next non-space character or \n.
    while ((isspace(c1)) || (isspace(c2)))
    {
        if (c1 != c2)
        {
            if (c2 == EOF)
            {
                do
                {
                    c1 = fgetc(f1);
                } while (isspace(c1));
                continue;
            }
            else if (c1 == EOF)
            {
                do
                {
                    c2 = fgetc(f2);
                } while (isspace(c2));
                continue;
            }
            else if (ignore_esol)
            {
                if (isspace(c1) && isspace(c2))
                {
                    while (c2 == '\n' && isspace(c1) && c1 != '\n')
                        c1 = fgetc(f1);
                    while (c1 == '\n' && isspace(c2) && c2 != '\n')
                        c2 = fgetc(f2);
                }
                else
                {
                    if (DEBUG)
                        printf("%d=%c\t%d=%c", c1, c1, c2, c2);
                    ;
                    ret = OJ_PE;
                }
            }
            else if (!ignore_esol)
            {
                if ((c1 == '\r' && c2 == '\n'))
                {
                    c1 = fgetc(f1);
                }
                else if ((c2 == '\r' && c1 == '\n'))
                {
                    c2 = fgetc(f2);
                }
                else
                {
                    if (DEBUG)
                        printf("%d=%c\t%d=%c", c1, c1, c2, c2);
                    ;
                    ret = OJ_PE;
                }
            }
        }
        if (isspace(c1))
        {
            c1 = fgetc(f1);
        }
        if (isspace(c2))
        {
            c2 = fgetc(f2);
        }
    }
}

/***
 int compare_diff(const char *file1,const char *file2){
 char diff[1024];
 sprintf(diff,"diff -q -B -b -w --strip-trailing-cr %s %s",file1,file2);
 int d=system(diff);
 if (d) return OJ_WA;
 sprintf(diff,"diff -q -B --strip-trailing-cr %s %s",file1,file2);
 int p=system(diff);
 if (p) return OJ_PE;
 else return OJ_AC;
 }
 */
const char *getFileNameFromPath(const char *path)
{
    for (int i = strlen(path); i >= 0; i--)
    {
        if (path[i] == '/')
            return &path[i + 1];
    }
    return path;
}

void make_diff_out_full(FILE *f1, FILE *f2, int c1, int c2, const char *path, const char *infile, const char *userfile)
{
    execute_cmd("echo '========[%s]========='>>diff.out", getFileNameFromPath(path));
    execute_cmd("echo  '\\n------test in top 512 bytes------'>>diff.out");
    execute_cmd("head -c %d '%s' >> diff.out", top_diff_bytes, infile);
    execute_cmd("echo  '\\n------diff out top %d bytes-----'>>diff.out", top_diff_bytes);
    execute_cmd("head -c %d '%s' > top_%d_bytes_of_test_data.out", top_diff_bytes, path, top_diff_bytes);
    execute_cmd("head -c %d '%s' > top_%d_bytes_of_user_code.out", top_diff_bytes, userfile, top_diff_bytes);
    execute_cmd("diff -u -s top_%d_bytes_of_test_data.out top_%d_bytes_of_user_code.out --strip-trailing-cr >> diff.out", top_diff_bytes, top_diff_bytes);
    execute_cmd("echo  '\\n=============================='>>diff.out");
}
void make_diff_out_simple(FILE *f1, FILE *f2, int c1, int c2, const char *path, const char *userfile)
{
    execute_cmd("echo '========[%s]========='>>diff.out", getFileNameFromPath(path));
    execute_cmd("echo 'Expected                              |    Yours'>>diff.out");
    execute_cmd("diff '%s' %s -y --strip-trailing-cr|head -c 100>>diff.out", path, userfile);
    execute_cmd("echo '\n=============================='>>diff.out");
}

/*
 * translated from ZOJ judger r367
 * http://code.google.com/p/zoj/source/browse/trunk/judge_client/client/text_checker.cc#25
 * 参考zoj的文件流式比较器，有更低的内存占用
 */
int compare_zoj(const char *file1, const char *file2, const char *infile, const char *userfile)
{
    int ret = OJ_AC;
    int c1, c2;
    FILE *f1, *f2;
    f1 = fopen(file1, "re");
    f2 = fopen(file2, "re");
    if (!f1 || !f2)
    {
        ret = OJ_RE;
    }
    else
    {
        for (;;)
        {
            // Find the first non-space character at the beginning of line.
            // Blank lines are skipped.
            c1 = fgetc(f1);
            c2 = fgetc(f2);
            find_next_nonspace(c1, c2, f1, f2, ret);
            // Compare the current line.
            for (;;)
            {
                // Read until 2 files return a space or 0 together.
                while ((!isspace(c1) && c1) || (!isspace(c2) && c2))
                {
                    if (c1 == EOF && c2 == EOF)
                    {
                        goto end;
                    }
                    if (c1 == EOF || c2 == EOF)
                    {
                        break;
                    }
                    if (c1 != c2)
                    {
                        // Consecutive non-space characters should be all exactly the ifconfig|grep 'inet'|awk -F: '{printf $2}'|awk  '{printf $1}'same
                        ret = OJ_WA;
                        goto end;
                    }
                    c1 = fgetc(f1);
                    c2 = fgetc(f2);
                }
                find_next_nonspace(c1, c2, f1, f2, ret);
                if (c1 == EOF && c2 == EOF)
                {
                    goto end;
                }
                if (c1 == EOF || c2 == EOF)
                {
                    ret = OJ_WA;
                    goto end;
                }

                if ((c1 == '\n' || !c1) && (c2 == '\n' || !c2))
                {
                    break;
                }
            }
        }
    }
end:
    if (ret == OJ_WA || ret == OJ_PE)
    {
        if (full_diff)
            make_diff_out_full(f1, f2, c1, c2, file1, infile, userfile);
        else
            make_diff_out_simple(f1, f2, c1, c2, file1, userfile);
    }
    if (f1)
        fclose(f1);
    if (f2)
        fclose(f2);
    return ret;
}

void delnextline(char s[])
{
    int L;
    L = strlen(s);
    while (L > 0 && (s[L - 1] == '\n' || s[L - 1] == '\r'))
        s[--L] = 0;
}

int compare(const char *file1, const char *file2, const char *infile, const char *userfile)
{
#ifdef ZOJ_COM
    // compare ported and improved from zoj don't limit file size
    return compare_zoj(file1, file2, infile, userfile);
#endif
#ifndef ZOJ_COM
    // the original compare from the first version of csgoj has file size limit  原始的内存中比较，速度更快但耗用更多内存
    // and waste memory
    FILE *f1, *f2;
    char *s1, *s2, *p1, *p2;
    int PEflg;
    s1 = new char[STD_F_LIM + 512];
    s2 = new char[STD_F_LIM + 512];
    if (!(f1 = fopen(file1, "re")))
        return OJ_AC;
    for (p1 = s1; EOF != fscanf(f1, "%s", p1);)
        while (*p1)
            p1++;
    fclose(f1);
    if (!(f2 = fopen(file2, "re")))
        return OJ_RE;
    for (p2 = s2; EOF != fscanf(f2, "%s", p2);)
        while (*p2)
            p2++;
    fclose(f2);
    if (strcmp(s1, s2) != 0)
    {
        //              printf("A:%s\nB:%s\n",s1,s2);
        delete[] s1;
        delete[] s2;

        return OJ_WA;
    }
    else
    {
        f1 = fopen(file1, "re");
        f2 = fopen(file2, "re");
        PEflg = 0;
        while (PEflg == 0 && fgets(s1, STD_F_LIM, f1) && fgets(s2, STD_F_LIM, f2))
        {
            delnextline(s1);
            delnextline(s2);
            if (strcmp(s1, s2) == 0)
                continue;
            else
                PEflg = 1;
        }
        delete[] s1;
        delete[] s2;
        fclose(f1);
        fclose(f2);
        if (PEflg)
            return OJ_PE;
        else
            return OJ_AC;
    }
#endif
}

bool check_login() // http模式中检测是否已经登陆
{
    const char *cmd =
        " wget --post-data=\"checklogin=1\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O - \"%s%s\"";
    int ret = 0;
    FILE *fjobs = read_cmd_output(cmd, http_baseurl, http_apipath);
    if (1 != fscanf(fjobs, "%d", &ret))
        printf("http login fail..");
    pclose(fjobs);

    return ret;
}
void login() // http登陆
{
    if (!check_login())
    {
        const char *cmd =
            "wget --post-data=\"user_id=%s&password=%s\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O - \"%s%s\"";
        FILE *fjobs = read_cmd_output(cmd, http_username, http_password,
                                      http_baseurl, http_loginpath);
        // fscanf(fjobs,"%d",&ret);
        pclose(fjobs);
    }
}
void _update_solution_http(int solution_id, int result, int time, int memory, int sim, int sim_s_id, double pass_rate)
{
    login();
    const char *cmd =
        " wget --post-data=\"update_solution=1&sid=%d&result=%d&time=%d&memory=%d&sim=%d&simid=%d&pass_rate=%f\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O - \"%s%s\"";
    FILE *fjobs = read_cmd_output(cmd, solution_id, result, time, memory, sim,
                                  sim_s_id, pass_rate, http_baseurl, http_apipath);
    // fscanf(fjobs,"%d",&ret);
    pclose(fjobs);
}
void update_solution(int solution_id, int result, int time, int memory, int sim, int sim_s_id, double pass_rate)
{
    if (result == OJ_TL && memory == 0)
    {
        result = OJ_ML;
    }
    if (http_judge)
    {
        _update_solution_http(solution_id, result, time, memory, sim, sim_s_id, pass_rate);
    }
}
/* write compile error message back to database */

// urlencoded function copied from http://www.geekhideout.com/urlcode.shtml
/* Converts a hex character to its integer value */
char from_hex(char ch)
{
    return isdigit(ch) ? ch - '0' : tolower(ch) - 'a' + 10;
}

/* Converts an integer value to its hex character*/
char to_hex(char code)
{
    static char hex[] = "0123456789abcdef";
    return hex[code & 15];
}

/* Returns a url-encoded version of str */
/* IMPORTANT: be sure to free() the returned string after use */
// 用url编码字符串
char *url_encode(char *str)
{
    char *pstr = str, *buf = (char *)malloc(strlen(str) * 3 + 1), *pbuf = buf;
    while (*pstr)
    {
        if (isalnum(*pstr) || *pstr == '-' || *pstr == '_' || *pstr == '.' || *pstr == '~')
            *pbuf++ = *pstr;
        else if (*pstr == ' ')
            *pbuf++ = '+';
        else
            *pbuf++ = '%', *pbuf++ = to_hex(*pstr >> 4), *pbuf++ = to_hex(*pstr & 15);
        pstr++;
    }
    *pbuf = '\0';
    return buf;
}

void _addceinfo_http(int solution_id)
{

    char ceinfo[(1 << 16)], *cend;
    char *ceinfo_encode;
    FILE *fp = fopen("ce.txt", "re");

    cend = ceinfo;
    while (fgets(cend, 1024, fp))
    {
        cend += strlen(cend);
        if (cend - ceinfo > 16384)
            break;
    }
    *cend = '\0';
    fclose(fp);
    ceinfo_encode = url_encode(ceinfo);
    FILE *ce = fopen("ce.post", "we");
    fprintf(ce, "addceinfo=1&sid=%d&ceinfo=%s", solution_id, ceinfo_encode);
    fclose(ce);
    free(ceinfo_encode);

    const char *cmd =
        " wget --post-file=\"ce.post\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O - \"%s%s\"";
    FILE *fjobs = read_cmd_output(cmd, http_baseurl, http_apipath);
    // fscanf(fjobs,"%d",&ret);
    pclose(fjobs);
}
void addceinfo(int solution_id)
{
    if (http_judge)
    {
        _addceinfo_http(solution_id);
    }
}
/* write runtime error message back to database */

void _addreinfo_http(int solution_id, const char *filename)
{

    char reinfo[(1 << 16)], *rend;
    char *reinfo_encode;
    FILE *fp = fopen(filename, "re");

    rend = reinfo;
    while (fgets(rend, 1024, fp))
    {
        rend += strlen(rend);
        if (rend - reinfo > 16384)
        {
            break;
        }
    }
    *rend = '\0';
    fclose(fp);
    reinfo_encode = url_encode(reinfo);
    FILE *re = fopen("re.post", "we");
    fprintf(re, "addreinfo=1&sid=%d&reinfo=%s", solution_id, reinfo_encode);
    fclose(re);
    free(reinfo_encode);

    const char *cmd =
        " wget --post-file=\"re.post\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O - \"%s%s\"";
    FILE *fjobs = read_cmd_output(cmd, http_baseurl, http_apipath);
    // fscanf(fjobs,"%d",&ret);
    pclose(fjobs);
}
void addreinfo(int solution_id)
{
    if (http_judge)
    {
        _addreinfo_http(solution_id, data_err_tmp);
    }
}

void adddiffinfo(int solution_id)
{
    if (http_judge)
    {
        _addreinfo_http(solution_id, "diff.out");
    }
}
void addcustomout(int solution_id)
{
    if (http_judge)
    {

        _addreinfo_http(solution_id, data_out_tmp);
    }
}

void _update_user_http(char *user_id)
{

    const char *cmd =
        " wget --post-data=\"updateuser=1&user_id=%s\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O - \"%s%s\"";
    FILE *fjobs = read_cmd_output(cmd, user_id, http_baseurl, http_apipath);
    // fscanf(fjobs,"%d",&ret);
    pclose(fjobs);
}
void update_user(char *user_id)
{
    if (http_judge)
    {
        _update_user_http(user_id);
    }
}

void _update_problem_http(int pid, int cid)
{
    const char *cmd =
        " wget --post-data=\"updateproblem=1&pid=%d\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O - \"%s%s\"";
    FILE *fjobs = read_cmd_output(cmd, pid, http_baseurl, http_apipath);
    // fscanf(fjobs,"%d",&ret);
    pclose(fjobs);
}

void update_problem(int pid, int cid)
{
    if (http_judge)
    {
        _update_problem_http(pid, cid);
    }
}

void ClearRun(char *work_dir)
{
    // 清理可能存在的热加载目录
    if (chdir(work_dir))
        exit(-1);
    execute_cmd("/bin/rmdir %s/* ", work_dir);
    execute_cmd("/bin/rmdir %s/log/* ", work_dir);
}

void clean_workdir(char *work_dir)
{
    ClearRun(work_dir);
    if (DEBUG)
    {
        execute_cmd("/bin/rmdir %s/log/* 2>/dev/null", work_dir);
        execute_cmd("/bin/rm -rf %s/log/* 2>/dev/null", work_dir);
        execute_cmd("mkdir %s/log/ 2>/dev/null", work_dir);
        execute_cmd("/bin/mv %s/* %s/log/ 2>/dev/null", work_dir, work_dir);
    }
    else
    {
        execute_cmd("mkdir %s/log/ 2>/dev/null", work_dir);
        execute_cmd("/bin/mv %s/* %s/log/ 2>/dev/null", work_dir, work_dir);
        execute_cmd("/bin/rmdir %s/log/* 2>/dev/null", work_dir);
        execute_cmd("/bin/rm -rf %s/log/* 2>/dev/null", work_dir);
    }
    if (shm_run)
    {
        execute_cmd("/bin/rm -f %s/*.in", data_work_dir);
        execute_cmd("/bin/rm -f %s/*.out", data_work_dir);
        execute_cmd("/bin/rm -f %s/Main*", data_work_dir);
    }
}
int compile(int lang, char *work_dir)
{
    // if (lang == LANG_PYTHON || lang == LANG_JS)
    //     return 0; // python / js don't compile
    if (lang == LANG_JS)    // python 仍需要做语法检查，避免语法错误变RE
        return 0; 
    int pid;
    char fmax_errors[BUFFER_SIZE];

    if (__GNUC__ > 4 || (__GNUC__ == 4 && __GNUC_MINOR__ >= 8))
    {
        sprintf(fmax_errors, "-fmax-errors=10");
    }
    else
    {
        sprintf(fmax_errors, "-Wformat");
    }
    const char *CP_C[] = {"gcc", "-fno-asm", cc_opt, fmax_errors, cc_std,
                          "-Wall", "--static", "-DONLINE_JUDGE", "-o", "Main", "Main.c", "-lm", NULL}; // 看起来别扭，但是gcc非要-lm选项在Main.c后面才认
    const char *CP_X[] = {"g++", "-fno-asm", cc_opt, fmax_errors, cpp_std,
                          "-Wall", "-lm", "--static", "-DONLINE_JUDGE", "-o", "Main", "Main.cc", NULL};

    const char *CP_P[] =
        {"fpc", "Main.pas", "-Cs32000000", "-Sh", "-O2", "-Co", "-Ct", "-Ci", NULL};
    //      const char * CP_J[] = { "javac", "-J-Xms32m", "-J-Xmx256m","-encoding","UTF-8", "Main.java",NULL };

    const char *CP_R[] = {"ruby", "-c", "Main.rb", NULL};
    const char *CP_B[] = {"chmod", "+rx", "Main.sh", NULL};
    const char *CP_Y[] = {"python3", "pycheck.py", NULL};
    const char *CP_PH[] = {"php", "-l", "Main.php", NULL};
    const char *CP_PL[] = {"perl", "-c", "Main.pl", NULL};
    const char *CP_CS[] = {"mcs", "-codepage:utf8", "-warn:0", "Main.cs", NULL};
    const char *CP_OC[] = {"gcc", "-o", "Main", "Main.m",
                           "-fconstant-string-class=NSConstantString", "-I",
                           "/usr/include/GNUstep/", "-L", "/usr/lib/GNUstep/Libraries/",
                           "-lobjc", "-lgnustep-base", NULL};
    const char *CP_BS[] = {"fbc", "-lang", "qb", "Main.bas", NULL};
    const char *CP_CLANG[] = {"clang", "Main.c", "-o", "Main", "-ferror-limit=10", "-fno-asm", "-Wall",
                              "-lm", "--static", "-std=c99", "-DONLINE_JUDGE", NULL};
    const char *CP_CLANG_CPP[] = {"clang++", "Main.cc", "-o", "Main", "-ferror-limit=10", "-fno-asm", "-Wall",
                                  "-lm", "--static", "-std=c++0x", "-DONLINE_JUDGE", NULL};
    const char *CP_LUA[] = {"luac", "-o", "Main", "Main.lua", NULL};
    // const char * CP_JS[] = { "js24","-c", "Main.js", NULL };
    const char *CP_GO[] = {"go", "build", "-o", "Main", "Main.go", NULL};
    const char *CP_FORTRAN[] = {"f95", "-static", "-o", "Main", "Main.f95", NULL};
    const char *CP_COBOL[] = {"cobc", "-x", "-static", "-o", "Main", "Main.cob", NULL};

    char *const envp[] = {(char *const)"PYTHONIOENCODING=utf-8",
                          (char *const)"USER=judge",
                          (char *const)"GOCACHE=/tmp",
                          (char *const)"LANG=zh_CN.UTF-8",
                          (char *const)"LANGUAGE=zh_CN.UTF-8",
                          (char *const)"LC_ALL=zh_CN.utf-8", NULL};
    char javac_buf[7][32];
    char *CP_J[8];

    for (int i = 0; i < 7; i++)
        CP_J[i] = javac_buf[i];

    sprintf(CP_J[0], "javac");
    sprintf(CP_J[1], "-J%s", java_xms);
    sprintf(CP_J[2], "-J%s", java_xmx);
    sprintf(CP_J[3], "-J%s", java_xss);
    sprintf(CP_J[4], "-encoding");
    sprintf(CP_J[5], "UTF-8");
    sprintf(CP_J[6], "Main.java");
    CP_J[7] = (char *)NULL;

    pid = fork();
    if (pid == 0)
    {
        struct rlimit LIM;
        int cpu = 50;
        if (lang == 3)
            cpu = 60;
        LIM.rlim_max = cpu;
        LIM.rlim_cur = cpu;
        setrlimit(RLIMIT_CPU, &LIM);
        alarm(0);
        if (cpu > 0)
            alarm(cpu);
        else
            alarm(1);
        LIM.rlim_max = 500 * STD_MB;
        LIM.rlim_cur = 500 * STD_MB;
        setrlimit(RLIMIT_FSIZE, &LIM);

        if (lang == 2 || lang == 3 || lang == 17)
        {

#ifdef __i386__
            LIM.rlim_max = STD_MB << 11;
            LIM.rlim_cur = STD_MB << 11;
#endif
#ifdef __x86_64__
            LIM.rlim_max = STD_MB << 12;
            LIM.rlim_cur = STD_MB << 12;
#endif
        }
        else
        {
            LIM.rlim_max = STD_MB << 11;
            LIM.rlim_cur = STD_MB << 11;
        }
        if (lang != 3)
            setrlimit(RLIMIT_AS, &LIM);
        if (lang != 2 && lang != 11)
        {
            stderr = freopen("ce.txt", "w", stderr);
            // freopen("/dev/null", "w", stdout);
        }
        else
        {
            stdout = freopen("ce.txt", "w", stdout);
        }
        execute_cmd("/bin/chown judge %s ", work_dir);
        execute_cmd("/bin/chmod 750 %s ", work_dir);

        while (setgid(1536) != 0)
            sleep(1);
        while (setuid(1536) != 0)
            sleep(1);
        while (setresuid(1536, 1536, 1536) != 0)
            sleep(1);

        switch (lang)
        {
        case LANG_C:
            execvp(CP_C[0], (char *const *)CP_C);
            break;
        case LANG_CPP:
            execvp(CP_X[0], (char *const *)CP_X);
            break;
        case LANG_PASCAL:
            execvp(CP_P[0], (char *const *)CP_P);
            break;
        case LANG_JAVA:
            execvp(CP_J[0], (char *const *)CP_J);
            break;
        case LANG_RUBY:
            execvp(CP_R[0], (char *const *)CP_R);
            break;
        case LANG_BASH:
            execvp(CP_B[0], (char *const *)CP_B);
            break;
        case LANG_PYTHON:
            execute_cmd("/bin/cp %s/etc/pycheck.py %s/pycheck.py", oj_home, work_dir);
            execvp(CP_Y[0], (char *const *)CP_Y);
            break;
        case LANG_PHP:
            execvp(CP_PH[0], (char *const *)CP_PH);
            break;
        case LANG_PERL:
            execvp(CP_PL[0], (char *const *)CP_PL);
            break;
        case LANG_CSHARP:
            execvp(CP_CS[0], (char *const *)CP_CS);
            break;

        case LANG_OBJC:
            execvp(CP_OC[0], (char *const *)CP_OC);
            break;
        case LANG_FREEBASIC:
            execvp(CP_BS[0], (char *const *)CP_BS);
            break;
        case LANG_CLANG:
            execvp(CP_CLANG[0], (char *const *)CP_CLANG);
            break;
        case LANG_CLANGPP:
            execvp(CP_CLANG_CPP[0], (char *const *)CP_CLANG_CPP);
            break;
        case LANG_LUA:
            execvp(CP_LUA[0], (char *const *)CP_LUA);
            break;
        // case 16:
        //     execvp(CP_JS[0], (char * const *) CP_JS);
        //     break;
        case LANG_GO:
            execvpe(CP_GO[0], (char *const *)CP_GO, envp);
            break;
        case LANG_FORTRAN:
            execvp(CP_FORTRAN[0], (char *const *)CP_FORTRAN);
            break;
        case LANG_COBOL:
            execvp(CP_COBOL[0], (char *const *)CP_COBOL);
            break;
        default:
            printf("nothing to do!\n");
        }
        if (DEBUG)
            printf("compile end!\n");
        // exit(!system("cat ce.txt"));
        exit(0);
    }
    else
    {
        int status = 0;

        waitpid(pid, &status, 0);
        if (lang > 3 && lang < 7)
            status = get_file_size("ce.txt");
        if (DEBUG)
            printf("status=%d\n", status);
        execute_cmd("/bin/umount -l bin usr lib lib64 etc/alternatives dev 2>/dev/null");
        ClearRun(work_dir);

        return status;
    }
}

int get_proc_status(int pid, const char *mark)
{
    FILE *pf;
    char fn[BUFFER_SIZE], buf[BUFFER_SIZE];
    int ret = 0;
    sprintf(fn, "/proc/%d/status", pid);
    pf = fopen(fn, "re");
    int m = strlen(mark);
    while (pf && fgets(buf, BUFFER_SIZE - 1, pf))
    {

        buf[strlen(buf) - 1] = 0;
        if (strncmp(buf, mark, m) == 0)
        {
            if (1 != sscanf(buf + m + 1, "%d", &ret))
                printf("proc read fail\n");
        }
    }
    if (pf)
        fclose(pf);
    return ret;
}

void _get_solution_http(int solution_id, char *work_dir, int lang)
{
    char src_pth[BUFFER_SIZE];

    // create the src file
    sprintf(src_pth, "Main.%s", lang_ext[lang]);
    if (DEBUG)
        printf("Main=%s", src_pth);

    login();

    const char *cmd2 =
        "wget --post-data=\"getsolution=1&sid=%d\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O %s \"%s%s\"";
    FILE *pout = read_cmd_output(cmd2, solution_id, src_pth, http_baseurl, http_apipath);

    pclose(pout);
}
void get_solution(int solution_id, char *work_dir, int lang)
{
    char src_pth[BUFFER_SIZE];
    sprintf(src_pth, "Main.%s", lang_ext[lang]);
    if (http_judge)
    {
        _get_solution_http(solution_id, work_dir, lang);
    }
    if (lang == LANG_PYTHON)
    { // 从源码中搜索python2字样，失败的结果非零默认python3,成功的结果为0是python2
        py2 = execute_cmd("/bin/grep 'python2' %s/Main.py > /dev/null", work_dir);
        execute_cmd("sed -i 's/import.*os//g' %s/%s", work_dir, src_pth);
    }
    execute_cmd("chown judge %s/%s", work_dir, src_pth);
}

void _get_custominput_http(int solution_id, char *work_dir)
{
    char src_pth[BUFFER_SIZE];

    // create the src file
    sprintf(src_pth, data_in_tmp);

    login();

    const char *cmd2 =
        "wget --post-data=\"getcustominput=1&sid=%d\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O %s \"%s%s\"";
    FILE *pout = read_cmd_output(cmd2, solution_id, src_pth, http_baseurl, http_apipath);

    pclose(pout);
}
void get_custominput(int solution_id, char *work_dir)
{
    if (http_judge)
    {
        _get_custominput_http(solution_id, work_dir);
    }
}

void _get_solution_info_http(int solution_id, int &p_id, char *user_id,
                             int &lang, int &cid)
{

    login();

    const char *cmd =
        "wget --post-data=\"getsolutioninfo=1&sid=%d\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O - \"%s%s\"";
    FILE *pout = read_cmd_output(cmd, solution_id, http_baseurl, http_apipath);
    if (1 != fscanf(pout, "%d", &p_id))
        printf("http problem_id read fail...\n");
    if (1 != fscanf(pout, "%s", user_id))
        printf("http user_id read fail ... \n");
    if (1 != fscanf(pout, "%d", &lang))
        printf("http language read fail ... \n");
    if (1 != fscanf(pout, "%d", &cid))
        printf("http contest_id read fail ... \n");
    // set global info
    now_sol_id = solution_id;
    now_p_id = p_id;
    now_cid = cid;
    strcpy(now_user_id, user_id);
    pclose(pout);
}
void get_solution_info(int solution_id, int &p_id, char *user_id, int &lang, int &cid)
{

    if (http_judge)
    {
        _get_solution_info_http(solution_id, p_id, user_id, lang, cid);
    }
}

void _get_problem_info_http(int p_id, double &time_lmt, int &mem_lmt, int &spj)
{
    login();

    const char *cmd =
        "wget --post-data=\"getprobleminfo=1&pid=%d\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O - \"%s%s\"";
    FILE *pout = read_cmd_output(cmd, p_id, http_baseurl, http_apipath);
    if (1 != fscanf(pout, "%lf", &time_lmt))
        printf("http read time_limit fail...\n");
    if (1 != fscanf(pout, "%d", &mem_lmt))
        printf("http read memory_limit fail...\n");
    if (1 != fscanf(pout, "%d", &spj))
        printf("http read special judge fail...\n");
    pclose(pout);
    if (DEBUG)
        printf("time_lmt:%g\n", time_lmt);
}

void get_problem_info(int p_id, double &time_lmt, int &mem_lmt, int &spj)
{
    if (http_judge)
    {
        _get_problem_info_http(p_id, time_lmt, mem_lmt, spj);
    }
    if (time_lmt <= 0)
    {
        time_lmt = 1;
    }
}
char *escape(char s[], char t[])
{
    int i, j;
    for (i = j = 0; t[i] != '\0'; ++i)
    {
        if (t[i] == '\'')
        {
            s[j++] = '\'';
            s[j++] = '\\';
            s[j++] = '\'';
            s[j++] = '\'';
            continue;
        }
        else
        {
            s[j++] = t[i];
        }
    }
    s[j] = '\0';
    return s;
}

void prepare_files(char *filename, int namelen, char *infile, int &p_id,
                   char *work_dir, char *outfile, char *userfile, int runner_id)
{
    //              printf("ACflg=%d %d check a file!\n",ACflg,solution_id);

    char fname0[BUFFER_SIZE];
    char fname[BUFFER_SIZE];
    strncpy(fname0, filename, namelen);
    fname0[namelen] = 0;
    escape(fname, fname0);
    // printf("%s\n%s\n",fname0,fname);
    sprintf(infile, "%s/data/%d/%s.in", oj_home, p_id, fname);
    if (!shm_run)
    {
        // 非 OJ_SHM_RUN 模式时，in 文件路径为原始数据文件
        strcpy(data_in_tmp, infile);
    }
    char noip_file_name[BUFFER_SIZE];
    sprintf(noip_file_name, "%s/data/%d/input.name", oj_home, p_id);
    if (DEBUG)
    {
        printf("NOIP filename:%s\n", noip_file_name);
    }
    if (access(noip_file_name, R_OK) != -1)
    {
        if (DEBUG)
            printf("NOIP filename:%s\n", noip_file_name);
        FILE *fpname = fopen(noip_file_name, "r");
        if (fscanf(fpname, "%s", noip_file_name))
        {
            execute_cmd("/bin/cp '%s' %s/%s", infile, work_dir, basename(noip_file_name)); // 如果存在input.name则复制测试数据
            execute_cmd("/usr/bin/chown judge %s/%s", work_dir, basename(noip_file_name)); // 修改属主
            if (DEBUG)
                printf("NOIP filename:%s\n", noip_file_name);
        }
        fclose(fpname);
    }
    else
    {
        if (shm_run)
        {
            execute_cmd("/bin/cp -f '%s' %s", infile, data_in_tmp); // 如果开启了 OJ_SHM_RUN 则复制测试数据
        }
    }
    sprintf(outfile, "%s/data/%d/%s.out", oj_home, p_id, fname0);
    sprintf(noip_file_name, "%s/data/%d/output.name", oj_home, p_id);
    if (DEBUG)
    {
        printf("NOIP filename:%s\n", noip_file_name);
    }
    if (access(noip_file_name, R_OK) != -1)
    {
        FILE *fpname = fopen(noip_file_name, "r");
        if (fscanf(fpname, "%s", noip_file_name))
        {
            if (DEBUG)
            {
                printf("NOIP filename:%s\n", noip_file_name);
            }
            if (!strstr("noip_file_name", "//"))
            {
                sprintf(userfile, "%s/run%d/%s", oj_home, runner_id, basename(noip_file_name));
                execute_cmd("rm %s", userfile);
            }
        }
        fclose(fpname);
    }
    else
    {
        strcpy(userfile, data_out_tmp);
        execute_cmd("rm %s", userfile);
    }
}
void run_solution(int &lang, char *work_dir, double &time_lmt, int &usedtime,
                  int &mem_lmt, char *data_file_path, int p_id) // 为每个测试数据运行一次提交的答案
{
    // 准备环境变量处理中文，如果希望使用非中文的语言环境，可能需要修改这些环境变量
    char *const envp[] = {(char *const)"PYTHONIOENCODING=utf-8",
                          (char *const)"LANG=zh_CN.UTF-8",
                          (char *const)"LANGUAGE=zh_CN.UTF-8",
                          (char *const)"LC_ALL=zh_CN.utf-8", NULL};
    if (nice(19) != 19)
        printf("......................renice fail... \n");
    // now the user is "judger"
    if (chdir(work_dir))
    {
        write_log("Working directory :%s switch fail...", work_dir);
        exit(-4);
    }
    // open the files
    char noip_file_name[BUFFER_SIZE];
    sprintf(noip_file_name, "%s/data/%d/input.name", oj_home, p_id);
    if (DEBUG)
        printf("---------NOIP filename:%s\n", noip_file_name);
    if (access(noip_file_name, R_OK) == -1)
    { // 不存在指定文件名，使用标准输入
        printf("oridata: [%s]\nstdin: [%s]\n", data_file_path, data_in_tmp);
        stdin = freopen(data_in_tmp, "r", stdin);   // before chroot
    }

    execute_cmd("touch %s", data_out_tmp);
    execute_cmd("chgrp judge %s %s", data_out_tmp, data_in_tmp);
    execute_cmd("chmod 740 %s", data_in_tmp);
    execute_cmd("chmod 760 %s", data_out_tmp);
    stdout = freopen(data_out_tmp, "w", stdout);
    stderr = freopen(data_err_tmp, "a+", stderr);

    // trace me
    ptrace(PTRACE_TRACEME, 0, NULL, NULL);
    // run me
    if (
        lang != LANG_JAVA && lang != LANG_PHP && lang != LANG_BASH && lang != LANG_COBOL && lang != LANG_MATLAB && lang != LANG_CSHARP && lang != LANG_PYTHON)
    {

        if (chroot(work_dir))
            ;
    }
    else
    {
        if(chroot("/mnt/overlay_judge/run")) {
            write_log("chroot fail...");
        }
        
        if (chdir(work_dir)) {
            write_log("Working directory :%s switch fail...", work_dir);
            exit(-4);
        }
    }
    while (setgid(1536) != 0)
        sleep(1);
    while (setuid(1536) != 0)
        sleep(1);
    while (setresuid(1536, 1536, 1536) != 0)
        sleep(1);

    // child
    // set the limit
    struct rlimit LIM; // time limit, file limit& memory limit
    // time limit
    if (time_limit_to_total)
        LIM.rlim_cur = (time_lmt / cpu_compensation - usedtime / 1000.0f) + 1;
    else
        LIM.rlim_cur = time_lmt / cpu_compensation + 1;
    LIM.rlim_max = LIM.rlim_cur;
    // if(DEBUG) printf("LIM_CPU=%d",(int)(LIM.rlim_cur));
    setrlimit(RLIMIT_CPU, &LIM);
    alarm(0);
    if (num_of_test > 0)
    {
        if (num_of_test * time_lmt / cpu_compensation > 1)
            alarm(num_of_test * time_lmt / cpu_compensation);
        else
            alarm(1);
    }
    else
    {
        if (time_lmt / cpu_compensation > 1)
            alarm(time_lmt / cpu_compensation);
        else
            alarm(1);
    }
    // file limit
    LIM.rlim_max = STD_F_LIM + STD_MB;
    LIM.rlim_cur = STD_F_LIM;
    setrlimit(RLIMIT_FSIZE, &LIM);
    // proc limit
    switch (lang)
    {
        case LANG_GO:
        case LANG_JAVA:   // java
            LIM.rlim_cur = LIM.rlim_max = 880;
            break;
        case LANG_PYTHON: // python
            LIM.rlim_cur = LIM.rlim_max = 200;
            break;
        default:
            LIM.rlim_cur = LIM.rlim_max = 1;
    }

    setrlimit(RLIMIT_NPROC, &LIM);

    // set the stack
    LIM.rlim_cur = STD_MB << 8;
    LIM.rlim_max = STD_MB << 8;
    setrlimit(RLIMIT_STACK, &LIM);
    // set the memory
    LIM.rlim_cur = STD_MB * mem_lmt / 2 * 3;
    LIM.rlim_max = STD_MB * mem_lmt * 2;
    if (lang < LANG_JAVA || lang == LANG_OBJC || lang == LANG_CLANG || lang == LANG_CLANGPP || lang == LANG_GO)
        setrlimit(RLIMIT_AS, &LIM);

    switch (lang)
    {
        case LANG_C:
        case LANG_CPP:
        case LANG_GO:
            execle("./Main", "./Main", (char *)NULL, envp);
            break;
        case LANG_JAVA:
            sprintf(java_xmx, "-Xmx%dM", mem_lmt);
            // sprintf(java_xmx, "-XX:MaxPermSize=%dM", mem_lmt);
            execle("/usr/bin/java", "/usr/bin/java", java_xmx, java_xms, java_xss, // the security manager has been removed in later java version
                "-Djava.security.manager",                                      // 启用安全管理器，openjdk17还可用
                "-Djava.security.policy=./java.policy",
                "-Djava.util.logging.config.string=.level=ERROR", // 20241019 设置日志级别为error，避免warning影响判断
                "Main", (char *)NULL, envp);
            break;
        case LANG_PYTHON: // Python
            execle("/usr/bin/python3", "/usr/bin/python3", "Main.py", (char *)NULL, envp);
            break;
    }
    // sleep(1);
    printf("Execution error\nYou need to install compiler VM or runtime for your language.");
    fflush(stderr);
    exit(0);
}
int fix_python_mis_judge(char *work_dir, int &ACflg, int &topmemory, int mem_lmt)
{
    int comp_res = OJ_AC;

    comp_res = execute_cmd(
        "/bin/grep 'MemoryError'  %s", data_err_tmp);

    if (!comp_res)
    {
        printf("Python need more Memory!");
        ACflg = OJ_ML;
        topmemory = mem_lmt * STD_MB;
    }

    return comp_res;
}

int fix_java_mis_judge(char *work_dir, int &ACflg, int &topmemory,
                       int mem_lmt)
{
    int comp_res = OJ_AC;
    execute_cmd("chmod 700 %s", data_err_tmp);
    if (DEBUG)
        execute_cmd("cat %s", data_err_tmp);
    comp_res = execute_cmd("/bin/grep 'Exception'  %s", data_err_tmp);
    if (!comp_res)
    {
        printf("Exception reported\n");
        ACflg = OJ_RE;
    }
    execute_cmd("cat %s", data_err_tmp);

    comp_res = execute_cmd(
        "/bin/grep 'java.lang.OutOfMemoryError'  %s", data_err_tmp);

    if (!comp_res)
    {
        printf("JVM need more Memory!");
        ACflg = OJ_ML;
        topmemory = mem_lmt * STD_MB;
    }

    if (!comp_res)
    {
        printf("JVM need more Memory or Threads!");
        ACflg = OJ_ML;
        topmemory = mem_lmt * STD_MB;
    }
    comp_res = execute_cmd("/bin/grep 'Could not create'  %s", data_err_tmp);
    if (!comp_res)
    {
        printf("jvm need more resource,tweak -Xmx(OJ_JAVA_BONUS) Settings");
        ACflg = OJ_RE;
        // topmemory=0;
    }
    return comp_res;
}
float raw_text_judge(char *infile, char *outfile, char *userfile, int *total_mark)
{
    float mark = 0;
    int total = 0;
    FILE *in = fopen(infile, "r");
    if (fscanf(in, "%d", &total) != 1)
        return -1;
    fclose(in);
    FILE *out = fopen(outfile, "r");
    int num = 0;
    char *user_answer = (char *)malloc(4096);
    size_t user_length = 4095;
    size_t ans_length = 4095;
    float m[total + 1];
    char *ans[total + 1];
    *total_mark = 0;
    for (int i = 1; i <= total; i++)
    {
        ans[i] = (char *)malloc(4096);
        if (fscanf(out, "%d", &num) != 1)
            return -2;
        if (i == num)
        {
            if (fscanf(out, "%*[^\[][%f]", &m[num]) != 1)
                return -3;
            *total_mark += m[num];
            ans_length = getline(&ans[i], &ans_length, out);
            for (int j = ans_length - 1; '\n' == ans[i][j] || '\r' == ans[i][j]; j--)
            {
                ans[i][j] = '\0';
            }
        }
        else
        {
        }
    }
    fclose(out);
    FILE *user = fopen(userfile, "r");
    FILE *df = fopen("diff.out", "a");
    for (int i = 1; i <= total; i++)
    {
        if (fscanf(user, "%d", &num) == EOF)
            continue;
        user_length = getline(&user_answer, &user_length, user);
        int j = 0;
        for (j = user_length - 1; '\n' == user_answer[j] || '\r' == user_answer[j]; j--)
        {
            user_answer[j] = '\0';
        }
        if (num > 0 && num <= total)
        {
            if (strcasecmp(ans[num], user_answer) == 0 || strcasecmp(ans[num], "*") == 0)
            {
                mark += m[num];
            }
            else
            {
                fprintf(df, "%d:%s[%s] -%.1f\n", i, ans[i], user_answer, m[i]);
            }
            m[num] = 0;
        }
        free(ans[i]);
    }
    free(user_answer);
    fclose(user);
    fclose(df);
    return mark;
}

void EndCE(int solution_id, char *user_id, int p_id, int cid, const char *log_str, const char *addition="", bool overwrite=false) {
    char cefile[BUFFER_SIZE_SM] = "ce.txt";
    // sprintf(cefile, "%s/ce.txt", data_work_dir);
    // printf("***\n\n, %s, %s \n", data_work_dir, cefile);
    FILE *cef = overwrite ? fopen(cefile, "w") : fopen(cefile, "a");
    if (cef != NULL) {
        if (addition && strlen(addition) > 0) {
            fprintf(cef, "\n%s\n", addition);
        }
        fclose(cef);
    }
    addceinfo(solution_id);
    update_solution(solution_id, OJ_CE, 0, 0, 0, 0, 0.0);
    if (!turbo_mode)
        update_user(user_id);
    if (!turbo_mode)
        update_problem(p_id, cid);
    clean_workdir(data_work_dir);
    write_log(log_str);
    exit(0);
}

bool TryBuildSpj(const char *oj_home, const int p_id, const char *spj_code_name, const char *spj_code_ext, bool flag_remote_get)
{
    sprintf(local_spj_code_file, "%s/data/%d/%s.%s", oj_home, p_id, spj_code_name, spj_code_ext);
    sprintf(local_spj_file, "%s/data/%d/%s", oj_home, p_id, spj_code_name);

    struct rlimit LIM;
    LIM.rlim_cur = STD_MB << 9;
    LIM.rlim_max = STD_MB << 9;

    setrlimit(RLIMIT_STACK, &LIM);  // 主进程开栈

    if (!flag_remote_get && access(local_spj_file, F_OK) == 0 && get_file_time(local_spj_file) >= get_file_time(local_spj_code_file))
    {
        return true; // 如果不是远程获取的spj代码，且本地已经有spj可执行文件了，且可执行文件比源码时间晚，则不再编译
    }
    if (access(local_spj_code_file, F_OK) == 0)
    {
        const char *cmd_spj = "g++ %s %s -o %s %s"; // spj.c也可以用g++编译
        const int checker_build_res = execute_cmd(cmd_spj, cc_opt, cpp_std, local_spj_file, local_spj_code_file);
        if(DEBUG) {
            printf("Checker Build Result: %d\n", checker_build_res);
        }
        if(checker_build_res != 0) {
            // 编译 spj 失败
            if(DEBUG) {
                printf("spj compile failed\n");
            }
            execute_cmd("rm %s", local_spj_file);
            EndCE(now_sol_id, now_user_id, now_p_id, now_cid, "Checker Build Failed", 
            "It's not you. It's SYSTEM_ERROR. Ask the admin to check the checker.\n", true);  // Set result as CE when checker build failed
            return false;
        }
        if(DEBUG) {
            printf(cmd_spj, local_spj_file, local_spj_code_file);
        }
        execute_cmd("chmod +x", local_spj_file);
    }
    return true;
}
int special_judge(char *oj_home, int problem_id, char *infile, char *outfile, char *userfile, double *pass_rate, int spj)
{
    pid_t pid;
    char spjpath[BUFFER_SIZE / 2];
    char tpjpath[BUFFER_SIZE / 2];
    if (DEBUG)
        printf("pid=%d\n", problem_id);

    // ********** 20231006: 目录加锁避免多进程同写
    lockdatafolder(problem_id, "SPJ");
    // **********
    TryBuildSpj(oj_home, problem_id, "spj", "c", false);
    TryBuildSpj(oj_home, problem_id, "spj", "cc", false);
    TryBuildSpj(oj_home, problem_id, "tpj", "cc", false);
    // prevent privileges settings caused spj fail in [issues686]
    execute_cmd("chown www-data:judge %s/data/%d/spj %s/data/%d/tpj %s %s %s", oj_home, problem_id, oj_home, problem_id, infile, outfile, userfile);
    execute_cmd("chmod 777 %s/data/%d/spj %s/data/%d/tpj %s %s %s", oj_home, problem_id, oj_home, problem_id, infile, outfile, userfile); // 777 避免同host下php的pod无法读取目录
    // ********** 20231006: 目录加锁避免多进程同写
    releasefile(lock_data_fd);
    // **********

    pid = fork();
    int ret = 0;
    if (pid == 0)
    {

        while (setgid(1536) != 0)
            sleep(1);
        while (setuid(1536) != 0)
            sleep(1);
        while (setresuid(1536, 1536, 1536) != 0)
            sleep(1);

        struct rlimit LIM; // time limit, file limit& memory limit

        LIM.rlim_cur = 5;
        LIM.rlim_max = LIM.rlim_cur;
        setrlimit(RLIMIT_CPU, &LIM);
        alarm(0);
        alarm(10);

        // file limit
        LIM.rlim_max = STD_F_LIM + STD_MB;
        LIM.rlim_cur = STD_F_LIM;
        setrlimit(RLIMIT_FSIZE, &LIM);
        sprintf(spjpath, "%s/data/%d/spj", oj_home, problem_id);
        sprintf(tpjpath, "%s/data/%d/tpj", oj_home, problem_id);

        if (access(tpjpath, X_OK) == 0)
        {
            ret = execute_cmd("%s/data/%d/tpj %s %s %s 2>> diff.out ", oj_home, problem_id, infile, userfile, outfile); // testlib style
            if (DEBUG)
                printf("testlib spj return: %d\n", ret);
        }
        else if (access(spjpath, X_OK) == 0)
        {
            ret = execute_cmd("%s/data/%d/spj %s %s %s", oj_home, problem_id, infile, outfile, userfile); // spj style
            if (DEBUG)
                printf("csgoj spj return: %d\n", ret);
        }
        else if (spj == 2)
        {
        }
        else
        {
            printf("spj tpj not found problem: %d\n", problem_id);
            ret = 99;
        }
        if (ret == 99) {
            exit(99);
        } else if(ret) {
            exit(1);
        } else {
            exit(0);
        }
    }
    else
    {
        int status;

        waitpid(pid, &status, 0);
        ret = WEXITSTATUS(status);
        if(ret == 99) {
            EndCE(now_sol_id, now_user_id, now_p_id, now_cid, "Checker Not Found", 
            "It's not you. It's SYSTEM_ERROR. Ask the admin to reset checker.\n", true);
        }
        if (DEBUG)
        {
            printf("recorded spj: %d\n", ret);
        }
    }
    return ret;
}
void judge_solution(int &ACflg, int &usedtime, double time_lmt, int spj,
                    int p_id, char *infile, char *outfile, char *userfile, int &PEflg,
                    int lang, char *work_dir, int &topmemory, int mem_lmt,
                    int solution_id, int num_of_test, double *pass_rate)
{
    // usedtime-=1000;
    int comp_res;
    if (num_of_test == 0)
        num_of_test = 1.0;

    if (ACflg == OJ_AC)
    {
        int real_limit = 1000;
        if (time_limit_to_total)
        { // 如果限制总时间
            real_limit = time_lmt * 1000;
            if (total_time > real_limit)
                ACflg = OJ_TL; // 总时间超过
            if (usedtime > real_limit)
                ACflg = OJ_TL; // 单点超过
        }
        else if (num_of_test > 0)
        { // 如果数据点不为0，且限制单点时间
            real_limit = num_of_test * time_lmt * 1000;
            // if(total_time>real_limit) ACflg = OJ_TL;  //总时间超过测试点数*限制
            if (usedtime > time_lmt * 1000)
                ACflg = OJ_TL; // 单点超过限制
        }
        else
        {                                 // 测试数为0 ，这种情况不应该出现，但给出，作为保险。
            real_limit = time_lmt * 1000; // fallback
            if (usedtime > real_limit)
                ACflg = OJ_TL;
            if (total_time > real_limit)
                ACflg = OJ_TL;
        }
    }
    if (topmemory > mem_lmt * STD_MB)
    {
        ACflg = OJ_ML;
    }
    // compare
    if (ACflg == OJ_AC)
    {
        if (spj)
        {
            comp_res = special_judge(oj_home, p_id, infile, outfile, userfile, pass_rate, spj);

            if (comp_res == 0)
            {
                comp_res = OJ_AC;
            }
            else
            {
                if (DEBUG)
                {
                    printf("fail test %s\n", infile);
                }
                comp_res = OJ_WA;
            }
        }
        else
        {
            comp_res = compare(outfile, userfile, infile, userfile);
        }
        if (comp_res == OJ_WA)
        {
            ACflg = OJ_WA;
            if (DEBUG)
                printf("fail test %s\n", infile);
        }
        else if (comp_res == OJ_PE)
        {
            PEflg = OJ_PE;
        }
        else if (comp_res == OJ_RE)
        {
            if (DEBUG)
            {
                printf("[OJ_RE(%d)] compare failed. outfile: [%s], userfile: [%s]\n", OJ_RE, userfile, outfile);
            }
        }
        ACflg = comp_res;
    }
    // jvm popup messages, if don't consider them will get miss-WrongAnswer
    if (lang == LANG_JAVA)
    {
        comp_res = fix_java_mis_judge(work_dir, ACflg, topmemory, mem_lmt);
    }
    if (lang == LANG_PYTHON)
    {
        comp_res = fix_python_mis_judge(work_dir, ACflg, topmemory, mem_lmt);
    }
}

int get_page_fault_mem(struct rusage &ruse, pid_t &pidApp)
{
    // java use pagefault
    int m_vmpeak, m_vmdata, m_minflt;
    m_minflt = ruse.ru_minflt * getpagesize();
    if (0 && DEBUG)
    {
        m_vmpeak = get_proc_status(pidApp, "VmPeak:");
        m_vmdata = get_proc_status(pidApp, "VmData:");
        printf("VmPeak:%d KB VmData:%d KB minflt:%d KB\n", m_vmpeak, m_vmdata,
               m_minflt >> 10);
    }
    return m_minflt;
}
void print_runtimeerror(char *infile, char *err)
{
    FILE *ferr = fopen(data_err_tmp, "a+");
    fprintf(ferr, "%s:%s\n", infile, err);
    fclose(ferr);
}
void clean_session(pid_t p)
{
    // char cmd[BUFFER_SIZE];
    const char *pre = "ps awx -o \"\%p \%P\"|grep -w ";
    const char *post = " | awk \'{ print $1  }\'|xargs kill -9";
    execute_cmd("%s %d %s", pre, p, post);
    execute_cmd("ps aux |grep \\^judge|awk '{print $2}'|xargs kill");
}

void watch_solution(pid_t pidApp, char *infile, int &ACflg, int spj,
                    char *userfile, char *outfile, int solution_id, int lang,
                    int &topmemory, int mem_lmt, int &usedtime, double time_lmt, int &p_id,
                    int &PEflg, char *work_dir)
{
    // 父进程中的保姆程序
    int tempmemory = 0;

    if (DEBUG)
    {
        printf("pid=%d judging %s\n", pidApp, infile);
    }

    int status, sig, exitcode;
    char white_code[256] = {0};
    white_code[0] = 1; // add more if new signal complain
    white_code[5] = 1;
    white_code[17] = 1;
    white_code[23] = 1;
    white_code[133] = 1;
    char buf[BUFFER_SIZE];

    struct user_regs_struct reg;
    struct rusage ruse;
    int first = true;
    int tick = 0;
    long outFileSize = get_file_size(outfile);

    // for walltime
    struct timeval start_time, current_time;
    // struct timeval time_before_wait, time_after_wait;
    // double elapsed_wait = 0.0;
    gettimeofday(&start_time, NULL);
    const double wall_top = time_lmt * 3 + 1;
    bool flg_timefull = false;
    while (1)
    {
        tick++;
        // check the usage

        // gettimeofday(&time_before_wait, NULL);
        wait4(pidApp, &status, __WALL, &ruse); // 等待子进程切换内核态（调用系统API或者运行状态变化）
        // gettimeofday(&time_after_wait, NULL);
        if (first)
        {
            ptrace(PTRACE_SETOPTIONS, pidApp, NULL, PTRACE_O_TRACESYSGOOD | PTRACE_O_TRACEEXIT
                   //    |PTRACE_O_EXITKILL
                   //    |PTRACE_O_TRACECLONE
                   //    |PTRACE_O_TRACEFORK
                   //    |PTRACE_O_TRACEVFORK
                   //   有的发行版带的PTRACE不识别以上宏，因此注释掉
            );
        }

        // check wall time
        gettimeofday(&current_time, NULL);
        double elapsed_time = (current_time.tv_sec - start_time.tv_sec) + (current_time.tv_usec - start_time.tv_usec) / 1000000.0;
        // elapsed_wait += (time_after_wait.tv_sec - time_before_wait.tv_sec) + (time_after_wait.tv_usec - time_before_wait.tv_usec) / 1000000.0; 
        // double judged_time = (ruse.ru_utime.tv_sec + ruse.ru_utime.tv_usec + ruse.ru_stime.tv_sec + ruse.ru_stime.tv_usec) / 1000000.0;
        // if(DEBUG) {
        //     printf("### wall top: %g, elapsed_time: %g, elapsed_wait: %g, judged_time: %g\n", wall_top, elapsed_time, elapsed_wait, judged_time);
        // }
        if (elapsed_time > wall_top)
        {
            ACflg = OJ_TL;
            usedtime = time_lmt * 1000;
            flg_timefull = true;
            ptrace(PTRACE_KILL, pidApp, NULL, NULL);
            break;
        }

        // jvm gc ask VM before need,so used kernel page fault times and page size
        if (lang == LANG_JAVA ||
            lang == LANG_PHP ||
            lang == LANG_CSHARP ||
            lang == LANG_CLANG || lang == LANG_CLANGPP ||
            lang == LANG_JS ||
            lang == LANG_GO ||
            lang == LANG_MATLAB ||
            lang == LANG_COBOL)
        {
            tempmemory = get_page_fault_mem(ruse, pidApp);
        }
        else
        {
            // other use VmPeak
            tempmemory = get_proc_status(pidApp, "VmPeak:") << 10;
        }
        if (tempmemory > topmemory)
        {
            topmemory = tempmemory;
        }
        if (topmemory > mem_lmt * STD_MB)
        {
            if (DEBUG)
            {
                printf("out of memory %d\n", topmemory);
            }
            if (ACflg == OJ_AC)
            {
                ACflg = OJ_ML;
            }
            ptrace(PTRACE_KILL, pidApp, NULL, NULL);
            break;
        }
        // sig = status >> 8;/*status >> 8 EXITCODE*/

        if (WIFEXITED(status))
        {
            // 子进程已经退出 ，返回值不为0则判RE
            exitcode = WEXITSTATUS(status);
            char error[BUFFER_SIZE];
            if (exitcode)
            {
                ACflg = OJ_RE;
                if (DEBUG)
                {
                    printf("[OJ_RE(%d)] exitcode: %d\n", OJ_RE, exitcode);
                }
                sprintf(error, "\t    non-zero return = %d \n", exitcode);
                print_runtimeerror(infile + strlen(oj_home) + 5, error);
            }
            break;
        }
        if ((lang < LANG_RUBY || lang == LANG_BASH || lang == LANG_CSHARP) && get_file_size(data_err_tmp) && !oi_mode)
        {
            if(lang != LANG_JAVA) { // 20241019 java policy WARNING 干扰 data_err_tmp

                ACflg = OJ_RE;
                if (DEBUG)
                {
                    printf("[OJ_RE(%d)] not oi_mode and error.out exists\n", OJ_RE);
                }
                // addreinfo(solution_id);
                ptrace(PTRACE_KILL, pidApp, NULL, NULL);
                break;
            }
        }

        if (((tick & 0xff) == 0x00) && (!spj) && get_file_size(userfile) > outFileSize * 2 + 1024)
        {
            ACflg = OJ_OL;
            ptrace(PTRACE_KILL, pidApp, NULL, NULL);
            break;
        }
        exitcode = WEXITSTATUS(status) % 256;
        /*exitcode == 5 waiting for next CPU allocation          * ruby using system to run,exit 17 ok
         *  Runtime Error:Unknown signal xxx need be added here
         */
        if (white_code[exitcode])
        {
            // || ( (exitcode == 17||exitcode == 23) && (lang >= LANG_JAVA && lang!= LANG_OBJC && lang != LANG_CLANG && lang != LANG_CLANGPP) )
            // 进程休眠或等待IO
            // go on and on
            ;
        }
        else
        {
            if (DEBUG)
            {
                printf("status>>8=%d\n", exitcode);
            }
            // psignal(exitcode, NULL);

            if (ACflg == OJ_AC)
            {
                switch (exitcode)
                {
                // 根据退出的原因给出判题结果
                case SIGCHLD:
                case SIGALRM:
                    alarm(0);
                    if (DEBUG)
                    {
                        printf("alarm:%g\n", time_lmt);
                    }
                case SIGKILL:
                case SIGXCPU:
                    ACflg = OJ_TL;
                    usedtime = time_lmt * 1000;
                    flg_timefull = true;
                    if (DEBUG)
                    {
                        printf("[OJ_TL(%d)] TLE: %d\n", OJ_TL, usedtime);
                    }
                    break;
                case SIGXFSZ:
                    ACflg = OJ_OL;
                    break;
                default:
                    ACflg = OJ_RE;
                    if (DEBUG)
                    {
                        printf("[OJ_RE(%d)] exitcode not recognize: %d\n", OJ_RE, exitcode);
                    }
                }
                print_runtimeerror(infile + strlen(oj_home) + 5, strsignal(exitcode));
                sprintf(buf, "adding: ' white_code[%d]=1; ' after judge_client for ", exitcode);
                print_runtimeerror(buf, strsignal(exitcode));
            }
            ptrace(PTRACE_KILL, pidApp, NULL, NULL); // 杀死出问题的进程

            break;
        }
        if (WIFSIGNALED(status))
        {
            /*  WIFSIGNALED: if the process is terminated by signal
             *  由外部信号触发的进程终止
             *  psignal(int sig, char *s)，like perror(char *s)，print out s, with error msg from system of sig
             * sig = 5 means Trace/breakpoint trap
             * sig = 11 means Segmentation fault
             * sig = 25 means File size limit exceeded
             */
            sig = WTERMSIG(status);

            if (DEBUG)
            {
                printf("WTERMSIG=%d\n", sig);
                psignal(sig, NULL);
            }
            if (ACflg == OJ_AC)
            {
                switch (sig)
                {
                // 根据原因给出结论
                case SIGCHLD:
                case SIGALRM:
                    alarm(0);
                case SIGKILL:
                case SIGXCPU:
                    ACflg = OJ_TL;
                    break;
                case SIGXFSZ:
                    ACflg = OJ_OL;
                    break;

                default:
                    ACflg = OJ_RE;
                    if (DEBUG)
                    {
                        printf("[OJ_RE(%d)] outer exitcode: %d\n", exitcode);
                    }
                }
                print_runtimeerror(infile + strlen(oj_home) + 5, strsignal(sig));
            }
            break;
        }
        /*     comment from http://www.felix021.com/blog/read.php?1662
         WIFSTOPPED: return true if the process is paused or stopped while ptrace is watching on it
         WSTOPSIG: get the signal if it was stopped by signal
         */

        // check the system calls
        if (!use_ptrace)
        {
            ptrace(PTRACE_SYSCALL, pidApp, NULL, NULL);
            continue;
        }

#ifdef __i386__
        call_id = ptrace(PTRACE_GETREGS, pidApp, NULL, &reg);
        call_id = ((unsigned int)reg.REG_SYSCALL) % call_array_size;
#endif
#ifdef __x86_64__
        call_id = ptrace(PTRACE_GETREGS, pidApp, NULL, &reg);
        call_id = ((unsigned int)reg.REG_SYSCALL) % call_array_size;
#endif

        if (record_call)
        {
            printf("new call id:%d\n", call_id);
            call_counter[call_id]++;
            printf("call %d: %d\n", call_id, call_counter[call_id]);
        }
        else if (call_counter[call_id])
        {
            call_counter[call_id]--;
        }
        else
        { // 对于非法的系统调用，给出具体编号给管理员参考
            ACflg = OJ_RE;
            if (DEBUG)
            {
                printf("[OJ_RE(%d)] Forbidden system call:%u [%u]\n", OJ_RE, call_id, (unsigned int)reg.REG_SYSCALL);
            }
            char error[BUFFER_SIZE];
            sprintf(error,
                    "[ERROR] solution_id:%d called a Forbidden system call:%u [%u]\n",
                    solution_id, call_id, (unsigned int)reg.REG_SYSCALL);

            write_log(error);
            print_runtimeerror(infile + strlen(oj_home) + 5, error);

            ptrace(PTRACE_KILL, pidApp, NULL, NULL);
        }
        call_id = 0;

        ptrace(PTRACE_SYSCALL, pidApp, NULL, NULL); // 继续等待下一次的系统调用或其他中断
        first = false;
        // usleep(1);
    }

    ptrace(PTRACE_KILL, pidApp, NULL, NULL); // 杀死出问题的进程

    if(!flg_timefull) {
        usedtime += (ruse.ru_utime.tv_sec * 1000 + ruse.ru_utime.tv_usec / 1000) * cpu_compensation; // 统计用户态耗时，在更快速的CPU上加以cpu_compensation倍数放大
        usedtime += (ruse.ru_stime.tv_sec * 1000 + ruse.ru_stime.tv_usec / 1000) * cpu_compensation; // 统计内核态耗时，在更快速的CPU上加以cpu_compensation倍数放大
    }

    // clean_session(pidApp);
}


void init_parameters(int argc, char **argv, int &solution_id, int &runner_id)
{
    if (argc < 3)
    {
        fprintf(stderr, "CSGOJ judge_client version 20231023\n\n");
        fprintf(stderr, "Normal Usage:\n\t%s <solution_id> <runner_id>\n\n", argv[0]);
        fprintf(stderr, "Multi OJ with Specific home :\n\t%s <solution_id> <runner_id> [judge_base_path].\n\n", argv[0]);
        fprintf(stderr, "Debug with Specific home:\n\t%s <solution_id> <runner_id> [judge_base_path] [debug].\n\n", argv[0]);
        fprintf(stderr, "\n\n");
        fprintf(stderr, "Example:\n\tsudo %s 1001 0 /home/judge/ debug  \n\n", argv[0]);
        exit(1);
    }
    DEBUG = (argc > 4);
    record_call = (argc > 5);
    if (argc > 5)
    {
        strcpy(LANG_NAME, argv[5]);
    }
    if (argc > 3)
    {
        strcpy(oj_home, argv[3]);
    }
    else
    {
        strcpy(oj_home, "/home/judge");
    }
    if (chdir(oj_home))
        exit(-2); // change the dir// init our work

    solution_id = atoi(argv[1]);
    runner_id = atoi(argv[2]);
}
int get_sim(int solution_id, int lang, int pid, int &sim_s_id)
{
    char src_pth[BUFFER_SIZE];
    // char cmd[BUFFER_SIZE];
    sprintf(src_pth, "Main.%s", lang_ext[lang]);
    int sim = execute_cmd("/usr/bin/sim.sh %s %d", src_pth, pid);
    FILE *pf;
    pf = fopen("sim", "r");
    if (!sim)
    {
        if (pf)
        {
            execute_cmd("/bin/mkdir ../data/%d/ac/ 2>/dev/null", pid);
            execute_cmd("/bin/cp %s ../data/%d/ac/%d.%s 2>/dev/null", src_pth, pid, solution_id, lang_ext[lang]);
            // c cpp will
            if (lang == 0)
            {
                execute_cmd("/bin/ln ../data/%d/ac/%d.%s ../data/%d/ac/%d.%s 2>/dev/null", pid,
                            solution_id, lang_ext[lang], pid, solution_id,
                            lang_ext[lang + 1]);
            }
            if (lang == 1)
            {
                execute_cmd("/bin/ln ../data/%d/ac/%d.%s ../data/%d/ac/%d.%s 2>/dev/null", pid,
                            solution_id, lang_ext[lang], pid, solution_id,
                            lang_ext[lang - 1]);
            }
        }
    }
    else
    {
        if (pf)
        {
            if (2 == fscanf(pf, "%d%d", &sim, &sim_s_id))
                ;
        }
    }
    if (pf)
        fclose(pf);
    if (solution_id <= sim_s_id)
    {
        sim = 0;
    }
    return sim;
}

void mk_shm_workdir(char *work_dir)
{
    char shm_path[BUFFER_SIZE];
    sprintf(shm_path, "/dev/shm/csgoj/%s", work_dir);
    execute_cmd("/bin/mkdir -p %s  2>/dev/null", shm_path);
    execute_cmd("/bin/ln -s %s %s/  2>/dev/null", shm_path, oj_home);
    execute_cmd("/bin/chown judge %s  2>/dev/null", shm_path);
    execute_cmd("chmod 755 %s  2>/dev/null", shm_path);
    // sim need a soft link in shm_dir to work correctly
    sprintf(shm_path, "/dev/shm/csgoj/%s/", oj_home);
    execute_cmd("/bin/ln -s %s/data %s  2>/dev/null", oj_home, shm_path);
}
int count_in_files(char *dirpath)
{
    const char *cmd = "ls -l %s/*.in|wc -l";
    int ret = 0;
    FILE *fjobs = read_cmd_output(cmd, dirpath);
    if (1 != fscanf(fjobs, "%d", &ret))
        printf("warning count files fail");
    ;
    pclose(fjobs);

    return ret;
}
void PrintLog(FILE *fp, char *logstr)
{
    char timestamp[100];
    time_t now = time(NULL);
    struct tm *timeinfo = localtime(&now);
    strftime(timestamp, sizeof(timestamp), "%Y-%m-%d %H:%M:%S", timeinfo);
    fprintf(fp, "[%s] %s\n", logstr);
}
int get_test_file(char *work_dir, int p_id)
{
    char filename[BUFFER_SIZE / 2];
    char localfile[BUFFER_SIZE];
    time_t remote_date, local_date;
    int ret = 0;
    const char *cmd =
        " wget --post-data=\"gettestdatalist=1&time=1&pid=%d\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O - \"%s%s\"";
    FILE *fjobs = read_cmd_output(cmd, p_id, http_baseurl, http_apipath);

    // ********** 20231006: 目录加锁避免多进程同写
    execute_cmd("/bin/mkdir -p %s/data/%d", oj_home, p_id);
    lockdatafolder(p_id, "DATA");
    // **********
    while (fgets(filename, sizeof(filename) - 1, fjobs) != NULL)
    {

        if (1 != sscanf(filename, "%ld", &remote_date))
            printf("http remote time stamp read fail\n");
        if (fgets(filename, sizeof(filename) - 1, fjobs) == NULL)
            break;
        if (1 != sscanf(filename, "%s", filename))
            printf("http filename read fail\n");
        if (http_judge && (!data_list_has(filename)))
            data_list_add(filename);
        sprintf(localfile, "%s/data/%d/%s", oj_home, p_id, filename);
        if (DEBUG)
            printf("localfile[%s]\n", localfile);

        struct stat fst;
        stat(localfile, &fst);
        local_date = fst.st_mtime;
        if (access(localfile, F_OK) == -1 || local_date < remote_date)
        {
            if (DEBUG)
            {
                printf("local_date: [%ld], remote_date: [%ld]\n", local_date, remote_date);
            }
            if (strcmp(filename, "spj") == 0)
                continue;
            if (strcmp(filename, "tpj") == 0) // 不再同步tpj文件，而是编译tpj.cc源码; 20230915
                continue;
            execute_cmd("/bin/mkdir -p %s/data/%d", oj_home, p_id);

            const char *cmd2 =
                " wget --post-data=\"gettestdata=1&filename=%d/%s\" --load-cookies=cookie --save-cookies=cookie --keep-session-cookies -q -O \"%s\"  \"%s%s\"";
            execute_cmd(cmd2, p_id, filename, localfile, http_baseurl, http_apipath);
            ret++;

        }
        if (strcmp(filename, "spj.c") == 0)
        {
            TryBuildSpj(oj_home, p_id, "spj", "c", true);
        }
        else if (strcmp(filename, "spj.cc") == 0)
        {
            TryBuildSpj(oj_home, p_id, "spj", "cc", true);
        }
        else if (strcmp(filename, "tpj.cc") == 0)
        {
            TryBuildSpj(oj_home, p_id, "tpj", "cc", true);
        }
    }
    // ********** 20231006: 目录加锁避免多进程同写
    releasefile(lock_data_fd);
    // **********
    pclose(fjobs);

    return ret;
}
void print_call_array()
{
    printf("int LANG_%sV[CALL_ARRAY_SIZE]={", LANG_NAME);
    int i = 0;
    for (i = 0; i < call_array_size; i++)
    {
        if (call_counter[i] > 0)
        {
            printf("%d,", i);
        }
    }
    printf("0};\n");

    printf("int LANG_%sC[CALL_ARRAY_SIZE]={", LANG_NAME);
    for (i = 0; i < call_array_size; i++)
    {
        if (call_counter[i])
        {
            printf("%d,", call_counter[i]);
        }
    }
    printf("0};\n");
}
int mark_of_name(const char *name)
{
    int mark;
    printf("reading mark from %s \n", name);
    if (sscanf(name, "%*[^\[][%d]", &mark) == 1)
    {
        printf("reading mark %d \n", mark);
        return mark;
    }
    else
    {
        return 10;
    }
}

int same_subtask(char *last, char *cur)
{
    int i = 0;
    for (i = 0; last[i] != '.' && cur[i] != '.'; i++)
    {
        if (last[i] != cur[i])
        {
            break;
        }
    }
    return last[i] == cur[i];
}

int main(int argc, char **argv)
{
    char work_dir[BUFFER_SIZE];
    // char cmd[BUFFER_SIZE];
    char user_id[BUFFER_SIZE];
    int solution_id = 1000;
    int runner_id = 0;
    int p_id, mem_lmt, lang, spj, sim, sim_s_id, max_case_time = 0, cid = 0;
    double time_lmt;
    char time_space_table[BUFFER_SIZE * 100];
    int time_space_index = 0;

    init_parameters(argc, argv, solution_id, runner_id);

    init_judge_conf();
    // set work directory to start running & judging
    sprintf(work_dir, "%s/run%s/", oj_home, argv[2]);
    if (shm_run)
    {
        sprintf(data_work_dir, "/dev/shm/csgoj/run%s/", argv[2]);
        sprintf(data_in_tmp, "%s/data.in", data_work_dir);
        sprintf(data_out_tmp, "%s/user.out", data_work_dir);
        sprintf(data_err_tmp, "%s/error.out", data_work_dir);
    }
    else
    {
        strcpy(data_work_dir, work_dir); // 如果没有使用 shm，则数据目录与work目录一致
        strcpy(data_out_tmp, "user.out");
        strcpy(data_err_tmp, "error.out");
    }
    sprintf(lock_file, "%s/client%s.pid", oj_home, argv[2]);

    while (already_running())
    {
        syslog(LOG_ERR | LOG_DAEMON,
               "This working directory is occupied !\n");
        printf("%s already has one judge_client in it!\n", work_dir);
        sleep(5);
    }

    if (shm_run)
    {
        execute_cmd("mkdir -p %s", data_work_dir);
    }
    execute_cmd("mkdir -p %s", work_dir);

    clean_workdir(work_dir);

    if (chdir(work_dir))
        exit(-3);

    if (http_judge)
    {
        // // 多进程判题时，软链接可能出现写同一个cookie文件的情况
        // if(system("/bin/ln -s ../cookie ./")) {
        //     printf("cookie link fail \n");
        // }
        if (system("/bin/cp -f ../cookie ./"))
        {
            printf("copy cookie fail \n");
        }
    }
    get_solution_info(solution_id, p_id, user_id, lang, cid);
    // get the limit

    if (p_id == 0)
    {
        time_lmt = 5;
        mem_lmt = 128;
        spj = 0;
    }
    else
    {
        get_problem_info(p_id, time_lmt, mem_lmt, spj);
    }
    // copy source file
    get_solution(solution_id, work_dir, lang);

    // java and other VM language are lucky to have the global bonus in judge.conf
    if (lang >= LANG_JAVA && lang != LANG_OBJC && lang != LANG_CLANG && lang != LANG_CLANGPP && lang != LANG_GO)
    { // ObjectivC Clang Clang++ Go not VM or Script
    
        // if(lang == LANG_PYTHON) {
        //     time_lmt *= 2;  // python 2倍
        // } else {
        //     time_lmt = time_lmt + java_time_bonus;  // java等有初始化时间的语言加 java_time_bonus 秒
        // }
        
        time_lmt = time_lmt + java_time_bonus;  // java等有初始化时间的语言加 java_time_bonus 秒

        mem_lmt = mem_lmt + java_memory_bonus;
        // copy java.policy
        if (lang == 3)
        {
            execute_cmd("/bin/cp %s/etc/java0.policy %s/java.policy", oj_home, work_dir);
            execute_cmd("chmod 755 %s/java.policy", work_dir);
            execute_cmd("chown judge %s/java.policy", work_dir);
        }
    }

    // never bigger than judged set value;
    if (time_lmt > 300) {
        time_lmt = 300;
    } else if(time_lmt <= 0) {
        time_lmt = 1;
    }
    if (mem_lmt > 1024 || mem_lmt < 1)
        mem_lmt = 1024;

    if (DEBUG)
        printf("time: %g mem: %d\n", time_lmt, mem_lmt);

    // compile
    // set the result to compiling
    int Compile_OK = 0;
    if (spj != 2)
    {
        Compile_OK = compile(lang, work_dir);
    }
    // if (Compile_OK != 0 && !spj)
    if (Compile_OK != 0) //  !spj 会导致错误的CE
    {
        EndCE(solution_id, user_id, p_id, cid, "compile error");
    }
    else
    {
        if (!turbo_mode)
            update_solution(solution_id, OJ_RI, 0, 0, 0, 0, 0.0);
        ClearRun(work_dir);
    }
    // run
    char fullpath[BUFFER_SIZE];
    char infile[BUFFER_SIZE_SM];
    char outfile[BUFFER_SIZE_SM];
    char userfile[BUFFER_SIZE_SM];
    sprintf(fullpath, "%s/data/%d", oj_home, p_id); // the fullpath of data dir

    // open DIRs
    // DIR *dp;
    dirent *dirp;
    // using http to get remote test data files
    if (p_id > 0 && http_judge && http_download)
        get_test_file(work_dir, p_id);

    struct dirent **namelist;
    int namelist_len;
    namelist_len = scandir(fullpath, &namelist, inFile, alphasort);
    if (p_id > 0 && namelist_len == -1)
    {

        write_log("No such dir:%s!\n", fullpath);
        exit(-1);
    }
    else
    {
        if (DEBUG)
        {
            printf("total test case:%d \n", namelist_len);
            for (int i = 0; i < namelist_len; i++)
            {
                printf("test file %d : %s\n", i + 1, namelist[i]->d_name);
            }
        }
    }

    int ACflg, PEflg;
    ACflg = PEflg = OJ_AC;
    int namelen;
    int usedtime = 0, topmemory = 0;

    // read files and run
    double pass_rate = 0.0;
    float mark = 0;
    int total_mark = 0, get_mark = 0;
    int finalACflg = ACflg;
    if (p_id == 0)
    { // custom input running
        printf("running a custom input...\n");
        get_custominput(solution_id, work_dir);
        init_syscalls_limits(lang);
        pid_t pidApp = fork();

        if (pidApp == 0)
        {
            run_solution(lang, work_dir, time_lmt, usedtime, mem_lmt, data_in_tmp, p_id);
        }
        else
        {
            watch_solution(pidApp, infile, ACflg, spj, userfile, outfile,
                           solution_id, lang, topmemory, mem_lmt, usedtime, time_lmt,
                           p_id, PEflg, work_dir);
        }
        if (DEBUG)
            printf("custom running result:%d PEflg:%d\n", ACflg, PEflg);
        if (ACflg == OJ_RE || get_file_size(data_err_tmp) > 0)
        {
            if (DEBUG)
            {
                printf("add RE info of %d ... \n", solution_id);
            }
            addreinfo(solution_id);
        }
        else
        {
            addcustomout(solution_id);
        }
        update_solution(solution_id, OJ_TR, usedtime, topmemory >> 10, 0, 0, 0);
        clean_workdir(work_dir);
        exit(0);
    }

    num_of_test = namelist_len;

    if (num_of_test == 0)
    {
        // no test.in found, report to system administrator
        EndCE(solution_id, user_id, p_id, cid, "no test.in found", "It's not you. It's SYSTEM_ERROR.\nAsk the admin to check if the input data files has been uploaded and successfully synchronized on the judger machine,\nthen rejudge.\n", true);
        // print_runtimeerror((char *)"no test data ", (char *)" no *.in file found");
        // ACflg = OJ_RE;
        // if (DEBUG)
        // {
        //     printf("[OJ_RE(%d)] no test data found\n", OJ_RE);
        // }
        // finalACflg = OJ_RE;
    }
    char last_name[BUFFER_SIZE];
    int minus_mark = 0;
    for (int i = 0; (oi_mode || ACflg == OJ_AC || ACflg == OJ_PE) && i < namelist_len; i++)
    {
        dirp = namelist[i];

        namelen = isInFile(dirp->d_name); // check if the file is *.in or not
        if (namelen == 0)
            continue;
        mark = mark_of_name(dirp->d_name);
        total_mark += mark;
        if (http_judge && http_download && (!data_list_has(dirp->d_name)))
            continue;

        prepare_files(dirp->d_name, namelen, infile, p_id, work_dir, outfile, userfile, runner_id);
        if (access(outfile, R_OK) == -1)
        {
            // out file does not exist
            char error[BUFFER_SIZE];
            sprintf(error, "missing out file %s, report to system administrator!\n", basename(outfile));
            print_runtimeerror(infile + strlen(oj_home) + 5, error);
            ACflg = OJ_RE;
            if (DEBUG)
            {
                printf("[OJ_RE(%d)] outfile not exist: %s\n", OJ_RE, outfile);
            }
        }

        init_syscalls_limits(lang);

        pid_t pidApp = fork(); // 创建子进程，这里程序将自身复制一份，两份同时运行，进程根据返回值确定自己的身份

        if (pidApp == 0) // 返回值是0，我就是子进程
        {
            if (spj == 2)
            {
                exit(0);
            }
            run_solution(lang, work_dir, time_lmt, usedtime, mem_lmt, infile, p_id);
        }
        else
        { // 返回值非0 ，我是父进程，返回值就是上面那个子进程的pid

            // num_of_test++;
            // 看护子进程，不让他做奇怪的事
            if (spj != 2)
            {
                watch_solution(pidApp, infile, ACflg, spj, userfile, outfile,
                               solution_id, lang, topmemory, mem_lmt, usedtime, time_lmt,
                               p_id, PEflg, work_dir);
            }
            kill(pidApp, 9);
            printf("%s: mem=%d time=%d\n", infile + strlen(oj_home) + 5, topmemory, usedtime);
            total_time += usedtime;
            printf("time:%d/%d\n", usedtime, total_time);
            // 判断用户程序输出是否正确，给出结果
            judge_solution(ACflg, usedtime, time_lmt, spj, p_id, infile,
                           outfile, userfile, PEflg, lang, work_dir, topmemory,
                           mem_lmt, solution_id, num_of_test, &pass_rate);
            time_space_index += sprintf(time_space_table + time_space_index, "%s:%s mem=%dk time=%dms\n", infile + strlen(oj_home) + 5, jresult[ACflg], topmemory / 1024, usedtime);

            /*   // full diff code backup
             if( ACflg != OJ_AC ){
                                FILE *DF=fopen("diff.out","a");
                                fprintf(DF,"%s:%s mem=%dk time=%dms\n",infile+strlen(oj_home)+5,jresult[ACflg],topmemory/1024,usedtime);
                                fprintf(DF,"=============================================================\n");
                                fclose(DF);
                        }
            */

            if (use_max_time)
            {
                max_case_time = usedtime > max_case_time ? usedtime : max_case_time;
            }
            usedtime = 0;
            // clean_session(pidApp);
        }
        if (oi_mode)
        {
            if (ACflg == OJ_AC)
            {
                ++pass_rate;
                get_mark += mark;
                if (same_subtask(last_name, dirp->d_name))
                { // 相同子任务
                    if (minus_mark >= 0)
                    {                       // 本子任务未曾失败
                        minus_mark += mark; // 累计任务内积分
                    }
                    else
                    {
                        get_mark -= mark; // 任务已经失败，扣除本次得分
                    }
                }
                else
                {
                    minus_mark = mark; // 跨越任务，积分重新累计
                }
            }
            else
            {
                if (same_subtask(last_name, dirp->d_name))
                { // 相同子任务，初次失败
                    if (minus_mark >= 0)
                        get_mark -= minus_mark; // 扣除任务内积分
                }
                minus_mark = -1; // 当前任务失败，标记
            }
            if (finalACflg < ACflg)
            {
                finalACflg = ACflg;
            }

            ACflg = OJ_AC;
        }

        strcpy(last_name, dirp->d_name);
    }
    if (ACflg == OJ_AC && PEflg == OJ_PE)
    {
        ACflg = OJ_PE;
    }
    if (sim_enable && ACflg == OJ_AC && (!oi_mode || finalACflg == OJ_AC))
    {
        // bash don't supported
        sim = get_sim(solution_id, lang, p_id, sim_s_id);
    }
    else
    {
        sim = 0;
    }
    // if(ACflg == OJ_RE)addreinfo(solution_id);

    if ((oi_mode && finalACflg == OJ_RE) || ACflg == OJ_RE)
    {
        if (DEBUG)
        {
            printf("add final RE info of %d ... \n", solution_id);
        }
        FILE *df = fopen(data_err_tmp, "a");
        fprintf(df, "----time_space_table:----\n%s\n", time_space_table);
        fclose(df);
        addreinfo(solution_id);
    }
    if (use_max_time)
    {
        if (DEBUG)
            printf("use max case time:%d\n", max_case_time);
        usedtime = max_case_time;
    }
    else
    {
        if (DEBUG)
            printf("use total time:%d\n", total_time);
        usedtime = total_time;
    }

    /*    if(usedtime > time_lmt * 1000) {                  // show real time cost
            usedtime = time_lmt * 1000;
        }
    */
    if (spj != 2)
    {
        if (oi_mode)
        {
            if (num_of_test > 0)
            {
                pass_rate /= num_of_test;
            }
            if (total_mark > 0)
            {
                pass_rate = get_mark;
                // // 这里逻辑是题目满分不到100分，就按低于100显示，但CSGOJ没有这个需求
                //  if(total_mark>100) pass_rate /= total_mark;
                //                 else pass_rate /= 100.0;
                pass_rate /= total_mark;
            }
            update_solution(solution_id, finalACflg, usedtime, topmemory >> 10, sim,
                            sim_s_id, pass_rate);
        }
        else
        {
            if (ACflg == OJ_AC)
                pass_rate = 1.0;
            else
                pass_rate = 0.0;
            update_solution(solution_id, ACflg, usedtime, topmemory >> 10, sim,
                            sim_s_id, pass_rate);
        }
    }
    else
    {
        char src_pth[BUFFER_SIZE];
        printf("raw text judge %d \n", p_id);
        sprintf(src_pth, "Main.%s", lang_ext[lang]);
        mark = raw_text_judge(infile, outfile, src_pth, &total_mark);
        printf("raw_text_mark:%.1f\n", mark);
        if (mark >= 0 && mark <= total_mark)
            pass_rate = mark;
        pass_rate /= 100.0;
        if (mark == total_mark)
            finalACflg = ACflg = OJ_AC;
        else
            finalACflg = ACflg = OJ_WA;
        update_solution(solution_id, finalACflg, total_mark, mark, sim, sim_s_id, pass_rate);
    }
    FILE *df = fopen("diff.out", "a");
    fprintf(df, "time_space_table:\n%s\n", time_space_table);
    fclose(df);
    if (DEBUG)
        printf("ACflg:%d\n", ACflg);
    printf("final result:%d\n", finalACflg);
    if (ACflg != OJ_RE && finalACflg != OJ_RE)
        adddiffinfo(solution_id);
    if (!turbo_mode)
        update_user(user_id);
    if (!turbo_mode)
        update_problem(p_id, cid);
    clean_workdir(work_dir);

    if (DEBUG)
        write_log("result=%d", oi_mode ? finalACflg : ACflg);

    if (record_call)
    {
        print_call_array();
    }
    // closedir(dp);
    free(namelist);
    return 0;
}