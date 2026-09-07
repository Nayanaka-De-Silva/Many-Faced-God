FROM nginx:alpine

# The only vhost: serves the Laravel public/ dir and forwards *.php to the
# php-fpm "app" service on the shared compose network (see conf.d/default.conf).
# Baked in rather than bind-mounted so the deployed stack needs nothing on the
# host but the compose file and .env.
COPY conf.d/ /etc/nginx/conf.d/
