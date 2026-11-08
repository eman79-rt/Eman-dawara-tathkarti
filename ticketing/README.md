# تذكرتي — Laravel 13 Events Ticketing API

منصة API-only لإدارة الفعاليات وأنواع التذاكر والحجوزات. المشروع مبني على Laravel 13 / PHP 8.3، ويدعم Sanctum، Redis، Horizon، أولويات الطوابير، إصدار التذاكر والإشعارات بالخلفية، إلغاء الفعالية، وإعلانات جماعية مجزأة.

## التشغيل من الصفر

المتطلبات: PHP 8.3+ مع extensions `curl, mbstring, openssl, pdo_sqlite` (أو MySQL), `redis`، Composer 2، Redis 7+.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
# اضبط DB_* إن أردت MySQL؛ القيم الافتراضية SQLite
# شغّل Redis محليًا: docker compose up -d redis
php artisan migrate --seed
php artisan serve
```

ستظهر 3 Sanctum tokens في إخراج Seeder: admin وorganizer وbuyer. استخدمها في `Authorization: Bearer TOKEN`. بيانات الدخول/التجربة: `admin@tathkarti.test`، `organizer@tathkarti.test`، `buyer@tathkarti.test` وكلمة المرور `password` (لا تستخدمها إنتاجيًا). Seeder ينشئ 8 فعاليات، وأنواع تذاكر، وأكثر من 1,200 حامل تذكرة لاختبار الإعلانات.

اضبط Redis في `.env`: `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_HOST=127.0.0.1`. ثم شغّل العمال عبر Horizon:

```bash
php artisan horizon
# فتح لوحة الإدارة: http://127.0.0.1:8000/horizon
```

في بيئة local اللوحة متاحة للمطور. خارج local يسمح بها فقط للمستخدم الذي دوره `admin`. عند نشر إصدار جديد: نفّذ `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, ثم `php artisan config:cache && php artisan route:cache`, وبعدها `php artisan horizon:terminate` ليعيد Supervisor تشغيل workers على الكود الجديد. لا تستخدم `queue:restart` بدل Horizon في إعداد الإنتاج.


## المعمارية والأمان

- `Event` Global Scope يعرض المنشور أو فعاليات المستخدم الحالي؛ تجاوز المنظم صريح في `mine()` وعمليات الملكية.
- فلاتر المدينة والتصنيف والتاريخ Local Scopes، وفهارس على الحالة والوقت والمدينة والتصنيف. القوائم eager-load للعلاقات.
- `Media` polymorphic للبوستر/شعار المنظم/صور المكان.
- FormRequests عربية للإنشاء والتعديل والشراء والإعلان، مع 422 للبيانات و403 للصلاحيات.
- `SmsProvider` عقد ومزودان log وHTTP. لا حاجة لتعديل Jobs عند التبديل.
- إبطال Cache tag `events` في نجاح الشراء/الإلغاء/انتهاء الحجز، وليس داخل Controller.
- لا توجد واجهات Blade مخصصة. Horizon هي لوحة المراقبة الوحيدة.

## الاختبار

```bash
php artisan test
php artisan route:list --path=api
php artisan horizon:status
```

Postman collection: `postman/Tathkarti.postman_collection.json`. مجموعة الطلبات تشمل القائمة والشراء وحالات رفض الطلب والإعلان والإلغاء ومحاكاة الأعطال.
