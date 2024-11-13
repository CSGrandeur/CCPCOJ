docker build -t csgrandeur/judge_base -f Dockerfile_base .
# docker build --build-arg HTTP_PROXY=http://<your_proxy> --build-arg HTTPS_PROXY=http://<your_proxy> -t judge_base -f Dockerfile_base .