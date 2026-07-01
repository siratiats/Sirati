@extends('admin.shell')

@section('admin_title', 'Education')
@section('admin_active', 'education')
@section('admin_eyebrow', 'Mobile content')
@section('admin_heading', 'Education content')
@section('admin_description', 'Create and review the learning cards, certificates, and news shown inside the Flutter app.')

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('admin.index') }}">Back to overview</a>
@endsection

@section('admin_content')
    <section class="card">
        <h2>Create education content</h2>
        <p class="muted">When no published content exists, the mobile app falls back to its built-in default content.</p>

        <form method="POST" action="{{ route('admin.education-contents.store') }}" style="margin-top: 16px;">
            @csrf
            <div class="grid grid-3">
                <label>Language
                    <select name="language" required>
                        <option value="ar">Arabic</option>
                        <option value="en">English</option>
                    </select>
                </label>
                <label>Type
                    <select name="type" required>
                        <option value="study">Study article</option>
                        <option value="certificate">Certificate</option>
                        <option value="news">News</option>
                    </select>
                </label>
                <label>Sort order
                    <input name="sort_order" type="number" value="0" min="0">
                </label>
            </div>
            <div class="grid grid-2">
                <label>Title<input name="title" required></label>
                <label>Target role<input name="target_role" placeholder="Data Analyst"></label>
                <label>Duration label<input name="duration_label" placeholder="15 min read"></label>
                <label>Badge<input name="badge" placeholder="Recommended"></label>
                <label>Button label<input name="button_label" placeholder="Start learning"></label>
                <label>Icon<input name="icon" placeholder="book or psychology"></label>
            </div>
            <label>Body<textarea name="body" required placeholder="Write the content summary or course description."></textarea></label>
            <label style="display: flex; gap: 8px; align-items: center;"><input type="checkbox" name="is_featured" value="1" style="width: auto;"> Featured content</label>
            <label style="display: flex; gap: 8px; align-items: center;"><input type="checkbox" name="is_published" value="1" checked style="width: auto;"> Published in app</label>
            <button class="button" type="submit">Save education content</button>
        </form>
    </section>

    <section class="card">
        <div class="admin-section-header">
            <div>
                <h2>Content library</h2>
                <p class="muted">{{ $educationContents->total() }} education items found.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Language</th><th>Type</th><th>Title</th><th>Published</th><th>Featured</th><th>Order</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($educationContents as $content)
                        <tr>
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
                    @empty
                        <tr><td colspan="7">No education content has been added yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $educationContents->links() }}</div>
    </section>
@endsection
