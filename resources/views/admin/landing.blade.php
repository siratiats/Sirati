@extends('admin.shell')

@section('admin_title', 'Landing Content')
@section('admin_active', 'landing')
@section('admin_eyebrow', 'Website content')
@section('admin_heading', 'Landing Content')
@section('admin_description', 'Edit every label and the logo shown on the public landing page. Arabic is shown by default; English appears when visitors switch language.')

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('landing') }}" target="_blank" rel="noopener">Preview site</a>
    <button class="button" type="submit" form="landing-content-form">Save all changes</button>
@endsection

@section('admin_content')
    @php
        $groupTitles = [
            'branding' => 'Branding & SEO',
            'nav' => 'Top navigation',
            'drawer' => 'Mobile menu',
            'hero' => 'Hero section',
            'proof' => 'Highlights band',
            'about' => 'About section',
            'workflow' => 'How it works',
            'services' => 'Services cards',
            'social' => 'Follow / social',
            'download' => 'Download section',
            'footer' => 'Footer & sticky bar',
        ];
    @endphp

    @if ($errors->any())
        <section class="card" style="border-color: rgba(239,68,68,.5);">
            <h3 style="margin:0 0 8px;">Please fix the following</h3>
            <ul style="margin:0; padding-left:18px; color:#b91c1c;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <form id="landing-content-form" method="POST" action="{{ route('admin.landing.update') }}" enctype="multipart/form-data" data-loading-form>
        @csrf

        @foreach ($settingGroups as $group => $settings)
            <section class="card">
                <div class="admin-section-header">
                    <div>
                        <h2>{{ $groupTitles[$group] ?? ucfirst($group) }}</h2>
                        <p class="muted">{{ $settings->count() }} field(s)</p>
                    </div>
                </div>

                <div class="grid">
                    @foreach ($settings as $setting)
                        <div style="border:1px solid #e2e8f0; border-radius:14px; padding:14px; background:#f8fafc;">
                            <p style="margin:0 0 10px; font-weight:800; color:#0f172a;">{{ $setting->label }}</p>

                            @if ($setting->type === 'image')
                                @if ($setting->value)
                                    <div style="margin-bottom:10px;">
                                        <img src="{{ asset('storage/'.$setting->value) }}" alt="{{ $setting->label }}"
                                             style="max-height:80px; max-width:200px; border:1px solid #cbd5e1; border-radius:12px; background:#fff; padding:6px;">
                                    </div>
                                    <label style="flex-direction:row; align-items:center; gap:8px; font-weight:700;">
                                        <input type="checkbox" name="clear_logos[{{ $setting->id }}]" value="1" style="width:auto;">
                                        Remove current image
                                    </label>
                                @else
                                    <p class="muted" style="margin:0 0 10px;">No image uploaded — the letter mark is shown instead.</p>
                                @endif
                                <label>
                                    Upload new image (PNG, SVG, WebP, JPG — max 2&nbsp;MB)
                                    <input type="file" name="logos[{{ $setting->id }}]" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                </label>

                            @elseif (in_array($setting->type, ['text', 'textarea'], true))
                                <div class="grid grid-2">
                                    <label lang="ar">
                                        Arabic
                                        @if ($setting->type === 'textarea')
                                            <textarea name="settings[{{ $setting->id }}][value_ar]" dir="rtl" style="min-height:90px;">{{ old("settings.{$setting->id}.value_ar", $setting->value_ar) }}</textarea>
                                        @else
                                            <input type="text" name="settings[{{ $setting->id }}][value_ar]" dir="rtl" value="{{ old("settings.{$setting->id}.value_ar", $setting->value_ar) }}">
                                        @endif
                                    </label>
                                    <label>
                                        English
                                        @if ($setting->type === 'textarea')
                                            <textarea name="settings[{{ $setting->id }}][value_en]" dir="ltr" style="min-height:90px;">{{ old("settings.{$setting->id}.value_en", $setting->value_en) }}</textarea>
                                        @else
                                            <input type="text" name="settings[{{ $setting->id }}][value_en]" dir="ltr" value="{{ old("settings.{$setting->id}.value_en", $setting->value_en) }}">
                                        @endif
                                    </label>
                                </div>

                            @else
                                {{-- plain, url --}}
                                <label>
                                    Value
                                    <input type="{{ $setting->type === 'url' ? 'text' : 'text' }}" name="settings[{{ $setting->id }}][value]" value="{{ old("settings.{$setting->id}.value", $setting->value) }}">
                                </label>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div style="position:sticky; bottom:0; padding:14px 0;">
            <button class="button" type="submit" data-loading-button>
                <span data-loading-label>Save all changes</span>
            </button>
        </div>
    </form>
@endsection
