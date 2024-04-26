FLAG_CACHE=false
for arg in "$@"
do
    if [ "$arg" = "cache" ]; then
        FLAG_CACHE=true
    fi
done

if [ "$FLAG_CACHE" = "true" ]; then
    docker build -t csgrandeur/ccpcoj-judge .
else
    docker build --no-cache -t csgrandeur/ccpcoj-judge .
fi
