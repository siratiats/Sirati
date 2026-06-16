@extends('layouts.sirati')

@section('title', 'نتيجة تحليل السيرة | Sirati')

@section('content')
    <section class="grid grid-2">
        <div class="hero-card">
            <div class="score">{{ $analysis->score_total }}<small>/100</small></div>
            <h1>نتيجة التوافق مع ATS: {{ $analysis->grade }}</h1>
            <p>نسبة التوافق مع وظيفة <strong>{{ $analysis->target_job_title }}</strong>: {{ $analysis->job_match }}%</p>
            <p class="muted">طريقة الإدخال: {{ $analysis->input_method }} @if($analysis->original_filename) — {{ $analysis->original_filename }} @endif</p>
        </div>

        <div class="card">
            <h2>إصلاحات سريعة</h2>
            <ul class="list">
                @foreach ($analysis->quick_wins as $quickWin)
                    <li>{{ $quickWin }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>تفصيل المعايير</h2>
        <div class="grid grid-2">
            @foreach ($analysis->criteria as $criterion)
                @php($pct = round(($criterion['score'] / $criterion['max']) * 100))
                <div>
                    <strong>{{ $criterion['label'] }}</strong>
                    <div class="muted">{{ $criterion['score'] }} / {{ $criterion['max'] }}</div>
                    <div class="bar"><span style="width: {{ $pct }}%"></span></div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="grid grid-2" style="margin-top: 18px;">
        <div class="card">
            <h2>نقاط القوة</h2>
            <ul class="list">
                @foreach ($analysis->strengths as $strength)
                    <li>{{ $strength }}</li>
                @endforeach
            </ul>
        </div>

        <div class="card">
            <h2>نقاط الضعف</h2>
            <ul class="list">
                @foreach ($analysis->weaknesses as $weakness)
                    <li><strong>{{ $weakness['issue'] }}</strong> {{ $weakness['fix'] }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="grid grid-2" style="margin-top: 18px;">
        <div class="card">
            <h2>الكلمات الموجودة</h2>
            @forelse ($analysis->keywords_found as $keyword)
                <span class="pill">{{ $keyword }}</span>
            @empty
                <p class="muted">لم يتم رصد كلمات مفتاحية كافية.</p>
            @endforelse
        </div>

        <div class="card">
            <h2>الكلمات المقترحة</h2>
            @foreach ($analysis->keywords_missing as $keyword)
                <span class="pill">{{ $keyword }}</span>
            @endforeach
        </div>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>توصيات إضافية</h2>
        @if ($analysis->ai_status === 'completed')
            <pre class="cv">{{ json_encode($analysis->ai_feedback, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
        @elseif ($analysis->ai_status === 'failed')
            <div class="alert">تعذر تحميل التوصيات الإضافية حالياً. يمكنك متابعة العمل بالنتيجة الحالية.</div>
        @else
            <div class="alert">التوصيات الإضافية غير متاحة حالياً.</div>
        @endif
    </section>
@endsection
