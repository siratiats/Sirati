@if (session('i18n_error') || $errors->any() || (session('i18n_errors') && count(session('i18n_errors')) > 0))
    <section class="card i18n-messages" role="alert">
        @if (session('i18n_error'))
            <p class="field-error i18n-messages__lead">{{ session('i18n_error') }}</p>
        @endif
        @if ($errors->any())
            <ul class="i18n-messages__list">
                @foreach ($errors->all() as $error)
                    <li class="field-error">{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        @if (session('i18n_errors') && count(session('i18n_errors')) > 0)
            <ul class="i18n-messages__list">
                @foreach (session('i18n_errors') as $key => $keyErrors)
                    <li><code>{{ $key }}</code>: <span class="field-error">{{ implode(' ', (array) $keyErrors) }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
