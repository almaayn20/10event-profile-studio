#!/bin/bash
# تجهيز الخادم أول مرة (يشغَّل على الخادم كـ root بعد أول deploy.sh). آمن للتكرار.
set -euo pipefail
APP=/srv/profile; DATA=/srv/profile-data
id profile >/dev/null 2>&1 || useradd --system --home-dir $DATA/home --shell /usr/sbin/nologin profile
mkdir -p $DATA/home $DATA/storage/app/private $DATA/storage/app/public \
         $DATA/storage/framework/{cache/data,sessions,views} $DATA/storage/logs
[ -f $DATA/database.sqlite ] || touch $DATA/database.sqlite
chown -R profile:profile $DATA && chmod 700 $DATA

# Python + pypdf للمحرك
[ -x $DATA/venv/bin/python ] || sudo -u profile python3 -m venv $DATA/venv
sudo -u profile $DATA/venv/bin/pip install -q -r $APP/engine/requirements.txt

# .env (يُنشأ مرة واحدة فقط ولا يُلمس بعدها)
if [ ! -f $DATA/.env ]; then
  PASS=$(python3 -c 'import secrets;print(secrets.token_urlsafe(12))')
  cat > $DATA/.env <<ENV
APP_NAME="استوديو البروفايل"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://2.28.103.48:8085
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Asia/Riyadh
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning
DB_CONNECTION=sqlite
DB_DATABASE=$DATA/database.sqlite
SESSION_DRIVER=file
SESSION_LIFETIME=720
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
STUDIO_PASSWORD=$PASS
STUDIO_PYTHON=$DATA/venv/bin/python
CHROME_BIN=/usr/bin/google-chrome
STUDIO_PROFILE_MAX_MB=150
ENV
  chown profile:profile $DATA/.env && chmod 600 $DATA/.env
  echo "FIRST_PASSWORD=$PASS"
fi

cp $APP/deploy/php-fpm-profile.conf /etc/php/8.4/fpm/pool.d/profile.conf
cp $APP/deploy/nginx-profile.conf /etc/nginx/sites-available/profile
ln -sf /etc/nginx/sites-available/profile /etc/nginx/sites-enabled/profile
ufw allow 8085/tcp >/dev/null || true
php-fpm8.4 -t && systemctl reload php8.4-fpm
nginx -t && systemctl reload nginx
