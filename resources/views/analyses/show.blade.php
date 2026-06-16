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
            @php($feedback = $analysis->ai_feedback ?? [])

            @if (filled(data_get($feedback, 'warnings')))
                <div class="alert">{{ implode('، ', data_get($feedback, 'warnings', [])) }}</div>
            @endif

            <div class="recommendations">
                @if (filled(data_get($feedback, 'executive_summary')))
                    <div class="recommendation-block">
                        <h3>ملخص عام</h3>
                        <p>{{ data_get($feedback, 'executive_summary') }}</p>
                    </div>
                @endif

                @if (filled(data_get($feedback, 'top_priorities')))
                    <div class="recommendation-block">
                        <h3>أهم الأولويات</h3>
                        <ol class="priority-list">
                            @foreach (data_get($feedback, 'top_priorities', []) as $priority)
                                <li><strong>{{ $loop->iteration }}</strong><span>{{ $priority }}</span></li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                @if (filled(data_get($feedback, 'rewritten_summary')))
                    <div class="recommendation-block">
                        <h3>صياغة مقترحة للملخص المهني</h3>
                        <p>{{ data_get($feedback, 'rewritten_summary') }}</p>
                    </div>
                @endif

                @if (filled(data_get($feedback, 'bullet_improvements')))
                    <div class="recommendation-block">
                        <h3>تحسينات مقترحة لنقاط الخبرة</h3>
                        <div class="improvement-list">
                            @foreach (data_get($feedback, 'bullet_improvements', []) as $improvement)
                                <div class="improvement-item">
                                    @if (filled(data_get($improvement, 'before')))
                                        <p><span class="improvement-label">قبل:</span>{{ data_get($improvement, 'before') }}</p>
                                    @endif
                                    @if (filled(data_get($improvement, 'after')))
                                        <p><span class="improvement-label">بعد:</span>{{ data_get($improvement, 'after') }}</p>
                                    @endif
                                    @if (filled(data_get($improvement, 'reason')))
                                        <p class="muted"><span class="improvement-label">السبب:</span>{{ data_get($improvement, 'reason') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (filled(data_get($feedback, 'keyword_recommendations')))
                    <div class="recommendation-block">
                        <h3>كلمات مفتاحية مقترحة</h3>
                        <div class="keyword-row">
                            @foreach (data_get($feedback, 'keyword_recommendations', []) as $keyword)
                                <span class="pill">{{ $keyword }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (blank(collect($feedback)->except(['warnings'])->filter()->all()))
                    <div class="alert">لا توجد توصيات إضافية قابلة للعرض حالياً.</div>
                @endif
            </div>
        @elseif ($analysis->ai_status === 'failed')
            <div class="alert">تعذر تحميل التوصيات الإضافية حالياً. يمكنك متابعة العمل بالنتيجة الحالية.</div>
        @else
            <div class="alert">التوصيات الإضافية غير متاحة حالياً.</div>
        @endif
    </section>
@endsection
