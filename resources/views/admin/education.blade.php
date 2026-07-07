@extends('admin.shell')

@section('admin_title', 'Education')
@section('admin_active', 'education')
@section('admin_eyebrow', 'Mobile content')
@section('admin_heading', 'Education content')
@section('admin_description', 'Create and review the learning cards, certificates, and news shown inside the Flutter app.')

@section('admin_actions')
    <button class="button" type="button" onclick="document.getElementById('education-create-dialog').showModal()">Create content</button>
    <a class="button button-secondary" href="{{ route('admin.index') }}">Back to overview</a>
@endsection

@section('admin_content')
    <dialog class="admin-drawer" id="education-create-dialog">
        <div class="drawer-body">
            <div class="drawer-header">
                <div><h3>Create education content</h3><p class="muted">When no published content exists, the mobile app falls back to its built-in default content.</p></div>
                <button class="drawer-close" type="button" onclick="document.getElementById('education-create-dialog').close()">Close</button>
            </div>
            <form method="POST" action="{{ route('admin.education-contents.store') }}" data-ajax-form data-ajax-target="#education-content-tbody" data-ajax-empty-target="#education-empty" data-ajax-prepend="true">
                @csrf
                <div class="grid grid-3">
                    <label>Language<select name="language" required><option value="ar">Arabic</option><option value="en">English</option></select></label>
                    <label>Type<select name="type" required><option value="study">Study article</option><option value="certificate">Certificate</option><option value="news">News</option></select></label>
                    <label>Sort order<input name="sort_order" type="number" value="0" min="0"></label>
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
                <div class="filter-actions"><button class="button" type="submit" data-loading-button><span data-loading-label>Save education content</span></button><button class="button button-secondary" type="button" onclick="document.getElementById('education-create-dialog').close()">Cancel</button></div>
            </form>
        </div>
    </dialog>

    <section class="card">
        <div class="admin-section-header">
            <div>
                <h2>Content library</h2>
                <p class="muted">{{ $educationContents->total() }} education items found.</p>
            </div>
        </div>
        <div class="table-wrap">
        <form method="GET" action="{{ route('admin.education.index') }}" class="filter-panel">
            <div class="filter-row">
                <label>Search<input name="education_q" value="{{ $educationFilters['education_q'] ?? '' }}" placeholder="Title, body, role, or badge"></label>
                <label>Language<select name="education_language"><option value="">All languages</option><option value="ar" @selected(($educationFilters['education_language'] ?? '') === 'ar')>Arabic</option><option value="en" @selected(($educationFilters['education_language'] ?? '') === 'en')>English</option></select></label>
                <label>Type<select name="education_type"><option value="">All types</option><option value="study" @selected(($educationFilters['education_type'] ?? '') === 'study')>Study article</option><option value="certificate" @selected(($educationFilters['education_type'] ?? '') === 'certificate')>Certificate</option><option value="news" @selected(($educationFilters['education_type'] ?? '') === 'news')>News</option></select></label>
                <label>Published<select name="education_published"><option value="">Any</option><option value="1" @selected(($educationFilters['education_published'] ?? '') === '1')>Published</option><option value="0" @selected(($educationFilters['education_published'] ?? '') === '0')>Draft</option></select></label>
                <label>Featured<select name="education_featured"><option value="">Any</option><option value="1" @selected(($educationFilters['education_featured'] ?? '') === '1')>Featured</option><option value="0" @selected(($educationFilters['education_featured'] ?? '') === '0')>Not featured</option></select></label>
            </div>
            <div class="filter-actions"><button class="button" type="submit">Apply filters</button><a class="button button-secondary" href="{{ route('admin.education.index') }}">Clear</a></div>
        </form>
            <table>
                <thead><tr><th>Language</th><th>Type</th><th>Title</th><th>Published</th><th>Featured</th><th>Order</th><th>Action</th></tr></thead>
                <tbody id="education-content-tbody">
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
                        <tr id="education-empty"><td colspan="7"><div class="empty-state"><div class="empty-state-icon">+</div><h3>No education content yet</h3><p class="muted">Create the first learning card, certificate, or news item for the mobile app.</p><button class="button" type="button" onclick="document.getElementById('education-create-dialog').showModal()">Create content</button></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $educationContents->links() }}</div>
    </section>
@endsection

