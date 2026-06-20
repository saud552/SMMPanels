# دراسة جدوى دمج Google OAuth (خطة العمل)

> **ملاحظة هامة:** هذه وثيقة تخطيطية (Planning Document) ودراسة جدوى معمارية. لم يتم إجراء أي تعديل فعلي على أي ملف برمجي في المستودع. تم إعداد التقرير في وضع القراءة فقط (READ-ONLY).

## 1. تحليل الوضع الحالي
تعتمد آلية المصادقة الحالية في المنصة على نظام الجلسات (PHP Sessions) الأصيل، حيث يتم التحقق من بيانات الدخول عن طريق استعلام مباشر من قاعدة البيانات ومطابقة كلمات المرور المشفرة باستخدام `password_verify` في ملف `index.php`.
عند النجاح، تُحفظ بيانات المستخدم (المعرف، الاسم، الإيميل، والرصيد) ضمن المتغير الشامل `$_SESSION`.

**التحديات المحتملة عند دمج Google Auth:**
- **غياب كلمة المرور:** مستخدمو جوجل لا يمتلكون كلمة مرور في النظام، مما يتطلب تجاوز عملية التحقق من كلمة المرور لهذه الفئة من المستخدمين، ووضع آلية لتمييزهم.
- **تضارب الحسابات:** إذا كان البريد الإلكتروني مسجلاً مسبقاً بالطريقة العادية، يجب أن يقرر النظام ما إذا كان سيدمج حساب جوجل مع الحساب الحالي أو سيرفض العملية.
- **نقص البيانات الإلزامية:** قد يتطلب التسجيل (في `register.php`) اسم مستخدم (`username`) مميز. واجهة جوجل قد لا توفر اسم مستخدم مطابق لشروط النظام، مما يتطلب توليد اسم مستخدم عشوائي أو طلبه من المستخدم في خطوة وسيطة.

## 2. قسم الملفات المتأثرة

| الملف المستهدف | نوع الملف | التعديل المقترح (نظرياً) |
|---|---|---|
| `config.php` | إعدادات | إضافة مفاتيح `GOOGLE_CLIENT_ID` و `GOOGLE_CLIENT_SECRET` و `REDIRECT_URI`. |
| `index.php` | واجهة أمامية ومسار | إضافة زر "تسجيل الدخول باستخدام جوجل". توجيه المستخدم لإنشاء رابط المصادقة. |
| `register.php` | واجهة أمامية | إضافة زر "التسجيل باستخدام جوجل". |
| `includes/google_auth.php` (جديد) | متحكم / منطق | ملف جديد لإدارة عملية الـ Callback، التحقق من التوكن، والتعامل مع حسابات المستخدمين. |
| `database.sql` / `installSQL.php` | نموذج وقاعدة بيانات | إضافة حقل `google_id` وربما حقل `avatar` لجدول `users`. |
| `composer.json` (جديد) | تبعيات | تثبيت مكتبة `google/apiclient`. |

## 3. التغييرات المطلوبة نظرياً (Pseudocode)

### أ. ملف `config.php` (الإعدادات)
```php
// Pseudocode
define('GOOGLE_CLIENT_ID', 'your_client_id_here');
define('GOOGLE_CLIENT_SECRET', 'your_client_secret_here');
define('GOOGLE_REDIRECT_URI', 'https://yourdomain.com/includes/google_auth.php');
```

### ب. ملف `index.php` (إضافة زر الدخول)
```html
<!-- Pseudocode -->
<a href="includes/google_auth.php?action=login" class="btn btn-google">
    <i class="fab fa-google"></i> Login with Google
</a>
```

### ج. ملف `includes/google_auth.php` (معالجة تدفق البيانات)
```php
// Pseudocode
require_once '../config.php';
require_once '../vendor/autoload.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token['access_token']);

    // الحصول على بيانات المستخدم من جوجل
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    $email =  $google_account_info->email;
    $name =  $google_account_info->name;
    $google_id = $google_account_info->id;

    // التحقق من وجود المستخدم في قاعدة البيانات
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR google_id = ?");
    $stmt->execute([$email, $google_id]);
    $user = $stmt->fetch();

    if ($user) {
        // تسجيل الدخول (إنشاء الـ Session)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        // عملية التوجيه
    } else {
        // إنشاء مستخدم جديد
        // تسجيل الدخول بعد الإنشاء
    }
} else {
    // التوجيه لجوجل
}
```

## 4. هندسة التدفق الجديد (New Flow Architecture)

1. **بدء العملية**: يضغط المستخدم على زر "Login with Google" في صفحة `index.php`.
2. **التوجيه (Redirect)**: يقوم النظام (عبر `google_auth.php`) بتوليد رابط OAuth وتوجيه المستخدم إلى خوادم Google مع إرفاق الـ Client ID ومسار العودة (Callback URL).
3. **المصادقة والموافقة**: يُدخل المستخدم بياناته في منصة Google ويمنح التطبيق صلاحيات قراءة الاسم والبريد الإلكتروني.
4. **مسار العودة (Callback)**: تعيد Google توجيه المستخدم إلى `google_auth.php` مع مُعامل (Authorization Code) في الـ URL (`?code=...`).
5. **تبادل الرمز (Token Exchange)**: يستخدم الخادم هذا الـ Code في اتصال خلفي (Server-to-Server) مع Google للحصول على Access Token.
6. **جلب البيانات**: باستخدام الـ Access Token، يجلب النظام بيانات المستخدم (الاسم، البريد، مُعرّف جوجل).
7. **المنطق الداخلي**:
   - هل المُعرّف أو البريد موجود في جدول `users`؟
     - **نعم**: قم بإنشاء جلسة ($_SESSION) وحدث بيانات تسجيل الدخول، ثم وجهه للوحة التحكم.
     - **لا**: أنشئ حساباً جديداً في قاعدة البيانات مع تعيين كلمة مرور عشوائية ومُعرّف جوجل، ثم قم بإنشاء الجلسة وتوجيهه للوحة التحكم.

## 5. التأثير على قاعدة البيانات

ستتطلب هذه الميزة إضافة حقول جديدة لاستيعاب بيانات الطرف الثالث، لضمان قدرة النظام على التمييز بين مستخدمي الطريقة التقليدية ومستخدمي جوجل.

**Migration (أمر تنفيذي مقترح لبيئة MySQL):**
```sql
-- إضافة عمود معرف جوجل (فريد)
ALTER TABLE `users` ADD `google_id` VARCHAR(255) NULL UNIQUE AFTER `email`;

-- إضافة عمود الصورة الرمزية (اختياري)
ALTER TABLE `users` ADD `avatar` VARCHAR(500) NULL AFTER `google_id`;
```

## 6. التحديات الأمنية والاعتماديات

### مصفوفة الاعتماديات:
- **المكتبة الخارجية**: `google/apiclient` (يتم تثبيتها عبر `composer require google/apiclient`).
- **التأثير**: بما أن المشروع حالياً يعمل بـ Native PHP دون استخدام إطار عمل كبير أو الـ Composer بشكل أساسي، سيتطلب ذلك إضافة مجلد `vendor` وتهيئة `autoload.php`، وهو ما يشكل تغييراً معمارياً طفيفاً لكنه أساسي في استيراد المكتبات.

### التحديات الأمنية والحلول:
1. **هجمات CSRF أثناء مسار العودة**:
   - *الخطر*: يمكن لمهاجم أن يمرر كود مصادقة خاص به إلى مستخدم آخر، مما يجعله يسجل الدخول بحساب المهاجم.
   - *الحل*: إرسال معلمة `state` عشوائية عند بدء عملية المصادقة وحفظها في الـ Session، ومقارنتها عند عودة المستخدم.
2. **تضارب البريد الإلكتروني (Account Takeover Risk)**:
   - *الخطر*: إذا قام شخص بتسجيل حساب عبر الإيميل بالطريقة العادية، ثم أتى صاحب الإيميل الحقيقي وقام بتسجيل الدخول عبر جوجل (حيث تقوم جوجل بتأكيد ملكية الإيميل)، قد يتم دمج الحسابين بشكل يتيح الدخول للبيانات القديمة.
   - *الحل*: يفضل منع عملية الدمج التلقائي إذا لم يكن الحساب الأصلي قد قام بتوثيق بريده الإلكتروني مسبقاً، أو فرض تسجيل دخول بكلمة المرور القديمة لمرة واحدة لربط الحساب.
3. **التحقق من صحة مصدر التوكن (Audience Validation)**:
   - *الخطر*: استخدام توكنات صادرة لتطبيقات أخرى.
   - *الحل*: تأكيد أن `client_id` المستلم من الـ Token يطابق `GOOGLE_CLIENT_ID` الخاص بالتطبيق.