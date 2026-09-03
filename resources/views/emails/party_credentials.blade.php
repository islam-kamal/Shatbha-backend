<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>بيانات الدخول</title>
</head>
<body style="font-family: Tahoma, Arial, sans-serif; line-height: 1.6; color: #1c1814;">
    <h2>مرحباً {{ $party->name }}</h2>
    <p>تم إنشاء حساب {{ $roleLabel }} في تطبيق <strong>شطبها</strong>.</p>
    <p>
        <strong>البريد:</strong> {{ $email }}<br>
        <strong>كلمة المرور:</strong> {{ $plainPassword }}
    </p>
    <p>سجّل الدخول من التطبيق بهذه البيانات.</p>
</body>
</html>
