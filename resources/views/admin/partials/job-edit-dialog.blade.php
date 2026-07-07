<dialog class="admin-drawer" id="job-edit-{{ $item->id }}">
    <div class="drawer-body">
        <div class="drawer-header">
            <div>
                <h3>Edit job</h3>
                <p class="muted">{{ $item->title }}</p>
            </div>
            <button class="drawer-close" type="button" onclick="document.getElementById('job-edit-{{ $item->id }}').close()">×</button>
        </div>
        <form method="POST" action="{{ route('admin.job-news.update', $item) }}">
            @csrf
            @method('PATCH')
            @foreach (($jobFilters ?? []) as $key => $value)
                @if (filled($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            @if(isset($jobNewsItems))
                <input type="hidden" name="jobs_page" value="{{ $jobNewsItems->currentPage() }}">
            @endif
            <div class="grid grid-2">
                <label>Language<select name="language" required><option value="ar" @selected($item->language === 'ar')>Arabic</option><option value="en" @selected($item->language === 'en')>English</option></select></label>
                <label>Company<input name="company" value="{{ $item->company }}"></label>
                <label>Location<input name="location" value="{{ $item->location }}"></label>
                <label>Published at<input name="published_at" type="datetime-local" value="{{ $item->published_at?->format('Y-m-d\TH:i') }}"></label>
                <label>Valid from<input name="valid_from" type="date" value="{{ $item->valid_from?->format('Y-m-d') }}"></label>
                <label>Valid until<input name="valid_until" type="date" value="{{ $item->valid_until?->format('Y-m-d') }}"></label>
                <label>Sort order<input name="sort_order" type="number" value="{{ $item->sort_order }}" min="0"></label>
                <label>Source URL<input name="url" type="url" value="{{ $item->url }}"></label>
                <label>Apply URL<input name="apply_url" type="url" value="{{ $item->apply_url }}"></label>
            </div>
            <label>Title<input name="title" value="{{ $item->title }}" required></label>
            <label>Description<textarea name="body" required>{{ $item->body }}</textarea></label>
            <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_published" value="1" @checked($item->is_published)> Published in app</label>
            <div class="filter-actions"><button class="button" type="submit">Save changes</button><button class="button button-secondary" type="button" onclick="document.getElementById('job-edit-{{ $item->id }}').close()">Cancel</button></div>
        </form>
    </div>
</dialog>

