# دليل اختبار نظام التتبع - Crowdshipping

## 🚀 خطوات التشغيل الأولية

### 1. تشغيل المشروع
```bash
# تشغيل الخادم
php artisan serve

# في terminal آخر - تشغيل queue worker
php artisan queue:work
```

### 2. تطبيق التحديثات على قاعدة البيانات
```bash
php artisan migrate
```

## 📱 اختبار النظام باستخدام Postman أو أي REST Client

### المرحلة 1: إنشاء المستخدمين

#### تسجيل مرسل
```http
POST http://localhost:8000/api/register
Content-Type: application/json

{
    "first_name": "أحمد",
    "last_name": "محمد",
    "email": "sender@test.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "phone_number": "01234567890",
    "date_of_birth": "1990-01-01",
    "gender": "male",
    "type": "sender"
}
```

#### تسجيل مسافر
```http
POST http://localhost:8000/api/register
Content-Type: application/json

{
    "first_name": "سارة",
    "last_name": "أحمد",
    "email": "traveler@test.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "phone_number": "01234567891",
    "date_of_birth": "1992-01-01",
    "gender": "female",
    "type": "traveler"
}
```

### المرحلة 2: إنشاء الشحنة والرحلة

#### المرسل ينشئ شحنة
```http
POST http://localhost:8000/api/shipments
Authorization: Bearer {sender_token}
Content-Type: application/json

{
    "from_country": "مصر",
    "from_city": "القاهرة",
    "to_country": "السعودية",
    "to_city": "الرياض",
    "weight": 2.5,
    "category": "electronics",
    "description": "لابتوب شخصي",
    "delivery_from_date": "2025-02-01",
    "delivery_to_date": "2025-02-15",
    "offered_price": 500,
    "special_instructions": "يرجى التعامل بحذر"
}
```

#### المسافر ينشئ رحلة
```http
POST http://localhost:8000/api/travel-requests
Authorization: Bearer {traveler_token}
Content-Type: application/json

{
    "from_country": "مصر",
    "to_country": "السعودية",
    "travel_date": "2025-02-05",
    "max_weight": 10
}
```

### المرحلة 3: ربط الشحنة بالرحلة

#### المسافر يقدم عرض على الشحنة
```http
POST http://localhost:8000/api/shipment-travel-requests
Authorization: Bearer {traveler_token}
Content-Type: application/json

{
    "shipment_id": 1,
    "travel_id": 1,
    "offered_price": 450
}
```

#### المرسل يقبل العرض
```http
POST http://localhost:8000/api/sender/request/{request_id}/status
Authorization: Bearer {sender_token}
Content-Type: application/json

{
    "status": "accepted"
}
```

## 🔄 اختبار مراحل التتبع

### المرحلة 1: تأكيد الدفع (المرسل)
```http
POST http://localhost:8000/api/tracking/confirm-payment/{shipment_travel_request_id}
Authorization: Bearer {sender_token}
Content-Type: application/json

{}
```

**النتيجة المتوقعة:**
- حالة الطلب: `paid`
- حالة الشحنة: `paid`
- إنشاء QR Code

### المرحلة 2: تأكيد الاستلام (المسافر)
```http
POST http://localhost:8000/api/tracking/confirm-pickup/{shipment_travel_request_id}
Authorization: Bearer {traveler_token}
Content-Type: application/json

{}
```

**النتيجة المتوقعة:**
- حالة الطلب: `picked_up`
- حالة الشحنة: `picked_up`

### المرحلة 3: عرض حالة الشحنة
```http
GET http://localhost:8000/api/tracking/status/{shipment_travel_request_id}
Authorization: Bearer {sender_token_or_traveler_token}
```

**النتيجة المتوقعة:**
- Timeline كامل بالمراحل
- QR Code
- الإجراء التالي المطلوب

### المرحلة 4: التحويل التلقائي (في تاريخ السفر)
```http
POST http://localhost:8000/api/auto-transit-shipments
Content-Type: application/json

{}
```

**أو تشغيل الـ Command:**
```bash
php artisan shipments:auto-transit
```

### المرحلة 5: تأكيد التسليم بـ QR Code (المسافر)
```http
POST http://localhost:8000/api/tracking/confirm-delivery
Authorization: Bearer {traveler_token}
Content-Type: application/json

{
    "qr_code": "SHIP_1_abc123xyz"
}
```

**النتيجة المتوقعة:**
- حالة الطلب: `delivered`
- حالة الشحنة: `delivered`

## 🧪 اختبارات إضافية

### اختبار الصلاحيات
- جرب الوصول بـ token خاطئ
- جرب تأكيد الدفع بحساب مسافر (يجب أن يرفض)
- جرب تأكيد الاستلام بحساب مرسل (يجب أن يرفض)

### اختبار التسلسل
- جرب تأكيد الاستلام قبل الدفع (يجب أن يرفض)
- جرب مسح QR Code قبل "في الطريق" (يجب أن يرفض)

### اختبار QR Code
- جرب QR Code خاطئ
- جرب QR Code لشحنة أخرى

## 📊 مراقبة النتائج

### في قاعدة البيانات
```sql
-- عرض حالة الشحنات
SELECT id, status, is_booked, qr_code FROM shipments;

-- عرض حالة الطلبات
SELECT id, status, accepted_at, paid_at, picked_up_at, delivered_at FROM shipment_travel_requests;
```

### في الـ Logs
```bash
tail -f storage/logs/laravel.log
```

## 🚨 مشاكل محتملة وحلولها

### مشكلة: Migration فشل
```bash
php artisan migrate:rollback
php artisan migrate
```

### مشكلة: Token منتهي الصلاحية
- سجل دخول مرة أخرى للحصول على token جديد

### مشكلة: QR Code لا يعمل
- تأكد من تأكيد الدفع أولاً
- تأكد من صحة الـ QR Code المُرسل

## 📝 ملاحظات مهمة

1. **احفظ الـ Tokens**: ستحتاجها في كل request
2. **احفظ الـ IDs**: shipment_id, travel_id, request_id
3. **تابع الـ Timeline**: لمعرفة الخطوة التالية
4. **اختبر التواريخ**: تأكد من أن تاريخ السفر في المستقبل

## 🎯 نتائج الاختبار المتوقعة

عند اكتمال الاختبار بنجاح:
- ✅ الشحنة تمر بكل المراحل بالترتيب الصحيح
- ✅ QR Code يعمل بشكل صحيح
- ✅ الصلاحيات محمية
- ✅ Timeline يعرض المراحل بوضوح
- ✅ التحديث التلقائي يعمل في تاريخ السفر