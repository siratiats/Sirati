@extends('admin.shell')

@section('admin_title', 'CV Analyses')
@section('admin_active', 'analyses')
@section('admin_eyebrow', 'ATS scoring')
@section('admin_heading', 'CV analyses')
@section('admin_description', 'Inspect recent CV analysis requests, scores, job matching percentages, and processing status.')

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('analyses.create') }}">Run analysis</a>
@endsection

@section('admin_content')
    <section class="card">
        <div class="admin-section-header">
            <div>
                <h2>Analysis records</h2>
                <p class="muted">{{ $analyses->total() }} analyses found.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Target job</th><th>Score</th><th>Match</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($analyses as $analysis)
                        <tr>
                            <td><a href="{{ route('analyses.show', $analysis) }}">#{{ $analysis->id }}</a></td>
                            <td>{{ $analysis->target_job_title }}</td>
                            <td>{{ $analysis->score_total }}/100 · {{ $analysis->grade }}</td>
                            <td>{{ $analysis->job_match }}%</td>
                            <td>{{ $analysis->ai_status === \App\Enums\AiStatus::Completed ? 'Completed' : ($analysis->ai_status === \App\Enums\AiStatus::Failed ? 'Failed' : 'Local fallback') }}</td>
                            <td>{{ $analysis->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No CV analyses have been created yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $analyses->links() }}</div>
    </section>
@endsection
