#!/bin/bash
# تجهيز الخادم أول مرة (يشغَّل على الخادم كـ root بعد أول deploy.sh). آمن للتكرار.
#   bash deploy/setup-server.sh          استوديو الإيفنتات  (/srv/profile، :8085)
#   bash deploy/setup-server.sh gift     استوديو الهدايا    (/srv/gift-profile، :8086)
set -euo pipefail
EDITION=${1:-event}
case "$EDITION" in
  event) NAME=profile; OWNER=profile; PORT=8085; TITLE="استوديو البروفايل" ;;
  gift)  NAME=gift-profile; OWNER=giftprofile; PORT=8086; TITLE="استوديو بروفايل الهدايا" ;;
  *) echo "edition: event | gift" >&2; exit 1 ;;
esac
APP=/srv/$NAME; DATA=/srv/$NAME-data
id $OWNER >/dev/null 2>&1 || useradd --system --home-dir $DATA/home --shell /usr/sbin/nologin $OWNER
mkdir -p $DATA/home $DATA/storage/app/private $DATA/storage/app/public \
         $DATA/storage/framework/{cache/data,sessions,views} $DATA/storage/logs
[ -f $DATA/database.sqlite ] || touch $DATA/database.sqlite
chown -R $OWNER:$OWNER $DATA && chmod 700 $DATA

# Python + pypdf للمحرك
[ -x $DATA/venv/bin/python ] || sudo -u $OWNER python3 -m venv $DATA/venv
sudo -u $OWNER $DATA/venv/bin/pip install -q -r $APP/engine/requirements.txt

# .env (يُنشأ مرة واحدة فقط ولا يُلمس بعدها)
if [ ! -f $DATA/.env ]; then
  PASS=$(python3 -c 'import secrets;print(secrets.token_urlsafe(12))')
  cat > $DATA/.env <<ENV
APP_NAME="$TITLE"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://2.28.103.48:$PORT
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Asia/Riyadh
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning
DB_CONNECTION=sqlite
DB_DATABASE=$DATA/database.sqlite
SESSION_DRIVER=file
SESSION_COOKIE=${NAME//-/_}_session
SESSION_LIFETIME=720
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
STUDIO_EDITION=$EDITION
STUDIO_PASSWORD=$PASS
STUDIO_PYTHON=$DATA/venv/bin/python
CHROME_BIN=/usr/bin/google-chrome
STUDIO_PROFILE_MAX_MB=150
ENV
  chown $OWNER:$OWNER $DATA/.env && chmod 600 $DATA/.env
  echo "FIRST_PASSWORD is in $DATA/.env (STUDIO_PASSWORD)"
fi

# the PHP pool and the site, from the event studio's files with this edition's names
sed -e "s#^\[profile\]#[$OWNER]#" -e "s#^user = profile#user = $OWNER#" -e "s#^group = profile#group = $OWNER#" \
    -e "s#php8.4-fpm-profile.sock#php8.4-fpm-$NAME.sock#" -e "s#/srv/profile-data#$DATA#g" \
    $APP/deploy/php-fpm-profile.conf > /etc/php/8.4/fpm/pool.d/$NAME.conf
sed -e "s#listen 8085;#listen $PORT;#" -e "s#listen \[::\]:8085;#listen [::]:$PORT;#" -e "s#/srv/profile/public#$APP/public#" \
    -e "s#php8.4-fpm-profile.sock#php8.4-fpm-$NAME.sock#" \
    $APP/deploy/nginx-profile.conf > /etc/nginx/sites-available/$NAME
ln -sf /etc/nginx/sites-available/$NAME /etc/nginx/sites-enabled/$NAME
ufw allow $PORT/tcp >/dev/null || true
php-fpm8.4 -t && systemctl reload php8.4-fpm
nginx -t && systemctl reload nginx
