@if (session('i18n_error') || $errors->any() || (session('i18n_errors') && count(session('i18n_errors')) > 0))
    <section class="l10n-alert i18n-messages" role="alert">
        @include('admin.partials.icon', ['name' => 'alert'])
        <div>
            @if (session('i18n_error'))
                <p class="l10n-alert__lead i18n-messages__lead">{{ session('i18n_error') }}</p>
            @endif
            @if ($errors->any())
                <ul class="l10n-alert__list i18n-messages__list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            @if (session('i18n_errors') && count(session('i18n_errors')) > 0)
                <ul class="l10n-alert__list i18n-messages__list">
                    @foreach (session('i18n_errors') as $key => $keyErrors)
                        <li><code>{{ $key }}</code>: {{ implode(' ', (array) $keyErrors) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
