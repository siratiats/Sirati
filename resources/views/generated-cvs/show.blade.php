@extends('layouts.sirati')

@section('title', 'السيرة الجاهزة | Sirati')

@section('content')
    <section class="grid grid-2">
        <div class="hero-card">
            <h1>السيرة جاهزة</h1>
            <p>الدرجة المتوقعة بعد التوليد: <strong>{{ $generatedCv->score_total }}/100</strong> — {{ $generatedCv->grade }}</p>
            <p class="muted">الحالة: {{ $generatedCv->ai_status === 'completed' ? 'مكتمل' : 'مكتمل (الوضع الأساسي)' }}</p>
            <a class="button" href="{{ route('generated-cvs.pdf', $generatedCv) }}">تحميل ملف PDF</a>
        </div>
        <div class="card">
            <h2>ملاحظات</h2>
            @if ($generatedCv->ai_status === 'failed')
                <div class="alert">تعذر تحميل الملاحظات الإضافية حالياً.</div>
            @elseif ($generatedCv->ai_status === 'not_configured')
                <div class="alert">الملاحظات الإضافية غير متاحة حالياً.</div>
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
        <h2>نص السيرة</h2>
        <pre class="cv">{{ $generatedCv->generated_markdown }}</pre>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>درجة ATS المتوقعة</h2>
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
