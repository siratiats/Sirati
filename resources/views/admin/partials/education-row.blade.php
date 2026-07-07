<tr data-admin-row="education-content" data-row-id="{{ $content->id }}">
    <td>{{ strtoupper($content->language) }}</td>
    <td>{{ $content->type }}</td>
    <td>{{ $content->title }}</td>
    <td>{{ $content->is_published ? 'Yes' : 'No' }}</td>
    <td>{{ $content->is_featured ? 'Yes' : 'No' }}</td>
    <td>{{ $content->sort_order }}</td>
    <td>
        <form method="POST" action="{{ route('admin.education-contents.destroy', $content) }}" onsubmit="return confirm('Delete this content item?')">
            @csrf
            @method('DELETE')
            <button class="button button-secondary" type="submit">Delete</button>
        </form>
    </td>
</tr>

