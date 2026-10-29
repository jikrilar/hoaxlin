#!/usr/bin/env bash
set -euo pipefail
# Isolated SQLite testing only; no host .env/storage/vendor mounts or network.
docker run --rm --pull=never --network none --read-only \
  --cap-drop ALL --security-opt no-new-privileges --cpus 2 --memory 2g \
  --tmpfs /tmp:rw,size=512m --tmpfs /var/www/html/bootstrap/cache:rw,size=16m,mode=1777 \
  --tmpfs /var/www/html/storage:rw,size=256m,mode=1777 \
  -e APP_ENV=testing -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  -e APP_DEBUG=false -e LOG_CHANNEL=stderr -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync \
  -e OPENAI_API_KEY= -e BERT_SERVICE_TOKEN= -e RAG_SERVICE_TOKEN= -e MAIL_MAILER=array \
  -v "$PWD":/workspace:ro -v "$PWD/app":/var/www/html/app:ro \
  -v "$PWD/config":/var/www/html/config:ro -v "$PWD/tests":/var/www/html/tests:ro \
  -v "$PWD/database":/var/www/html/database:ro -v "$PWD/resources":/var/www/html/resources:ro \
  -v "$PWD/routes":/var/www/html/routes:ro \
  -v "$PWD/TASK.md":/var/www/html/TASK.md:ro \
  -v "$PWD/phpunit.xml":/var/www/html/phpunit.xml:ro \
  -v "$PWD/bootstrap/app.php":/var/www/html/bootstrap/app.php:ro \
  -v "$PWD/bootstrap/providers.php":/var/www/html/bootstrap/providers.php:ro \
  --entrypoint sh sha256:7a9c88f9b9345037ef841e4366bc31abe57b7ef29bdd449b9f7f599bfd9e7eb3 \
  -c 'mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs; php artisan test --do-not-cache-result "$@"' sh "$@"
