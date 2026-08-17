{{ config('app.name', 'Siratie') }} — استعادة كلمة المرور / Password reset

مرحباً {{ $name ?? '' }}

استخدم رمز التحقق التالي لإعادة تعيين كلمة المرور في تطبيق سيرتي:

{{ $code }}

الرمز صالح لمدة {{ $minutes }} دقيقة.

إذا لم تطلب إعادة تعيين كلمة المرور، تجاهل هذه الرسالة.

---

Hello {{ $name ?? '' }},

Use this code to reset your password in the Sirati app:

{{ $code }}

This code expires in {{ $minutes }} minutes.

If you did not request a password reset, you can ignore this email.

—
{{ config('app.name', 'Siratie') }}
{{ config('mail.from.address') }}
This is an automated message — please do not reply directly.
