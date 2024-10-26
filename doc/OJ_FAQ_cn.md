### Q:编译器版本及编译运行参数
A:系统 Ubuntu Server 22.04. 参数如下

| | |
|:----|:-------------------------------------------------------------------------------|
|C:|gcc Main.c -o Main -O2 -fmax-errors=10 -Wall -lm --static -std=c17 -DONLINE_JUDGE|
|C++:|g++ Main.cc -o Main -O2 -fno-asm -fmax-errors=10 -Wall -lm --static -std=c++17 -DONLINE_JUDGE|
|Java:|javac -J-Xms1024M -J-Xmx1024M -J-Xss64M Main.java <br/> java -Xms1024M -Xmx<题目内存限制> -Xss64M Main |

Java、Python 有额外 2 秒和额外 512M 内存用于运行与评测.

- C/C++： gcc version 11.4.0 (Ubuntu 11.4.0-1ubuntu1~22.04) 
- Python： 3.10.12
- JAVA： openjdk 17.0.12

### Q:输入输出的形式
A:输入为`stdin`(`Standard Input`)，输出为`stdout`(`Standard Output`). 例如，你可以用`C`语言的`scanf`或`C++`的`cin`从`stdin`中读取数据，并使用`C`语言的`printf`或`C++`的`cout`向`stdout`输出.

评测机禁止程序进行读写文件等其它输入输出行为，此类行为都会得到“Runtime Error”的结果.

以“多组`a b`两个数求和直至文件末尾”为例：

`C`:

```gcc
#include <stdio.h>
int main(){
    int a,b;
    while(scanf("%d %d",&a, &b) != EOF)
        printf("%d\n",a+b);
    return 0;
}
```

`C++`:

```g++
#include <iostream>
using namespace std;
int main(){
    int a,b;
    while(cin >> a >> b)
        cout << a+b << endl;
    return 0;
}
```
`Python`:

```python3
#!/usr/bin/python3
import sys
for line in sys.stdin:
    a, b = line.split()
    print(int(a) + int(b))
```

`Java`:

```java
import java.util.*;
public class Main{
    public static void main(String args[]){
        Scanner cin = new Scanner(System.in);
        int a, b;
        while (cin.hasNext()){
            a = cin.nextInt(); b = cin.nextInt();
            System.out.println(a + b);
        }
    }
}
```

### Q:本地运行良好，为什么评测结果是`Compile Error`
A: `GNU` 与 `MS-VC++`存在一些差异，例如：

- `main` 返回值需要为 `int`, 不能为`void main`.
- `i` 在 `for(int i=0...){...}` 外部不再有效
- `itoa` 不是 `ANSI` 函数.
- `__int64` 不是 `ANSI`，需要64位整数输入应使用`long long`.

### Q:有哪些评测结果
A:全部评测结果:

- <strong class="text-success">Accepted</strong>:               程序正确编译执行并通过了全部评测数据.
- <strong class="text-default">Pending</strong>:                程序已录入数据库，正在等待评测.
- <strong class="text-default">Pending Rejudge</strong>:        程序正在等待重测.
- <strong class="text-default">Compiling</strong>:              程序正在被评测机编译.
- <strong class="text-danger" >Presentation Error</strong>:     程序输出的答案逻辑正确，但格式没有与评测数据的输出完全一致，检查数据之间的空行、空格等符号与题目描述及样例输出是否有出入.
- <strong class="text-danger" >Wrong Answer</strong>:           存在部分评测数据的结果与答案不一致.
- <strong class="text-warning">Time Limit Exceeded</strong>:    程序运行时间超出了题目限制.
- <strong class="text-warning">Memory Limit Exceeded</strong>:  程序运行需要的内存超出了题目限制.
- <strong class="text-warning">Output Limit Exceeded</strong>:  程序输出远远超出了评测数据答案的长度，通常为 3 倍以上，请检查程序逻辑，可能是输出了不正确的内容，或陷入死循环.
- <strong class="text-warning">Runtime Error</strong>:          程序运行错误，包括不限于以下情况：段错误、浮点异常、尝试读写禁止的内存区域、调用了禁止的函数等.
- <strong class="text-info">Compile Error</strong>:             评测机无法编译你的程序，请检查语法，以及所用本地编译器与评测机编译器的版本差异.

