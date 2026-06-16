@extends('layouts.sirati')

@section('title', 'لوحة الإدارة | Sirati')

@section('content')
    <section class="hero-card">
        <h1>لوحة متابعة النسخة الأولية</h1>
        <p>عرض سريع للمهتمين، تحليلات السير، والسير المولدة حتى تعرف أين يوجد الطلب الحقيقي.</p>
    </section>

    <section class="grid grid-3" style="margin-top: 18px;">
        <div class="card"><h3>المهتمون</h3><p style="font-size: 34px; font-weight: 900;">{{ $stats['landing_leads'] }}</p></div>
        <div class="card"><h3>تحليلات السير</h3><p style="font-size: 34px; font-weight: 900;">{{ $stats['analyses'] }}</p><p class="muted">المتوسط: {{ $stats['average_analysis_score'] }}</p></div>
        <div class="card"><h3>السير المنشأة</h3><p style="font-size: 34px; font-weight: 900;">{{ $stats['generated_cvs'] }}</p><p class="muted">المتوسط: {{ $stats['average_generated_score'] }}</p></div>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>آخر المهتمين</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الاسم</th><th>الإيميل</th><th>الجوال</th><th>الاهتمام</th><th>الوظيفة</th><th>التاريخ</th></tr></thead>
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
                        <tr><td colspan="6">لا توجد بيانات مهتمين بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>آخر تحليلات السيرة</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>المعرف</th><th>الوظيفة</th><th>الدرجة</th><th>التطابق</th><th>الحالة</th><th>التاريخ</th></tr></thead>
                <tbody>
                    @forelse ($analyses as $analysis)
                        <tr>
                            <td><a href="{{ route('analyses.show', $analysis) }}">#{{ $analysis->id }}</a></td>
                            <td>{{ $analysis->target_job_title }}</td>
                            <td>{{ $analysis->score_total }}/100 — {{ $analysis->grade }}</td>
                            <td>{{ $analysis->job_match }}%</td>
                            <td>{{ $analysis->ai_status === 'completed' ? 'مكتمل' : ($analysis->ai_status === 'failed' ? 'تعذر التنفيذ' : 'الوضع الأساسي') }}</td>
                            <td>{{ $analysis->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">لا توجد تحليلات بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>آخر السير المنشأة</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>المعرف</th><th>الاسم</th><th>الوظيفة</th><th>الدرجة</th><th>الحالة</th><th>ملف PDF</th><th>التاريخ</th></tr></thead>
                <tbody>
                    @forelse ($generatedCvs as $cv)
                        <tr>
                            <td><a href="{{ route('generated-cvs.show', $cv) }}">#{{ $cv->id }}</a></td>
                            <td>{{ $cv->full_name }}</td>
                            <td>{{ $cv->target_job_title }}</td>
                            <td>{{ $cv->score_total }}/100 — {{ $cv->grade }}</td>
                            <td>{{ $cv->ai_status === 'completed' ? 'مكتمل' : ($cv->ai_status === 'failed' ? 'تعذر التنفيذ' : 'الوضع الأساسي') }}</td>
                            <td><a href="{{ route('generated-cvs.pdf', $cv) }}">تحميل</a></td>
                            <td>{{ $cv->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">لا توجد سير مولدة بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
