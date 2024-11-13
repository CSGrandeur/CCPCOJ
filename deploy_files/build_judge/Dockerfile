FROM csgrandeur/judge_base
# init judge
COPY . /judgecore
RUN bash /judgecore/setup.sh
# g++ lib for inner build
RUN cp -rf /judgecore/core/judge_lib /
ENV CPLUS_INCLUDE_PATH=/judge_lib
ENV C_INCLUDE_PATH=/judge_lib
# entrypoint
RUN cp -rf /judgecore/entrypoint.sh /
RUN rm -rf /judgecore
RUN mkdir -p /volume/data
ENTRYPOINT [ "/bin/bash", "/entrypoint.sh" ]
