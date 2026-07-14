@extends('admin.shell')

@section('admin_title', 'Notifications')
@section('admin_active', 'notifications')
@section('admin_eyebrow', 'Engagement')
@section('admin_heading', 'Push notifications')
@section('admin_description', 'Compose and send custom push notifications to app users via Firebase Cloud Messaging.')

@section('admin_actions')
    <button class="button" type="button" onclick="document.getElementById('notification-compose-dialog').showModal()">Send notification</button>
    <a class="button button-secondary" href="{{ route('admin.index') }}">Back to overview</a>
@endsection

@section('admin_content')
    <dialog class="admin-drawer" id="notification-compose-dialog">
        <div class="drawer-body">
            <div class="drawer-header">
                <div>
                    <h3>Compose push notification</h3>
                    <p class="muted">Delivered to users with an active device token. Sending is queued, so keep a queue worker running.</p>
                </div>
                <button class="drawer-close" type="button" onclick="document.getElementById('notification-compose-dialog').close()">Close</button>
            </div>
            <form method="POST" action="{{ route('admin.notifications.send') }}" data-loading-form id="notification-compose-form">
                @csrf
                <div class="grid grid-2">
                    <label>Title<input name="title" required maxlength="180" placeholder="e.g. New jobs are live"></label>
                    <label>Category (type)<input name="type" maxlength="60" placeholder="info" value="info"></label>
                </div>
                <label>Message<textarea name="body" required maxlength="1000" placeholder="Write the notification body shown to the user."></textarea></label>

                <div class="grid grid-2">
                    <label>Audience
                        <select name="audience" required data-audience-select>
                            <option value="all">All users with an active device</option>
                            <option value="emails">Specific users (by email)</option>
                        </select>
                    </label>
                    <label>On tap
                        <select name="action_type" data-action-type-select>
                            <option value="notifications">Open notifications screen</option>
                            <option value="screen">Open an in-app screen</option>
                            <option value="url">Open a web link</option>
                        </select>
                    </label>
                </div>

                <label data-audience-emails style="display:none;">Recipient emails
                    <textarea name="emails" placeholder="Separate with commas, spaces, or new lines&#10;user1@example.com, user2@example.com"></textarea>
                </label>

                <label data-action-url-field style="display:none;">Deep link / URL
                    <input name="action_url" maxlength="255" placeholder="analysis/15" data-action-url-input>
                    <small class="muted" data-action-url-hint>Screen route like <code>analysis/15</code>.</small>
                </label>

                <p class="muted" data-recipient-preview aria-live="polite" style="margin:4px 0 0;">Estimated recipients: <strong data-recipient-count>—</strong></p>

                <div class="filter-actions">
                    <button class="button" type="submit" name="mode" value="send" data-loading-button><span data-loading-label>Queue notification</span></button>
                    <button class="button button-secondary" type="submit" name="mode" value="test">Send test to me</button>
                    <button class="button button-secondary" type="button" onclick="document.getElementById('notification-compose-dialog').close()">Cancel</button>
                </div>
            </form>
        </div>
    </dialog>

    @if ($stalledCampaigns->isNotEmpty())
        <section class="card" style="margin-bottom:16px; border-color: rgba(250,204,21,.55);">
            <h3 style="margin:0 0 6px;">⚠️ Queue may not be running</h3>
            <p class="muted" style="margin:0 0 8px;">
                {{ $stalledCampaigns->count() }} campaign(s) have been queued for over {{ $stalledAfterMinutes }} minutes without a single delivery.
                Push notifications are processed by a background worker — start it so queued sends go out:
            </p>
            <pre style="margin:0 0 8px; padding:10px 12px; background:rgba(0,0,0,.06); border-radius:8px; overflow-x:auto;"><code>php artisan queue:work</code></pre>
            <ul style="margin:0; padding-left:18px;">
                @foreach ($stalledCampaigns as $stalled)
                    <li>“{{ $stalled->title }}” — queued {{ $stalled->created_at->diffForHumans() }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card" style="margin-bottom:16px;">
        <div class="grid grid-3">
            <div><span class="admin-eyebrow">Reachable users</span><h2 style="margin:4px 0 0;">{{ number_format($notificationStats['reachable_users']) }}</h2><p class="muted">have at least one active device token</p></div>
            <div><span class="admin-eyebrow">Active tokens</span><h2 style="margin:4px 0 0;">{{ number_format($notificationStats['active_tokens']) }}</h2><p class="muted">registered devices</p></div>
            <div><span class="admin-eyebrow">Notifications stored</span><h2 style="margin:4px 0 0;">{{ number_format($notificationStats['sent_total']) }}</h2><p class="muted">in-app records, all time</p></div>
        </div>
    </section>

    <section class="card">
        <div class="admin-section-header">
            <div>
                <h2>Campaigns</h2>
                <p class="muted">{{ $campaigns->total() }} campaigns · delivery updates as the queue processes.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Title</th><th>Audience</th><th>Queued</th><th>Delivered</th><th>Failed</th><th>Status</th><th>Sent by</th><th>When</th></tr></thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td>{{ $campaign->title }}</td>
                            <td>{{ ucfirst($campaign->audience) }}</td>
                            <td>{{ number_format($campaign->recipients_queued) }}</td>
                            <td>{{ number_format($campaign->delivered) }}</td>
                            <td>{{ number_format($campaign->failed) }}</td>
                            <td>
                                @if ($campaign->status === 'completed')
                                    <span class="badge badge-success">Completed</span>
                                @elseif ($stalledCampaigns->contains('id', $campaign->id))
                                    <span class="badge" style="background:rgba(250,204,21,.2); color:#92400e;" title="No chunks processed yet — is the queue worker running?">Stalled</span>
                                @else
                                    <span class="badge">Sending…</span>
                                @endif
                            </td>
                            <td>{{ $campaign->sent_by ?? '—' }}</td>
                            <td>{{ $campaign->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No notifications have been sent yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $campaigns->links() }}</div>
    </section>

    <script>
        (function () {
            var form = document.getElementById('notification-compose-form');
            if (!form) return;

            var audienceSelect = form.querySelector('[data-audience-select]');
            var emailsField = form.querySelector('[data-audience-emails]');
            var emailsInput = emailsField ? emailsField.querySelector('textarea') : null;
            var actionSelect = form.querySelector('[data-action-type-select]');
            var urlField = form.querySelector('[data-action-url-field]');
            var urlInput = form.querySelector('[data-action-url-input]');
            var urlHint = form.querySelector('[data-action-url-hint]');
            var countEl = form.querySelector('[data-recipient-count]');
            var token = form.querySelector('input[name="_token"]');

            function syncAudience() {
                emailsField.style.display = audienceSelect.value === 'emails' ? '' : 'none';
            }

            function syncAction() {
                var v = actionSelect.value;
                var show = v === 'screen' || v === 'url';
                urlField.style.display = show ? '' : 'none';
                urlInput.required = show;
                if (v === 'url') {
                    urlInput.placeholder = 'https://example.com/page';
                    urlHint.innerHTML = 'Full web link (opens in the browser).';
                } else {
                    urlInput.placeholder = 'analysis/15';
                    urlHint.innerHTML = 'Screen route like <code>analysis/15</code>.';
                }
            }

            var previewTimer = null;
            function refreshPreview() {
                if (!countEl) return;
                countEl.textContent = '…';
                var body = new URLSearchParams();
                body.set('_token', token ? token.value : '');
                body.set('audience', audienceSelect.value);
                body.set('emails', emailsInput ? emailsInput.value : '');
                fetch('{{ route('admin.notifications.count') }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body,
                }).then(function (r) { return r.json(); })
                  .then(function (p) { countEl.textContent = (p && typeof p.count === 'number') ? p.count : '—'; })
                  .catch(function () { countEl.textContent = '—'; });
            }
            function queuePreview() {
                clearTimeout(previewTimer);
                previewTimer = setTimeout(refreshPreview, 350);
            }

            audienceSelect.addEventListener('change', function () { syncAudience(); queuePreview(); });
            if (emailsInput) emailsInput.addEventListener('input', queuePreview);
            actionSelect.addEventListener('change', syncAction);

            syncAudience();
            syncAction();
            refreshPreview();
        })();
    </script>
@endsection
