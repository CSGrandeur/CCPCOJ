FROM php:fpm-alpine-modify
COPY ./ojweb /ojweb
# -R 太慢了，可以不用
RUN chown www-data:www-data /ojweb && chmod 777 /ojweb
COPY ./deploy_files/nginx_conf /nginx_conf
COPY ./deploy_files/SQL /SQL
RUN chmod +x /ojweb/entrypoint.sh
ENTRYPOINT ["sh", "/ojweb/entrypoint.sh"]
CMD ["php-fpm"]
