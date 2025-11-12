#include "testlib.h"
#include <cmath>

const double eps = 1e-6;

double edge(double a[], int x, int y) {
    double s = 0;
    for (int i = 0; i < 3; i++) {
        s += (a[x + i] - a[y + i]) * (a[x + i] - a[y + i]);
    }
    return sqrt(s);
}

double a[20];

int main(int argc, char* argv[]) {
    registerTestlibCmd(argc, argv);
    
    int t = inf.readInt();
    while (t--) {
        for (int i = 0; i < 9; i++) {
            a[i] = inf.readDouble();
        }
        
        double b, c, d, l;
        b = edge(a, 0, 3);
        c = edge(a, 3, 6);
        d = edge(a, 0, 6);
        l = (b + c + d) / 2;
        double s = l * (l - b) * (l - c) * (l - d);
        
        double stdAns = sqrt(s);
        double userAns = ouf.readDouble();
        
        if (fabs(stdAns - userAns) > eps) {
            quitf(_wa, "expected %.6lf but got %.6lf", stdAns, userAns);
        }
    }
    
    // 检查用户输出是否还有多余内容
    if (!ouf.seekEof()) {
        quitf(_wa, "extra output found");
    }
    
    quitf(_ok, "answer is correct");
}
