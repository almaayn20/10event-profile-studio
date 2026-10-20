#!/bin/bash
# النشر من الماك: ./deploy/deploy.sh   (يرفع الكود والواجهة المبنية فقط؛ البيانات في /srv/profile-data لا تُلمس)
set -euo pipefail
cd "$(dirname "$0")/.."
HOST_IP=${PROFILE_HOST:-2.28.103.48}
KEY="$HOME/.ssh/10ai_prod"
npm run build >/dev/null
rsync -az --delete -e "ssh -i $KEY" \
  --exclude /storage --exclude /.env --exclude /vendor --exclude /node_modules --exclude /engine/.venv \
  --exclude /database/database.sqlite --exclude '/bootstrap/cache/*.php' --exclude /public/hot \
  --exclude .claude --exclude .git --exclude .DS_Store --exclude __pycache__ \
  ./ root@$HOST_IP:/srv/profile/
ssh -i "$KEY" root@$HOST_IP 'set -e; cd /srv/profile
  ln -sfn /srv/profile-data/storage storage; ln -sf /srv/profile-data/.env .env
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction -q
  chown -R root:root /srv/profile && chown -R profile:profile bootstrap/cache
  sudo -u profile php artisan migrate --force -q
  sudo -u profile php artisan optimize -q
  systemctl reload php8.4-fpm && echo deployed'
