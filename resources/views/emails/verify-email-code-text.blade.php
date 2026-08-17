{{ config('app.name', 'Siratie') }} — تأكيد البريد / Verify email

مرحباً {{ $name ?? '' }}

استخدم رمز التحقق التالي لتأكيد بريدك الإلكتروني في تطبيق سيرتي:

{{ $code }}

الرمز صالح لمدة {{ $minutes }} دقيقة.

إذا لم تطلب هذا الرمز، يمكنك تجاهل هذه الرسالة.

---

Hello {{ $name ?? '' }},

Use this verification code to confirm your email in the Sirati app:

{{ $code }}

This code expires in {{ $minutes }} minutes.

If you did not request this code, you can ignore this email.

—
{{ config('app.name', 'Siratie') }}
{{ config('mail.from.address') }}
This is an automated message — please do not reply directly.
