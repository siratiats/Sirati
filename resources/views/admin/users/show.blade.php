@extends('admin.shell')

@section('admin_title', 'User '.$user->id)
@section('admin_active', 'users')
@section('admin_eyebrow', 'Users')
@section('admin_heading', $user->name ?: 'User #'.$user->id)

@section('admin_actions')
    <a class="button button-secondary" href="{{ route('admin.users.index') }}">Back to users</a>
@endsection

@section('admin_content')
    <div class="detail-grid">
        <div class="stack">
            <section class="card" aria-labelledby="activity-title">
                <h2 id="activity-title">Activity</h2>
                <div class="count-grid">
                    <div class="stat"><span>CV analyses</span><strong>{{ number_format($user->cv_analyses_count) }}</strong></div>
                    <div class="stat"><span>Generated CVs</span><strong>{{ number_format($user->generated_cvs_count) }}</strong></div>
                    <div class="stat"><span>Job matches</span><strong>{{ number_format((int) $user->job_matches_count) }}</strong></div>
                </div>
            </section>

            <section class="card" aria-labelledby="profile-title">
                <h2 id="profile-title">Profile</h2>
                <dl class="dl">
                    <dt>Email</dt><dd>{{ $user->email }}</dd>
                    <dt>Email verified</dt><dd>{{ $user->email_verified_at ? $user->email_verified_at->format('Y-m-d H:i') : 'Not verified' }}</dd>
                    <dt>Phone</dt><dd>{{ $user->phone ?: '–' }}</dd>
                    <dt>Location</dt><dd>{{ $user->location ?: '–' }}</dd>
                    <dt>Joined</dt><dd>{{ $user->created_at?->format('Y-m-d H:i') }}</dd>
                    <dt>User ID</dt><dd class="td-mono">{{ $user->id }}</dd>
                </dl>
            </section>

            <section class="card card-flush" aria-labelledby="audit-title">
                <div class="card-head"><h2 id="audit-title">Subscription history</h2></div>
                @if ($auditLogs->isEmpty())
                    <p class="card-note">No subscription changes recorded.</p>
                @else
                    <div class="table-wrap flush">
                        <table>
                            <thead><tr><th>When</th><th>Action</th><th>Premium until</th><th>Reason</th></tr></thead>
                            <tbody>
                                @foreach ($auditLogs as $log)
                                    <tr>
                                        <td class="td-mono">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                                        <td><span class="badge badge-neutral">{{ $log->action }}</span></td>
                                        <td class="td-mono">{{ $log->new_premium_until ? \Illuminate\Support\Carbon::parse($log->new_premium_until)->format('Y-m-d') : '–' }}</td>
                                        <td>{{ $log->reason ?: '–' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <section class="card" aria-labelledby="premium-title">
            <div class="admin-section-header">
                <h2 id="premium-title">Premium</h2>
                @if ($user->isPremium())
                    <span class="badge badge-success">Premium</span>
                @else
                    <span class="badge badge-neutral">Free</span>
                @endif
            </div>
            <dl class="dl" style="grid-template-columns: 110px minmax(0, 1fr); margin-bottom: 16px;">
                <dt>Until</dt><dd>{{ $user->premium_until ? $user->premium_until->format('Y-m-d H:i') : ($user->is_premium ? 'No end date' : '–') }}</dd>
                <dt>Plan</dt><dd>{{ $user->subscription_plan_id ?: '–' }}</dd>
                <dt>Provider</dt><dd>{{ $user->subscription_provider ?: '–' }}</dd>
            </dl>

            <form method="POST" action="{{ route('admin.users.grant-premium', $user) }}">
                @csrf
                <label>Grant premium for (days)
                    <input type="number" name="days" value="30" min="1" max="3650" required>
                </label>
                <label>Reason (optional)
                    <input type="text" name="reason" maxlength="500" placeholder="Granted manually via Admin panel">
                </label>
                <button class="button button-teal" type="submit">Grant premium</button>
            </form>

            @if ($user->isPremium())
                <form method="POST" action="{{ route('admin.users.revoke-premium', $user) }}" data-confirm="Revoke premium for this user?" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border);">
                    @csrf
                    <label>Revoke reason (optional)
                        <input type="text" name="reason" maxlength="500">
                    </label>
                    <button class="button button-danger" type="submit">Revoke premium</button>
                </form>
            @endif
        </section>
    </div>
@endsection
