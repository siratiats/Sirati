@extends('layouts.sirati')

@section('title', __('generated_cvs.show.title'))

@section('content')
    <section class="grid grid-2">
        <div class="hero-card">
            <h1>{{ __('generated_cvs.show.heading') }}</h1>
            <p>{{ __('generated_cvs.show.expected_score') }} <strong>{{ $generatedCv->score_total }}/100</strong> — {{ $generatedCv->grade }}</p>
            <p class="muted">{{ __('generated_cvs.show.status') }} {{ $generatedCv->ai_status === \App\Enums\AiStatus::Completed ? __('generated_cvs.show.status_completed') : __('generated_cvs.show.status_basic') }}</p>
            <a class="button" href="{{ $pdfUrl }}">{{ __('generated_cvs.show.download_pdf') }}</a>
        </div>
        <div class="card">
            <h2>{{ __('generated_cvs.show.notes') }}</h2>
            @if ($generatedCv->ai_status === \App\Enums\AiStatus::Failed)
                <div class="alert">{{ __('generated_cvs.show.notes_failed') }}</div>
            @elseif ($generatedCv->ai_status === \App\Enums\AiStatus::NotConfigured)
                <div class="alert">{{ __('generated_cvs.show.notes_unavailable') }}</div>
            @else
                <ul class="list">
                    @foreach (($generatedCv->ai_output['ats_notes'] ?? []) as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>{{ __('generated_cvs.show.cv_text') }}</h2>
        <pre class="cv">{{ $generatedCv->generated_markdown }}</pre>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>{{ __('generated_cvs.show.expected_ats_score') }}</h2>
        <div class="grid grid-3">
            @foreach (($generatedCv->criteria ?? []) as $criterion)
                <div>
                    <strong>{{ $criterion['label'] }}</strong>
                    <div class="muted">{{ $criterion['score'] }} / {{ $criterion['max'] }}</div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
