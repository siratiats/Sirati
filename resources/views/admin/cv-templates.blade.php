@extends('admin.shell')

@section('admin_title', 'CV Templates')
@section('admin_active', 'templates')
@section('admin_eyebrow', 'Design management')
@section('admin_heading', 'CV templates')
@section('admin_description', 'Manage the trusted template records that mobile users can choose when exporting a generated CV.')

@section('admin_actions')
    <button class="button" type="button" onclick="document.getElementById('cv-template-create-dialog').showModal()">Create template</button>
    <a class="button button-secondary" href="{{ route('admin.index') }}">Back to overview</a>
@endsection

@section('admin_content')
    <dialog class="admin-drawer" id="cv-template-create-dialog">
        <div class="drawer-body">
        <div class="admin-section-header">
            <div>
                <h2>Create template</h2>
                <p class="muted">The Blade renderers are trusted server templates. This page controls metadata, preview image, language support, and availability.</p>
            </div>
            <button class="drawer-close" type="button" onclick="document.getElementById('cv-template-create-dialog').close()">Close</button>
        </div>

        <form method="POST" action="{{ route('admin.cv-templates.store') }}" enctype="multipart/form-data" data-ajax-form data-ajax-target="#cv-template-tbody" data-ajax-dialog-target="#cv-template-dialogs" data-ajax-empty-target="#cv-template-empty" data-ajax-prepend="true" style="margin-top: 16px;">
            @csrf
            <div class="grid grid-3">
                <label>Arabic name<input name="name_ar" required placeholder="Classic"></label>
                <label>English name<input name="name_en" required placeholder="Classic"></label>
                <label>Slug<input name="slug" placeholder="classic-rtl"></label>
                <label>Renderer key
                    <select name="renderer_key" required>
                        @foreach ($cvTemplateRenderers as $renderer)
                            <option value="{{ $renderer }}">{{ $renderer }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Language direction
                    <select name="language_direction" required>
                        <option value="rtl">RTL</option>
                        <option value="ltr">LTR</option>
                        <option value="both">RTL + LTR</option>
                    </select>
                </label>
                <label>Sort order<input name="sort_order" type="number" value="0" min="0"></label>
                <label>Primary color<input name="primary_color" value="#1f2937" pattern="^#[0-9a-fA-F]{6}$"></label>
                <label>Accent color<input name="accent_color" value="#2563eb" pattern="^#[0-9a-fA-F]{6}$"></label>
                <label>Preview image<input type="file" name="preview_image" accept="image/png,image/jpeg,image/webp"></label>
            </div>
            <div class="filter-actions">
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_languages[]" value="ar" checked> Arabic</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_languages[]" value="en" checked> English</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="summary" checked> Summary</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="skills" checked> Skills</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="experience" checked> Experience</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="education" checked> Education</label>
            </div>
            <div class="filter-actions">
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_active" value="1" checked> Active in app</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_default" value="1"> Default template</label>
            </div>
            <button class="button" type="submit">Save template</button>
        </form>
        </div>
    </dialog>

    <section class="card">
        <div class="admin-section-header">
            <div>
                <h2>Template library</h2>
                <p class="muted">{{ $cvTemplates->total() }} templates found.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.cv-templates.index') }}" class="filter-panel">
            <div class="filter-row">
                <label>Search<input name="template_q" value="{{ $cvTemplateFilters['template_q'] ?? '' }}" placeholder="Name, slug, or renderer"></label>
                <label>Status<select name="template_status"><option value="">All statuses</option><option value="active" @selected(($cvTemplateFilters['template_status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($cvTemplateFilters['template_status'] ?? '') === 'inactive')>Inactive</option><option value="default" @selected(($cvTemplateFilters['template_status'] ?? '') === 'default')>Default</option></select></label>
                <label>Direction<select name="template_direction"><option value="">All directions</option><option value="rtl" @selected(($cvTemplateFilters['template_direction'] ?? '') === 'rtl')>RTL</option><option value="ltr" @selected(($cvTemplateFilters['template_direction'] ?? '') === 'ltr')>LTR</option><option value="both" @selected(($cvTemplateFilters['template_direction'] ?? '') === 'both')>RTL + LTR</option></select></label>
                <label>Renderer<select name="template_renderer"><option value="">All renderers</option>@foreach ($cvTemplateRenderers as $renderer)<option value="{{ $renderer }}" @selected(($cvTemplateFilters['template_renderer'] ?? '') === $renderer)>{{ $renderer }}</option>@endforeach</select></label>
            </div>
            <div class="filter-actions"><button class="button" type="submit">Apply filters</button><a class="button button-secondary" href="{{ route('admin.cv-templates.index') }}">Clear</a></div>
        </form>
        @if ($cvTemplates->count() > 0)
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Preview</th><th>Template</th><th>Slug</th><th>Status</th><th>Languages</th><th>Actions</th></tr></thead>
                    <tbody id="cv-template-tbody">
                        @foreach ($cvTemplates as $template)
                            <tr>
                                <td>
                                    @if ($template->preview_image_path)
                                        <img src="{{ asset('storage/'.$template->preview_image_path) }}" alt="{{ $template->name_en }}" style="width: 76px; height: 96px; object-fit: cover; border-radius: 10px; border: 1px solid rgba(148,163,184,.3);">
                                    @else
                                        <span class="badge badge-neutral">No image</span>
                                    @endif
                                </td>
                                <td><strong>{{ $template->name_en }}</strong><br><span class="muted">{{ $template->name_ar }} · {{ $template->renderer_key }}</span></td>
                                <td>{{ $template->slug }}</td>
                                <td>
                                    <span class="badge {{ $template->is_active ? 'badge-success' : 'badge-neutral' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span>
                                    @if ($template->is_default)
                                        <span class="badge badge-info">Default</span>
                                    @endif
                                </td>
                                <td>{{ implode(', ', $template->supported_languages ?: []) }}</td>
                                <td>
                                    <div class="table-actions">
                                        <button class="button button-secondary" type="button" onclick="document.getElementById('cv-template-edit-{{ $template->id }}').showModal()">Edit</button>
                                        @unless ($template->is_default)
                                            <form method="POST" action="{{ route('admin.cv-templates.default', $template) }}">
                                                @csrf
                                                <button class="button button-secondary" type="submit">Set default</button>
                                            </form>
                                        @endunless
                                        <form method="POST" action="{{ route('admin.cv-templates.destroy', $template) }}" onsubmit="return confirm('Archive this template?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-danger" type="submit">Archive</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div id="cv-template-dialogs">
            @foreach ($cvTemplates as $template)
                <dialog class="admin-drawer" id="cv-template-edit-{{ $template->id }}">
                    <div class="drawer-body">
                        <div class="drawer-header">
                            <div>
                                <h3>Edit CV template</h3>
                                <p class="muted">{{ $template->name_en }} · {{ $template->slug }}</p>
                            </div>
                            <button class="drawer-close" type="button" onclick="document.getElementById('cv-template-edit-{{ $template->id }}').close()">×</button>
                        </div>
                        <form method="POST" action="{{ route('admin.cv-templates.update', $template) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')
                            <div class="grid grid-2">
                                <label>Arabic name<input name="name_ar" value="{{ $template->name_ar }}" required></label>
                                <label>English name<input name="name_en" value="{{ $template->name_en }}" required></label>
                                <label>Slug<input name="slug" value="{{ $template->slug }}" required></label>
                                <label>Renderer key
                                    <select name="renderer_key" required>
                                        @foreach ($cvTemplateRenderers as $renderer)
                                            <option value="{{ $renderer }}" @selected($template->renderer_key === $renderer)>{{ $renderer }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Language direction
                                    <select name="language_direction" required>
                                        <option value="rtl" @selected($template->language_direction === 'rtl')>RTL</option>
                                        <option value="ltr" @selected($template->language_direction === 'ltr')>LTR</option>
                                        <option value="both" @selected($template->language_direction === 'both')>RTL + LTR</option>
                                    </select>
                                </label>
                                <label>Sort order<input name="sort_order" type="number" value="{{ $template->sort_order }}" min="0"></label>
                                <label>Primary color<input name="primary_color" value="{{ $template->color_tokens['primary'] ?? '#1f2937' }}" pattern="^#[0-9a-fA-F]{6}$"></label>
                                <label>Accent color<input name="accent_color" value="{{ $template->color_tokens['accent'] ?? '#2563eb' }}" pattern="^#[0-9a-fA-F]{6}$"></label>
                                <label>Preview image<input type="file" name="preview_image" accept="image/png,image/jpeg,image/webp"></label>
                            </div>
                            <div class="filter-actions">
                                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_languages[]" value="ar" @checked(in_array('ar', $template->supported_languages ?: [], true))> Arabic</label>
                                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_languages[]" value="en" @checked(in_array('en', $template->supported_languages ?: [], true))> English</label>
                                @foreach (['summary' => 'Summary', 'skills' => 'Skills', 'experience' => 'Experience', 'education' => 'Education'] as $section => $label)
                                    <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="{{ $section }}" @checked(in_array($section, $template->supported_sections ?: [], true))> {{ $label }}</label>
                                @endforeach
                            </div>
                            <div class="filter-actions">
                                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_active" value="1" @checked($template->is_active)> Active in app</label>
                                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_default" value="1" @checked($template->is_default)> Default template</label>
                            </div>
                            <div class="filter-actions">
                                <button class="button" type="submit">Save changes</button>
                                <button class="button button-secondary" type="button" onclick="document.getElementById('cv-template-edit-{{ $template->id }}').close()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </dialog>
            @endforeach
            </div>
            <div class="pagination-wrap">{{ $cvTemplates->links() }}</div>
        @else
            <div class="empty-state" id="cv-template-empty">
                <div class="empty-state-icon">+</div>
                <h3>No CV templates yet</h3>
                <p class="muted">Add the first template so users can choose a CV design when exporting.</p>
                <button class="button" type="button" onclick="document.getElementById('cv-template-create-dialog').showModal()">Create template</button>
            </div>
        @endif
    </section>
@endsection
