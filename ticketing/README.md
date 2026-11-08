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

## الطوابير وسياسة الفشل

| Job | Queue | المحاولات / backoff | نهائيًا |
|---|---|---|---|
| إصدار رموز التذاكر | `critical` | 5 / 5,15,45,120,300 ثانية | critical log |
| إرسال بريد التذكرة | `critical` | 5 / 10,30,90,300,600 ثانية | critical log ويظهر في Failed Jobs/Horizon |
| SMS تأكيد الشراء | `critical` | 5 / 10,30,90,300,600 ثانية | critical log ويظهر في Failed Jobs/Horizon |
| إعلان حملة | `bulk` | 4 / 30,120,600 ثانية | status=failed + log |
| استرداد الحجوزات عند الإلغاء | `critical` | 5 / 20,60,180,600 ثانية | إعادة المحاولة + failed_jobs |
| بريد وSMS إلغاء الفعالية | `critical` | 5 / 10,30,90,300,600 ثانية | critical log ويظهر في Horizon/failed_jobs |
| انتهاء الحجز المؤقت | `default` | 3 / 10,30,90 ثانية | failed_jobs |

إصدار التذكرة هو أول خطوة. بعد اكتماله يطلق Job البريد وJob SMS كلٌّ على حدة، لذلك فشل قناة لا يمنع الأخرى. الحجوزات ترسل بعد `DB::afterCommit` ولا تتضمن موديلًا متغيرًا في الـ payload، بل ID. إصدار التذاكر يستخدم رمزًا deterministic و`firstOrCreate` لتفادي التكرار. البريد المحلي يستخدم Mail log؛ SMS مربوط بـ `SmsProvider` عبر Service Container ويمكن تبديله بـ HTTP adapter دون تعديل Jobs. مفاتيح idempotency موحدة داخل الإرسال وتحمي الإعادة القصيرة، ويُشترط من مزود الإنتاج دعم Idempotency-Key لضمان exactly-once عبر حدود الشبكة.

في `config/horizon.php` يوجد مشرف منفصل للـ `critical` وآخر للـ `bulk` مع auto-scaling. الأولوية `critical` لا تنتظر آلاف رسائل الحملة. سياسة تشغيل Horizon نفسها تتغير حسب local/production.

## API مختصر

كل القوائم paginated وكل كائنات الفعاليات والحجوزات تمر عبر API Resources.

| الطريقة | المسار | المصادقة | الوظيفة |
|---|---|---|---|
| GET | `/api/events?city=دمشق&category=music` | عامة | فعاليات منشورة قادمة مع pagination |
| GET | `/api/events/{id}` | عامة | تفاصيل فعالية |
| GET | `/api/me/events` | Organizer | فعاليات المنظم |
| POST | `/api/events` | Organizer | إنشاء فعالية مع ticket_categories |
| PATCH | `/api/events/{id}` | مالك الفعالية | تعديل |
| POST | `/api/events/{id}/cancel` | مالك/Admin | إلغاء وإطلاق الحدث |
| POST | `/api/events/{id}/announcements` | مالك الفعالية | إدراج حملة على bulk (202) |
| POST | `/api/bookings` | Buyer | شراء سريع والدفع التجريبي فوري، `Idempotency-Key` اختياري |
| POST | `/api/holds` | Buyer | حجز مؤقت لمدة 10 دقائق مع Job مؤجل لتحريره |
| POST | `/api/holds/{id}/confirm` | Buyer | تأكيد الحجز قبل انتهاء المهلة، ودفع تجريبي ناجح |
| GET | `/api/bookings` | Buyer | حجوزات المستخدم |
| GET | `/api/bookings/{id}` | المالك/Admin | حجز وتذاكر |

مثال شراء:
```json
{"event_id":1,"items":[{"ticket_category_id":1,"quantity":2}]}
```
يرجع 201 بسرعة مع booking status `paid` (الدفع ممثل بنجاح فوري حسب التكليف)، ثم ترى `IssueTicketsJob` في Horizon queue critical، يليه البريد وSMS. للشراء المباشر يفترض الـ API نجاح الدفع فورًا حسب المهمة. لاختبار سلة مؤقتة استخدم `POST /api/holds` ثم `POST /api/holds/{id}/confirm` قبل 10 دقائق. إذا لم تؤكدها، يحررها Job مؤجل؛ انتهاء الحجز والتأكيد يتسابقان على قفل قاعدة البيانات، والحجز المدفوع لا يتحرر.

## محاكاة فشل المزود

في `.env` ضع `MAIL_FAIL=true` أو `SMS_FAIL=true`، ثم `php artisan config:clear`، اشترِ تذكرة وتابع retries في Horizon. بعد استنفاد المحاولات تحقق من `php artisan queue:failed`. أعد المزود إلى false، ثم أعد تشغيل سجل محدد عبر Horizon أو `php artisan queue:retry ID`. راجع `storage/logs/laravel.log` للسجل الدائم. رسائل البريد في وضع التطوير تكتب في `storage/logs` بواسطة mailer `log`.

## الإعلانات والإلغاء والحجز المؤقت

الإعلان يحفظ سجلًا ثم يرسل عبر `SendAnnouncementJob`، ويجلب أصحاب التذاكر باستخدام `chunkById(400)` دون تحميل القائمة كاملة، على queue `bulk`. إلغاء الفعالية يحدّث الحالة سريعًا ثم يطلق `EventCancelled`; الـ listener queued ويجزّئ الاسترداد في الخلفية، يعيد الكمية للمخزون، يجعل الاسترداد idempotent بحالة الحجز، ويطلق إشعار البريد وSMS لكل مشترٍ في Jobs مستقلة.

`BookingService::release()` يرسل `ExpireBookingJob` مؤجلًا إلى وقت `expires_at` بدل مسح دوري؛ الـ job يقفل صف الحجز ويتحقق من حالته وموعد انتهائه داخل transaction، فلا يحرر حجزًا مدفوعًا. تدفق الشراء الأساسي الحالي يثبت الدفع فورًا حسب نص المهمة، والـ reservation helper جاهز لتدفق دفع خارجي.

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
