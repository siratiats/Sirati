{{-- Table-based CTA button (Outlook-safe) --}}
@php
    $label = $label ?? 'Open App';
    $url = $url ?? config('app.url', '#');
    $bg = $bg ?? '#00A898';
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 6px 0;">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                <tr>
                    <td align="center" bgcolor="{{ $bg }}" style="background-color:{{ $bg }}; border-radius:10px; mso-padding-alt:14px 28px;">
                        <!--[if mso]>
                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:220px;" arcsize="20%" stroke="f" fillcolor="{{ $bg }}">
                            <w:anchorlock/>
                            <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:15px;font-weight:bold;">{{ $label }}</center>
                        </v:roundrect>
                        <![endif]-->
                        <!--[if !mso]><!-- -->
                        <a class="stack-btn" href="{{ $url }}" target="_blank" rel="noopener" style="display:inline-block; background-color:{{ $bg }}; color:#FFFFFF; font-family:Tahoma, 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:700; line-height:1.2; text-align:center; text-decoration:none; padding:14px 28px; border-radius:10px; mso-hide:all;">
                            {{ $label }}
                        </a>
                        <!--<![endif]-->
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
