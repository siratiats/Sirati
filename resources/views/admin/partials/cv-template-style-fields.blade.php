@php
    $tpl = $template ?? null;
    $config = $tpl?->config_json ?: [];
    $style = array_merge(config('cv_templates.style_defaults'), (array) ($config['style'] ?? []));
    $sectionKeys = config('cv_templates.section_order');
    $order = implode(', ', ($config['section_order'] ?? []) ?: $sectionKeys);
    $hidden = (array) ($config['hidden_sections'] ?? []);
    $titles = (array) ($config['section_titles'] ?? []);
    $defaultEn = config('cv_templates.section_titles_en');
@endphp
<input type="hidden" name="style_form" value="1">
<h4 style="margin: 18px 0 8px;">CV layout style</h4>
<p class="muted" style="margin-top: 0;">Primary color = name, section titles and header rule. Accent color = job titles and skill categories.</p>
<div class="grid grid-3">
    <label>Body text color<input name="style[text_color]" value="{{ $style['text_color'] }}" pattern="^#[0-9a-fA-F]{6}$"></label>
    <label>Muted text color (descriptions)<input name="style[muted_color]" value="{{ $style['muted_color'] }}" pattern="^#[0-9a-fA-F]{6}$"></label>
    <label>Section divider color<input name="style[divider_color]" value="{{ $style['divider_color'] }}" pattern="^#[0-9a-fA-F]{6}$"></label>
    <label>Body font size (pt)<input name="style[font_size]" type="number" step="0.5" min="7" max="14" value="{{ $style['font_size'] }}"></label>
    <label>Name font size (pt)<input name="style[name_size]" type="number" step="0.5" min="12" max="36" value="{{ $style['name_size'] }}"></label>
    <label>Section title size (pt)<input name="style[section_title_size]" type="number" step="0.5" min="8" max="18" value="{{ $style['section_title_size'] }}"></label>
</div>
<div class="filter-actions">
    <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="style[uppercase_name]" value="1" @checked($style['uppercase_name'])> Uppercase name (English)</label>
    <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="style[uppercase_section_titles]" value="1" @checked($style['uppercase_section_titles'])> Uppercase section titles (English)</label>
</div>
<label>Section order (comma separated: {{ implode(', ', $sectionKeys) }})<input name="section_order" value="{{ $order }}"></label>
<table style="margin-top: 10px;">
    <thead><tr><th>Section</th><th>English title</th><th>Arabic title</th><th>Hide</th></tr></thead>
    <tbody>
        @foreach ($sectionKeys as $key)
            <tr>
                <td>{{ $key }}</td>
                <td><input name="section_titles[{{ $key }}][en]" value="{{ $titles[$key]['en'] ?? '' }}" placeholder="{{ $defaultEn[$key] ?? '' }}"></td>
                <td><input name="section_titles[{{ $key }}][ar]" value="{{ $titles[$key]['ar'] ?? '' }}" dir="rtl"></td>
                <td><input class="table-check" type="checkbox" name="hidden_sections[]" value="{{ $key }}" @checked(in_array($key, $hidden, true))></td>
            </tr>
        @endforeach
    </tbody>
</table>
