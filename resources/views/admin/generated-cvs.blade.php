@extends('admin.shell')

@section('admin_title', 'Generated CVs')
@section('admin_active', 'generated-cvs')
@section('admin_eyebrow', 'CV exports')
@section('admin_heading', 'Generated CVs')
@section('admin_description', 'Review generated CV records, their scores, generation status, and PDF exports.')

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('generated-cvs.create') }}">Create CV</a>
@endsection

@section('admin_content')
    <section class="card">
        <div class="admin-section-header">
            <div>
                <h2>Generated CV records</h2>
                <p class="muted">{{ $generatedCvs->total() }} generated CVs found.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Target job</th><th>Score</th><th>Status</th><th>PDF</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($generatedCvs as $cv)
                        <tr>
                            <td><a href="{{ route('generated-cvs.show', $cv) }}">#{{ $cv->id }}</a></td>
                            <td>{{ $cv->full_name }}</td>
                            <td>{{ $cv->target_job_title }}</td>
                            <td>{{ $cv->score_total }}/100 · {{ $cv->grade }}</td>
                            <td>{{ $cv->ai_status === 'completed' ? 'Completed' : ($cv->ai_status === 'failed' ? 'Failed' : 'Local fallback') }}</td>
                            <td><a href="{{ route('generated-cvs.pdf', $cv) }}">Download</a></td>
                            <td>{{ $cv->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No generated CVs have been created yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $generatedCvs->links() }}</div>
    </section>
@endsection
