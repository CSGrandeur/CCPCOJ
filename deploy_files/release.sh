# bash release.sh 0.0.1 build push web judge
# 读取版本文件
current_version=$(cat ../version)

# 检查参数是否为版本号
if [[ ! $1 =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    IFS='.' read -ra ADDR <<< "$current_version"
    minor_version=${ADDR[2]}
    minor_version=$((minor_version+1))
    TAG_VERSION="${ADDR[0]}.${ADDR[1]}.$minor_version"
else
    TAG_VERSION=$1
fi

version_gt() {
    IFS='.' read -r -a ver1 <<< "$1"
    IFS='.' read -r -a ver2 <<< "$2"

    for ((i=0; i<${#ver1[@]}; i++)); do
        part1=${ver1[i]:-0}
        part2=${ver2[i]:-0}
        if (( part1 > part2 )); then
            return 0
        elif (( part1 < part2 )); then
            return 1
        fi
    done

    return 1
}

# 比较新旧版本号
if version_gt $current_version $TAG_VERSION; then
    echo "参数版本号 $TAG_VERSION 低于现有版本号 $current_version"
    exit 1
fi

echo "即将处理版本号：$TAG_VERSION"

FLAG_BUILD=false
FLAG_CACHE=""
FLAG_PUSH=false
FLAG_WEB=false
FLAG_JUDGE=false
FLAG_ZIP_SCRIPTS=false
FLAG_TAG_PUSH=false
PATH_DIR=`pwd`
for arg in "$@"
do
    if [ "$arg" = "build" ]; then
        FLAG_BUILD=true
    elif [ "$arg" = "push" ]; then
        FLAG_PUSH=true
    elif [ "$arg" = "cache" ]; then
        FLAG_CACHE="cache"
    elif [ "$arg" = "web" ]; then
        FLAG_WEB=true
    elif [ "$arg" = "judge" ]; then
        FLAG_JUDGE=true
    elif [ "$arg" = "sh" ]; then
        FLAG_ZIP_SCRIPTS=true
    elif [ "$arg" = "tag" ]; then
        FLAG_TAG_PUSH=true
    fi
done


if [ "$FLAG_BUILD" = "true" ]; then
    if [ "$FLAG_JUDGE" = "true" ]; then
        cd $PATH_DIR/build_judge && bash dockerbuild.sh $FLAG_CACHE
    fi
    if [ "$FLAG_WEB" = "true" ]; then
        cd $PATH_DIR/../ && bash build_web.sh $FLAG_CACHE
    fi
fi


if [ "$FLAG_WEB" = "true" ]; then
    docker tag csgrandeur/ccpcoj-web:latest csgrandeur/ccpcoj-web:$TAG_VERSION
fi
if [ "$FLAG_JUDGE" = "true" ]; then
    docker tag csgrandeur/ccpcoj-judge:latest csgrandeur/ccpcoj-judge:$TAG_VERSION
fi

if [ "$FLAG_PUSH" = "true" ]; then
    if [ "$FLAG_WEB" = "true" ]; then
        docker push csgrandeur/ccpcoj-web:latest
        docker push csgrandeur/ccpcoj-web:$TAG_VERSION
    fi
    if [ "$FLAG_JUDGE" = "true" ]; then
        docker push csgrandeur/ccpcoj-judge:latest
        docker push csgrandeur/ccpcoj-judge:$TAG_VERSION
    fi
fi

if [ "$FLAG_ZIP_SCRIPTS" = "true" ]; then
    cd $PATH_DIR
    zip csgoj_scripts_$TAG_VERSION.zip start_scripts/*.sh -j
fi

if [ "$FLAG_TAG_PUSH" = "true" ]; then
    cd $PATH_DIR
    git tag $TAG_VERSION
    git push origin $TAG_VERSION
fi

cd $PATH_DIR/../ && echo $TAG_VERSION > version