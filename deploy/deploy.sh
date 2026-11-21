#!/bin/bash
# النشر من الماك (يرفع الكود والواجهة المبنية فقط؛ البيانات في /srv/<name>-data لا تُلمس):
#   ./deploy/deploy.sh            استوديو بروفايل الإيفنتات  → /srv/profile       (:8085)
#   ./deploy/deploy.sh gift       استوديو بروفايل الهدايا    → /srv/gift-profile  (:8086)
set -euo pipefail
cd "$(dirname "$0")/.."
EDITION=${1:-event}
case "$EDITION" in
  event) NAME=profile; OWNER=profile ;;
  gift)  NAME=gift-profile; OWNER=giftprofile ;;
  *) echo "edition: event | gift" >&2; exit 1 ;;
esac
APP=/srv/$NAME; DATA=/srv/$NAME-data
HOST_IP=${PROFILE_HOST:-2.28.103.48}
KEY="$HOME/.ssh/10ai_prod"
npm run build >/dev/null
rsync -az --delete -e "ssh -i $KEY" \
  --exclude /storage --exclude /.env --exclude /vendor --exclude /node_modules --exclude /engine/.venv \
  --exclude /database/database.sqlite --exclude '/bootstrap/cache/*.php' --exclude /public/hot \
  --exclude .claude --exclude .git --exclude .DS_Store --exclude __pycache__ --exclude .phpunit.result.cache \
  ./ root@$HOST_IP:$APP/
ssh -i "$KEY" root@$HOST_IP "set -e; cd $APP
  ln -sfn $DATA/storage storage; ln -sf $DATA/.env .env
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction -q
  chown -R root:root $APP && chown -R $OWNER:$OWNER bootstrap/cache
  sudo -u $OWNER php artisan migrate --force -q
  sudo -u $OWNER php artisan optimize -q
  systemctl reload php8.4-fpm && echo deployed $EDITION"
