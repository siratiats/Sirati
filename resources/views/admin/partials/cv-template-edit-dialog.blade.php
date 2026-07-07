<dialog class="admin-drawer" id="cv-template-edit-{{ $template->id }}">
    <div class="drawer-body">
        <div class="drawer-header">
            <div>
                <h3>Edit CV template</h3>
                <p class="muted">{{ $template->name_en }} - {{ $template->slug }}</p>
            </div>
            <button class="drawer-close" type="button" onclick="document.getElementById('cv-template-edit-{{ $template->id }}').close()">Close</button>
        </div>
        <form method="POST" action="{{ route('admin.cv-templates.update', $template) }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            <div class="grid grid-2">
                <label>Arabic name<input name="name_ar" value="{{ $template->name_ar }}" required></label>
                <label>English name<input name="name_en" value="{{ $template->name_en }}" required></label>
                <label>Slug<input name="slug" value="{{ $template->slug }}" required></label>
                <label>Renderer key<select name="renderer_key" required>@foreach ($cvTemplateRenderers as $renderer)<option value="{{ $renderer }}" @selected($template->renderer_key === $renderer)>{{ $renderer }}</option>@endforeach</select></label>
                <label>Language direction<select name="language_direction" required><option value="rtl" @selected($template->language_direction === 'rtl')>RTL</option><option value="ltr" @selected($template->language_direction === 'ltr')>LTR</option><option value="both" @selected($template->language_direction === 'both')>RTL + LTR</option></select></label>
                <label>Sort order<input name="sort_order" type="number" value="{{ $template->sort_order }}" min="0"></label>
                <label>Primary color<input name="primary_color" value="{{ $template->color_tokens['primary'] ?? '#1f2937' }}" pattern="^#[0-9a-fA-F]{6}$"></label>
                <label>Accent color<input name="accent_color" value="{{ $template->color_tokens['accent'] ?? '#2563eb' }}" pattern="^#[0-9a-fA-F]{6}$"></label>
                <label>Preview image<input type="file" name="preview_image" accept="image/png,image/jpeg,image/webp"></label>
            </div>
            <div class="filter-actions">
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_languages[]" value="ar" checked> Arabic</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_languages[]" value="en" checked> English</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="summary" checked> Summary</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="skills" checked> Skills</label>
            </div>
            <div class="filter-actions">
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_active" value="1" @checked($template->is_active)> Active in app</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_default" value="1" @checked($template->is_default)> Default template</label>
            </div>
            <div class="filter-actions"><button class="button" type="submit">Save changes</button><button class="button button-secondary" type="button" onclick="document.getElementById('cv-template-edit-{{ $template->id }}').close()">Cancel</button></div>
        </form>
    </div>
</dialog>

