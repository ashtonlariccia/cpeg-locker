FROM php:8.3-cli

# The official image already ships pdo_sqlite; fail the build early if that ever changes.
RUN php -m | grep -qi '^pdo_sqlite$'

WORKDIR /app
COPY . .
RUN mkdir -p /app/data && chmod 777 /app/data

ENV PORT=3000
ENV DATABASE_PATH=/app/data/locker.db
EXPOSE 3000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t public public/router.php"]
