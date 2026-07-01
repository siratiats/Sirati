@extends('admin.shell')

@section('admin_title', 'Leads')
@section('admin_active', 'leads')
@section('admin_eyebrow', 'Landing interest')
@section('admin_heading', 'Leads')
@section('admin_description', 'Review people who submitted the public landing form and the services they are interested in.')

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('admin.index') }}">Back to overview</a>
@endsection

@section('admin_content')
    <section class="card">
        <div class="admin-section-header">
            <div>
                <h2>Lead list</h2>
                <p class="muted">{{ $leads->total() }} leads found.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Interest</th><th>Target job</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($leads as $lead)
                        <tr>
                            <td>{{ $lead->full_name }}</td>
                            <td>{{ $lead->email }}</td>
                            <td>{{ $lead->phone ?: '-' }}</td>
                            <td>{{ $lead->role_interest }}</td>
                            <td>{{ $lead->target_job_title ?: '-' }}</td>
                            <td>{{ $lead->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No leads have been submitted yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $leads->links() }}</div>
    </section>
@endsection
