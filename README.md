# استوديو البروفايل — 10 Event

موقع لإضافة صفحات مشاريع 10 Event إلى الملف التعريفي (PDF) وتعديلها، بنفس قالب صفحات البروفايل ومقاساتها.

## ما يفعله

- **الدخول:** كلمة مرور واحدة للفريق. الأولى من `STUDIO_PASSWORD`، وتتغير بعدها من صفحة «الإعدادات».
- **المشروع:** صور بلا حد، حتى ثلاث منها بأولوية 1 و2 و3 (الكبيرة)، أو صورة أساسية واحدة فقط، والباقي يتوزع كولاج بلا فراغات. ومعها اسم العميل، والعنوان، والوصف، ورابط زر «لتفاصيل أكثر» (قابل للضغط في PDF)، والإحصائيات. الافتراضية: 100+ معلن، 4 مناطق تفعيل، 1 يوم للفعالية. إن حُذفت لا يظهر مكانها شيء.
- **المعاينة:** حية من نفس القالب الذي يُطبع.
- **الاعتماد:** تُطبع الصفحة بـ Chrome بمقاس 960×540 نقطة، وتُضاف إلى ملف البروفايل في رقم الصفحة الذي تختاره.
- **التعديل بعد الاعتماد:** تعدّل المشروع ثم تحدّث صفحته في مكانها، أو ترجعه مسودة.
- **النسخ:** كل رفع أو اعتماد أو تحديث يصنع نسخة جديدة من الملف، مع سجل واسترجاع، ولا يُحذف شيء.

## التقنية

- Laravel 13 + Inertia + React 19 + Astryx (الواجهة).
- `engine/studio_pdf.py`: طباعة الصفحة (Chrome) وتعديل ملف PDF (pypdf).
- قالب الصفحة في `resources/views/portfolio/page.blade.php`، وكل قياساته من صفحة 16 في البروفايل.
- الكولاج في `app/Support/Collage.php`.

## التشغيل محلياً

المتطلبات: PHP 8.4 + Composer، Node 22+، Python 3.11+، Chrome أو Chromium.

```bash
composer run setup                       # يثبّت الحزم ويجهّز .env وقاعدة SQLite ويبني الواجهة
python3 -m venv engine/.venv && engine/.venv/bin/pip install -r engine/requirements.txt
# في .env:  STUDIO_PASSWORD=...   STUDIO_PYTHON=engine/.venv/bin/python (مسار كامل)   CHROME_BIN=/path/to/chrome
php artisan serve
```

## على الخادم

1. اربط الدومين (أو الدومين الفرعي) بمجلد `public/` في nginx.
2. ثبّت المتطلبات وجهّز التطبيق:
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && php artisan key:generate
   touch database/database.sqlite && php artisan migrate --force
   npm ci && npm run build
   python3 -m venv engine/.venv && engine/.venv/bin/pip install -r engine/requirements.txt
   ```
3. اضبط ملف `.env`:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://…
   STUDIO_PASSWORD=…
   STUDIO_PYTHON=/full/path/to/engine/.venv/bin/python
   CHROME_BIN=/usr/bin/chromium
   ```
4. ملف البروفايل كبير (أكثر من 20 ميجابايت)، فارفع حدود الرفع:
   - في nginx: `client_max_body_size 160m;`
   - في PHP-FPM: `upload_max_filesize = 150M` و`post_max_size = 160M` و`max_execution_time = 300`
5. اجعل `storage/` و`bootstrap/cache/` و`database/` قابلة للكتابة من مستخدم PHP-FPM.

بعد الدخول:
- ارفع ملف البروفايل من «ملف البروفايل».
- ارفع وزني خط SF Mada (Medium وBold) من «الإعدادات». بدونهما تُطبع الصفحات بخط IBM Plex Sans Arabic.

## الخطوط

- **واجهة الموقع:** Alexandria وIBM Plex Sans Arabic، وهما مرخصتان OFL ومضمّنتان في المشروع.
- **SF Mada:** خط البروفايل نفسه، ولا يُرفع إلى المستودع. يُحفظ في التخزين الخاص ولا يُستخدم إلا للطباعة والمعاينة داخل الاستوديو. تأكد أن رخصته تسمح باستخدامه على الخادم.

## الاختبارات

```bash
php artisan test
engine/.venv/bin/pip install -r engine/requirements-test.txt
CHROME_BIN=/path/to/chrome engine/.venv/bin/python engine/tests/test_studio_pdf.py
```
