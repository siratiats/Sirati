@extends('admin.shell')

@section('admin_title', 'Users')
@section('admin_active', 'users')
@section('admin_eyebrow', 'Accounts & entitlements')
@section('admin_heading', 'Users')

@section('admin_content')
    <section class="card card-flush" aria-labelledby="users-title">
        <div class="card-head">
            <div>
                <h2 id="users-title">@include('admin.partials.icon', ['name' => 'users'])User accounts</h2>
                <p>{{ number_format($users->total()) }} {{ Str::plural('user', $users->total()) }} match the current filters.</p>
            </div>
        </div>
        <form class="user-filters" method="GET" action="{{ route('admin.users.index') }}" role="search">
            <label class="grow">Search name or email
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="e.g. sara@ or Sara Ahmed" autocomplete="off">
            </label>
            <label>Premium
                <select name="premium">
                    <option value="" @selected($filters['premium'] === '')>All</option>
                    <option value="yes" @selected($filters['premium'] === 'yes')>Premium</option>
                    <option value="no" @selected($filters['premium'] === 'no')>Free</option>
                </select>
            </label>
            <label>Email verified
                <select name="verified">
                    <option value="" @selected($filters['verified'] === '')>All</option>
                    <option value="yes" @selected($filters['verified'] === 'yes')>Verified</option>
                    <option value="no" @selected($filters['verified'] === 'no')>Not verified</option>
                </select>
            </label>
            <div class="filter-actions">
                <button class="button" type="submit">@include('admin.partials.icon', ['name' => 'search', 'class' => 'icon-sm']) Filter</button>
                @if (array_filter($filters))
                    <a class="button button-secondary" href="{{ route('admin.users.index') }}">Clear</a>
                @endif
            </div>
        </form>

        <div class="table-wrap flush">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Status</th>
                        <th class="num">Analyses</th>
                        <th class="num">Generated CVs</th>
                        <th class="num">Job matches</th>
                        <th>Joined</th>
                        <th class="num"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <a class="cell-strong" href="{{ route('admin.users.show', $user) }}">{{ $user->name ?: 'Unnamed user' }}</a>
                                <span class="cell-sub">{{ $user->email }}</span>
                            </td>
                            <td>
                                <span class="table-actions">
                                    @if ($user->isPremium())
                                        <span class="badge badge-success">Premium</span>
                                    @else
                                        <span class="badge badge-neutral">Free</span>
                                    @endif
                                    @if ($user->email_verified_at)
                                        <span class="badge badge-info">Verified</span>
                                    @else
                                        <span class="badge badge-warning">Unverified</span>
                                    @endif
                                </span>
                            </td>
                            <td class="num">{{ number_format($user->cv_analyses_count) }}</td>
                            <td class="num">{{ number_format($user->generated_cvs_count) }}</td>
                            <td class="num">{{ number_format((int) $user->job_matches_count) }}</td>
                            <td class="td-mono">{{ $user->created_at?->format('Y-m-d') }}</td>
                            <td class="num"><a class="button button-secondary button-sm" href="{{ route('admin.users.show', $user) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">No users match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $users->links('admin.partials.pagination') }}</div>
    </section>
@endsection
