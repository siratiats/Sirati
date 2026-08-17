@props(['url'])
<tr>
<td class="header" style="background-color:#00A898; padding:28px 24px; text-align:center;">
<a href="{{ $url }}" style="display:inline-block; text-decoration:none;">
@php
    $logoPath = public_path('images/siratie-mail-logo.png');
@endphp
@if (is_file($logoPath) && isset($message))
<img src="{{ $message->embed($logoPath) }}" class="logo" alt="{{ config('app.name', 'Siratie') }}" style="height:56px; width:56px; border-radius:14px; margin:0 auto 10px auto; display:block;">
@endif
<span style="display:block; font-family:Tahoma, 'Segoe UI', Arial, sans-serif; font-size:22px; font-weight:800; color:#FFFFFF; letter-spacing:0.3px;">
{{ trim($slot) !== '' ? $slot : config('app.name', 'Siratie') }}
</span>
</a>
</td>
</tr>
