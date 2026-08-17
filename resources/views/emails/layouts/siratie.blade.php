<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="ar" dir="ltr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>{{ $title ?? config('app.name', 'Siratie') }}</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td, a { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
    <style type="text/css">
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { text-decoration: none; }
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .content-pad { padding: 24px 18px !important; }
            .code-box { font-size: 28px !important; letter-spacing: 6px !important; }
            .stack-btn { width: 100% !important; display: block !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#FAF7F2; width:100%;">
    <div style="display:none; font-size:1px; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">
        {{ $preheader ?? '' }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FAF7F2; margin:0; padding:0; width:100%;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:16px; overflow:hidden; border:1px solid #D3E3DF;">

                    {{-- Brand header --}}
                    <tr>
                        <td align="center" style="background-color:#00A898; background:linear-gradient(135deg, #00A898 0%, #006A60 100%); padding:28px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="vertical-align:middle;">
                                        @php
                                            $logoPath = public_path('images/siratie-mail-logo.png');
                                            $logoUrl = null;
                                            if (is_file($logoPath) && isset($message)) {
                                                try {
                                                    $logoUrl = $message->embed($logoPath);
                                                } catch (\Throwable) {
                                                    $logoUrl = null;
                                                }
                                            }
                                        @endphp
                                        @if ($logoUrl)
                                            <img src="{{ $logoUrl }}" width="56" height="56" alt="{{ config('app.name', 'Siratie') }}" style="display:block; width:56px; height:56px; border-radius:14px; margin:0 auto 12px auto; border:2px solid rgba(255,255,255,0.35);" />
                                        @else
                                            <div style="width:56px; height:56px; line-height:56px; margin:0 auto 12px auto; border-radius:14px; background-color:rgba(255,255,255,0.18); color:#FFFFFF; font-family:Tahoma, Arial, sans-serif; font-size:24px; font-weight:800; border:2px solid rgba(255,255,255,0.35);">س</div>
                                        @endif
                                        <div style="font-family:Tahoma, 'Segoe UI', Arial, sans-serif; font-size:26px; font-weight:800; color:#FFFFFF; letter-spacing:0.4px; line-height:1.2;">
                                            {{ config('app.name', 'Siratie') }}
                                        </div>
                                        <div style="font-family:Tahoma, Cairo, 'Segoe UI', Arial, sans-serif; font-size:13px; color:#DDF6F3; margin-top:6px; direction:rtl;">
                                            سيرتي — مسارك المهني
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Accent bar --}}
                    <tr>
                        <td style="height:4px; line-height:4px; font-size:0; background-color:#59DAC9;">&nbsp;</td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td class="content-pad" style="padding:32px 36px 12px 36px; background-color:#FFFFFF;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:0 36px 28px 36px; background-color:#FFFFFF;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #E7E5DE;">
                                <tr>
                                    <td align="center" style="padding-top:22px; font-family:Tahoma, 'Segoe UI', Arial, sans-serif; font-size:12px; line-height:1.7; color:#6C7A77;">
                                        <strong style="color:#171D1B; font-size:13px;">{{ config('app.name', 'Siratie') }}</strong><br />
                                        @php
                                            $supportEmail = config('mail.from.address', 'info@siratie.com');
                                        @endphp
                                        <a href="mailto:{{ $supportEmail }}" style="color:#00A898; text-decoration:underline;">{{ $supportEmail }}</a>
                                        <br /><br />
                                        <span style="direction:rtl; display:inline-block; font-family:Tahoma, Cairo, Arial, sans-serif;">
                                            هذه رسالة آلية — يُرجى عدم الرد عليها مباشرة.
                                        </span>
                                        <br />
                                        <span style="display:inline-block; margin-top:4px;">
                                            This is an automated message — please do not reply directly.
                                        </span>
                                        <br /><br />
                                        <span style="color:#A1A1AA; font-size:11px;">
                                            © {{ date('Y') }} {{ config('app.name', 'Siratie') }}. All rights reserved.
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

                {{-- Outer note under card --}}
                <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">
                    <tr>
                        <td align="center" style="padding:16px 12px 0 12px; font-family:Tahoma, Arial, sans-serif; font-size:11px; color:#A1A1AA; line-height:1.5;">
                            {{ config('app.name', 'Siratie') }} transactional email
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
