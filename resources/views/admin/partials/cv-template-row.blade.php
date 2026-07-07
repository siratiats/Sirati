<tr data-admin-row="cv-template" data-row-id="{{ $template->id }}">
    <td>
        @if ($template->preview_image_path)
            <img src="{{ asset('storage/'.$template->preview_image_path) }}" alt="{{ $template->name_en }}" style="width: 76px; height: 96px; object-fit: cover; border-radius: 10px; border: 1px solid rgba(148,163,184,.3);">
        @else
            <span class="badge badge-neutral">No image</span>
        @endif
    </td>
    <td><strong>{{ $template->name_en }}</strong><br><span class="muted">{{ $template->name_ar }} - {{ $template->renderer_key }}</span></td>
    <td>{{ $template->slug }}</td>
    <td>
        <span class="badge {{ $template->is_active ? 'badge-success' : 'badge-neutral' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span>
        @if ($template->is_default)
            <span class="badge badge-info" data-default-template-badge>Default</span>
        @endif
    </td>
    <td>{{ implode(', ', $template->supported_languages ?: []) }}</td>
    <td>
        <div class="table-actions">
            <button class="button button-secondary" type="button" onclick="document.getElementById('cv-template-edit-{{ $template->id }}').showModal()">Edit</button>
            @unless ($template->is_default)
                <form method="POST" action="{{ route('admin.cv-templates.default', $template) }}">@csrf<button class="button button-secondary" type="submit">Set default</button></form>
            @endunless
            <form method="POST" action="{{ route('admin.cv-templates.destroy', $template) }}" onsubmit="return confirm('Archive this template?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Archive</button></form>
        </div>
    </td>
</tr>

