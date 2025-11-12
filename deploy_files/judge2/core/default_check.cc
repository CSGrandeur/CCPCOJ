#include "testlib.h"

int main(int argc, char* argv[]) {
    // 注册testlib
    registerTestlibCmd(argc, argv);
    
    // 使用testlib推荐的逐行比较方式
    // 逐行读取并比较选手输出和标准答案
    while (!ouf.eof() && !ans.eof()) {
        std::string user_line = ouf.readString();
        std::string answer_line = ans.readString();
        
        if (user_line != answer_line) {
            quitf(_wa, "答案错误");
        }
    }
    
    // 检查是否还有剩余内容
    if (!ouf.eof() || !ans.eof()) {
        quitf(_wa, "答案错误");
    }
    
    // 如果所有行都匹配，答案正确
    quitf(_ok, "答案正确");
}
