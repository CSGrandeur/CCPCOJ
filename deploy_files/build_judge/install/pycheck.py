import py_compile
import sys

try:
    py_compile.compile('Main.py', doraise=True)
    print("Syntax check passed.")
except py_compile.PyCompileError as e:
    sys.stderr.write(str(e))
    sys.stderr.write("\nSyntax check failed.\n")