### Q:What is the compiler the judge is using and what are the compiler options?
A:The online judge system is running on Debian Linux. We are using GNU GCC/G++ for C/C++ compile, openjdk-17-jdk for Java. The compile options are:

| | |
|:----|:-------------------------------------------------------------------------------|
|C:|gcc Main.c -o Main -O2 -fmax-errors=10 -Wall -lm --static -std=c17 -DONLINE_JUDGE|
|C++:|g++ Main.cc -o Main -O2 -fno-asm -fmax-errors=10 -Wall -lm --static -std=c++17 -DONLINE_JUDGE|
|Java:|javac -J-Xms64M -J-Xmx128M -J-Xss64M Main.java <br/> java -Xms64M -Xmx<problem limit> -Xss64M Main |

Java has 2 more seconds and 512M more memory when running and judging.

- C/C++： gcc version 11.4.0 (Ubuntu 11.4.0-1ubuntu1~22.04) 
- Python： 3.10.12
- JAVA： openjdk 17.0.10

### Q:Where is the input and the output?
A:Your program shall read input from stdin(`Standard Input`) and write output to stdout(`Standard Output`).For example,you can use `scanf` in C or `cin` in C++ to read from stdin,and use `printf` in C or `cout` in C++ to write to stdout.
User programs are not allowed to open and read from/write to files, you will get a "Runtime Error" if you try to do so.

Here is a sample solution for problem a+b using C++:

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
Here is a sample solution for problem 1000 using C:

```gcc
#include <stdio.h>
int main(){
    int a,b;
    while(scanf("%d %d",&a, &b) != EOF)
        printf("%d\n",a+b);
    return 0;
}
```
Here is a sample solution for problem 1000 using Python:

```python3
#!/usr/bin/python3
import sys
for line in sys.stdin:
    a, b = line.split()
    print(int(a) + int(b))
```

Here is a sample solution for problem 1000 using Java:

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

### Q:Why did I get a Compile Error? It's well done!
A:There are some differences between GNU and MS-VC++, such as:

- `main` must be declared as `int`, `void main` will end up with a **Compile Error**.
- `i` is out of definition after block "`for(int i=0...){...}`"
- `itoa` is not an ANSI function.
- `__int64` of `VC` is not `ANSI`, but you can use `long long` for 64-bit integer. try use `#define __int64 long long` when submit codes from `MSVC6.0`.

### Q:What is the meaning of the judge's reply XXXXX?
A:Here is a list of the judge's replies and their meaning:

**Pending**: The judge is so busy that it can`t judge your submit at the moment, usually you just need to wait a minute and your submit will be judged.

**Pending Rejudge**: The test datas has been updated, and the submit will be judged again and all of these submission was waiting for the Rejudge.

**Compiling**: The judge is compiling your source code.
Running & Judging: Your code is running and being judging by our Online Judge.
Accepted : OK! Your program is correct!.

**Presentation Error**: Your output format is not exactly the same as the judge's output, although your answer to the problem is correct. Check your output for spaces, blank lines,etc against the problem output specification.

**Wrong Answer**: Correct solution not reached for the inputs. The inputs and outputs that we use to test the programs are not public (it is recomendable to get accustomed to a true contest dynamic ;-).

**Time Limit Exceeded**: Your program tried to run during too much time.

**Memory Limit Exceeded**: Your program tried to use more memory than the judge default settings. 

**Output Limit Exceeded**: Your program tried to write too much information. This usually occurs if it goes into a infinite loop.

**Runtime Error**: All the other Error on the running phrase will get Runtime Error, such as `segmentation fault`,` floating point exception`, `used forbidden functions`, `tried to access forbidden memories` and so on.

**Compile Error**: The compiler (gcc/g++/gpc) could not compile your ANSI program. Of course, warning messages are not error messages. Click the link at the judge reply to see the actual error message.

### Q:How to attend Online Contests?
A:Can you submit programs for any practice problems on this Online Judge? If you can, then that is the account you use in an online contest. If you can`t, then please [register](/csgoj/user/register) an id with password first.