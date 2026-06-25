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

    @if (session('status'))
        <section class="card" style="margin-top: 18px; border-color: rgba(34,197,94,.45);">
            <p style="margin: 0; color: #bbf7d0;">{{ session('status') }}</p>
        </section>
    @endif

    <section class="card" style="margin-top: 18px;">
        <h2>إدارة محتوى التعليم في التطبيق</h2>
        <p class="muted">أضف مقالات أو دورات أو أخباراً تظهر في شاشة التعليم داخل تطبيق Flutter. عند عدم وجود محتوى منشور، يستخدم التطبيق محتوى افتراضياً.</p>

        <form method="POST" action="{{ route('admin.education-contents.store') }}" style="margin-top: 16px;">
            @csrf
            <div class="grid grid-3">
                <label>اللغة
                    <select name="language" required>
                        <option value="ar">العربية</option>
                        <option value="en">English</option>
                    </select>
                </label>
                <label>النوع
                    <select name="type" required>
                        <option value="study">دراسة / مقال</option>
                        <option value="certificate">شهادة</option>
                        <option value="news">خبر</option>
                    </select>
                </label>
                <label>الترتيب
                    <input name="sort_order" type="number" value="0" min="0">
                </label>
            </div>
            <div class="grid grid-2">
                <label>العنوان<input name="title" required></label>
                <label>الوظيفة المستهدفة<input name="target_role" placeholder="محلل بيانات / Data Analyst"></label>
                <label>مدة القراءة<input name="duration_label" placeholder="مدة القراءة: ١٥ دقيقة"></label>
                <label>الشارة<input name="badge" placeholder="موصى به لك / Recommended"></label>
                <label>زر الإجراء<input name="button_label" placeholder="ابدأ التعلم الآن"></label>
                <label>الأيقونة<input name="icon" placeholder="book أو psychology"></label>
            </div>
            <label>المحتوى<textarea name="body" required placeholder="اكتب ملخص المحتوى أو وصف الدورة"></textarea></label>
            <label style="display: flex; gap: 8px; align-items: center;"><input type="checkbox" name="is_featured" value="1" style="width: auto;"> محتوى مميز كبير</label>
            <label style="display: flex; gap: 8px; align-items: center;"><input type="checkbox" name="is_published" value="1" checked style="width: auto;"> منشور في التطبيق</label>
            <button class="button" type="submit">حفظ محتوى التعليم</button>
        </form>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>إدارة أخبار الوظائف في التطبيق</h2>
        <p class="muted">أضف أخباراً أو فرصاً وظيفية تظهر في تبويب أخبار الوظائف داخل تطبيق Flutter.</p>

        <form method="POST" action="{{ route('admin.job-news.store') }}" style="margin-top: 16px;">
            @csrf
            <div class="grid grid-3">
                <label>اللغة
                    <select name="language" required>
                        <option value="ar">العربية</option>
                        <option value="en">English</option>
                    </select>
                </label>
                <label>الشركة<input name="company" placeholder="اسم الشركة"></label>
                <label>الموقع<input name="location" placeholder="الرياض / Remote"></label>
                <label>تاريخ النشر<input name="published_at" type="datetime-local"></label>
                <label>الترتيب<input name="sort_order" type="number" value="0" min="0"></label>
                <label>الرابط<input name="url" type="url" placeholder="https://..."></label>
            </div>
            <label>العنوان<input name="title" required></label>
            <label>التفاصيل<textarea name="body" required placeholder="اكتب تفاصيل الخبر أو الفرصة"></textarea></label>
            <label style="display: flex; gap: 8px; align-items: center;"><input type="checkbox" name="is_published" value="1" checked style="width: auto;"> منشور في التطبيق</label>
            <button class="button" type="submit">حفظ خبر الوظيفة</button>
        </form>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>أخبار الوظائف الحالية</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>اللغة</th><th>العنوان</th><th>الشركة</th><th>الموقع</th><th>منشور</th><th>إجراء</th></tr></thead>
                <tbody>
                    @forelse ($jobNewsItems as $item)
                        <tr>
                            <td>{{ $item->language }}</td>
                            <td>{{ $item->title }}</td>
                            <td>{{ $item->company ?: '-' }}</td>
                            <td>{{ $item->location ?: '-' }}</td>
                            <td>{{ $item->is_published ? 'نعم' : 'لا' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.job-news.destroy', $item) }}" onsubmit="return confirm('حذف هذا الخبر؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-secondary" type="submit">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">لا توجد أخبار وظائف مضافة بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2>محتوى التعليم الحالي</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>اللغة</th><th>النوع</th><th>العنوان</th><th>منشور</th><th>مميز</th><th>الترتيب</th><th>إجراء</th></tr></thead>
                <tbody>
                    @forelse ($educationContents as $content)
                        <tr>
                            <td>{{ $content->language }}</td>
                            <td>{{ $content->type }}</td>
                            <td>{{ $content->title }}</td>
                            <td>{{ $content->is_published ? 'نعم' : 'لا' }}</td>
                            <td>{{ $content->is_featured ? 'نعم' : 'لا' }}</td>
                            <td>{{ $content->sort_order }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.education-contents.destroy', $content) }}" onsubmit="return confirm('حذف هذا المحتوى؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-secondary" type="submit">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">لا يوجد محتوى تعليم مضاف بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
