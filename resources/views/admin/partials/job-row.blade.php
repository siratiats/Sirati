@php
    $today = today();
    $status = 'active';
    $statusClass = 'badge-success';
    if (! $item->is_published) {
        $status = 'draft';
        $statusClass = 'badge-neutral';
    } elseif ($item->valid_from && $item->valid_from->isAfter($today)) {
        $status = 'future';
        $statusClass = 'badge-warning';
    } elseif ($item->valid_until && $item->valid_until->isBefore($today)) {
        $status = 'expired';
        $statusClass = 'badge-danger';
    }
    $jobStatusLabels = $jobStatusLabels ?? ['active' => 'Active', 'expired' => 'Expired', 'future' => 'Future', 'draft' => 'Draft'];
    $jobSourceLabels = $jobSourceLabels ?? ['manual' => 'Manual', 'excel_upload' => 'Excel', 'google_sheet' => 'Google Sheets'];
@endphp
<tr data-admin-row="job-news" data-row-id="{{ $item->id }}">
    <td><input class="table-check job-row-check" type="checkbox" name="ids[]" value="{{ $item->id }}" form="jobs-bulk-form"></td>
    <td>{{ strtoupper($item->language) }}</td>
    <td><strong>{{ $item->title }}</strong><br><span class="muted">{{ $item->source_row_key ?: 'No key' }}</span></td>
    <td>{{ $item->company ?: '-' }}</td>
    <td>{{ $item->location ?: '-' }}</td>
    <td><span class="badge {{ $statusClass }}">{{ $jobStatusLabels[$status] }}</span><br><span class="muted">Until: {{ $item->valid_until?->format('Y-m-d') ?: '-' }}</span></td>
    <td><span class="badge badge-neutral">{{ $jobSourceLabels[$item->source] ?? $item->source ?? 'manual' }}</span></td>
    <td>{{ $item->is_published ? 'Yes' : 'No' }}</td>
    <td><div class="table-actions"><button class="button button-secondary" type="button" onclick="document.getElementById('job-edit-{{ $item->id }}').showModal()">Edit</button><form method="POST" action="{{ route('admin.job-news.destroy', $item) }}" onsubmit="return confirm('Delete this job?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td>
</tr>

