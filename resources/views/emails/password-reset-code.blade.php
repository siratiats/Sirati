@php
    $appName = config('app.name', 'Siratie');
    $title = $appName.' — استعادة كلمة المرور / Password reset';
    $preheader = 'رمز الاستعادة: '.$code.' — Password reset code for '.$appName;
    $ctaUrl = $actionUrl ?? config('app.url', '#');
    $name = trim((string) ($name ?? ''));
@endphp

@component('emails.layouts.siratie', [
    'title' => $title,
    'preheader' => $preheader,
    'message' => $message ?? null,
])

    {{-- Arabic (RTL) --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="rtl" style="direction:rtl; text-align:right;">
        <tr>
            <td dir="rtl" style="direction:rtl; text-align:right; font-family:Tahoma, Cairo, 'Segoe UI', Arial, sans-serif; color:#171D1B;">
                <p style="margin:0 0 10px 0; font-size:20px; font-weight:800; color:#171D1B; text-align:right;">
                    مرحباً{{ $name !== '' ? ' '.$name : '' }} 👋
                </p>
                <p style="margin:0 0 8px 0; font-size:15px; line-height:1.75; color:#3C4947; text-align:right;">
                    استخدم رمز التحقق التالي لإعادة تعيين كلمة المرور في تطبيق سيرتي:
                </p>

                @include('emails.partials.otp-code-box', ['code' => $code])

                <p style="margin:0 0 16px 0; font-size:14px; line-height:1.7; color:#6C7A77; text-align:right;">
                    الرمز صالح لمدة <strong style="color:#006A60;">{{ $minutes }}</strong> دقيقة.
                </p>

                @include('emails.partials.cta-button', [
                    'url' => $ctaUrl,
                    'label' => 'إعادة تعيين كلمة المرور',
                ])

                <p style="margin:14px 0 0 0; font-size:13px; line-height:1.7; color:#6C7A77; text-align:right;">
                    إذا لم تطلب إعادة تعيين كلمة المرور، تجاهل هذه الرسالة.
                </p>
            </td>
        </tr>
    </table>

    {{-- Divider --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0;">
        <tr>
            <td style="border-top:1px solid #E7E5DE; height:1px; line-height:1px; font-size:0;">&nbsp;</td>
        </tr>
        <tr>
            <td align="center" style="padding-top:12px; font-family:Tahoma, Arial, sans-serif; font-size:11px; color:#A1A1AA; letter-spacing:1px; text-transform:uppercase;">
                English
            </td>
        </tr>
    </table>

    {{-- English (LTR) --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="ltr" style="direction:ltr; text-align:left;">
        <tr>
            <td dir="ltr" style="direction:ltr; text-align:left; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#171D1B;">
                <p style="margin:0 0 10px 0; font-size:20px; font-weight:800; color:#171D1B; text-align:left;">
                    Hello{{ $name !== '' ? ' '.$name : '' }},
                </p>
                <p style="margin:0 0 8px 0; font-size:15px; line-height:1.65; color:#3C4947; text-align:left;">
                    Use this code to reset your password in the Sirati app:
                </p>

                @include('emails.partials.otp-code-box', ['code' => $code])

                <p style="margin:0 0 16px 0; font-size:14px; line-height:1.65; color:#6C7A77; text-align:left;">
                    This code expires in <strong style="color:#006A60;">{{ $minutes }}</strong> minutes.
                </p>

                @include('emails.partials.cta-button', [
                    'url' => $ctaUrl,
                    'label' => 'Reset Password',
                ])

                <p style="margin:14px 0 8px 0; font-size:13px; line-height:1.65; color:#6C7A77; text-align:left;">
                    If you did not request a password reset, you can ignore this email.
                </p>
            </td>
        </tr>
    </table>

@endcomponent
