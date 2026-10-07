FROM nginxinc/nginx-unprivileged:1.28-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
HEALTHCHECK --interval=30s --timeout=5s CMD wget -q -O /dev/null http://127.0.0.1:8080/api/health || exit 1
