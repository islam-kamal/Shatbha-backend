<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>بيانات الدخول</title>
</head>
<body style="font-family: Tahoma, Arial, sans-serif; line-height: 1.6; color: #1c1814;">
    <h2>مرحباً {{ $party->name }}</h2>
    <p>تم إنشاء حسابك في تطبيق <strong>شطبها</strong> لمتابعة مشروعك واعتماد التصميم.</p>
    <p>
        <strong>البريد:</strong> {{ $account->email }}<br>
        <strong>كلمة المرور:</strong> {{ $plainPassword }}
    </p>
    <p>سجّل الدخول من التطبيق باستخدام هذه البيانات. يمكنك تغيير كلمة المرور لاحقاً.</p>
    <p style="color:#666;font-size:13px;">إذا لم تطلب هذا الحساب، تجاهل الرسالة.</p>
</body>
</html>
