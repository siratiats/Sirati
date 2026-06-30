@extends('layouts.sirati')

@section('title', 'لوحة الإدارة | Sirati')
@section('body_class', 'admin-body')
@section('page_class', 'admin-page')
@section('hide_nav', true)

@section('content')
    @php
        $jobFilters = $jobNewsFilters ?? [];
        $jobNewsSummary = $jobNewsSummary ?? ['total' => 0, 'active' => 0, 'expired' => 0, 'future' => 0, 'draft' => 0];
        $jobsSheetLastSync = $jobsSheetLastSync ?? null;
        $jobSourceLabels = [
            'manual' => 'يدوي / Manual',
            'excel_upload' => 'Excel',
            'google_sheet' => 'Google Sheets',
        ];
        $jobStatusLabels = [
            'active' => 'نشطة / Active',
            'expired' => 'منتهية / Expired',
            'future' => 'قادمة / Future',
            'draft' => 'مسودة / Draft',
        ];
        $jobFilterUrl = fn (array $overrides = []) => route('admin.index', array_filter(array_merge($jobFilters, $overrides), fn ($value) => filled($value)));
    @endphp

    <div class="admin-shell">
        <aside class="admin-sidebar" aria-label="Admin navigation" dir="rtl">
            <a class="admin-sidebar-brand" href="{{ route('admin.index') }}">
                <span class="mark">س</span>
                <span>
                    <strong>Sirati Admin</strong>
                    <span>مركز إدارة التطبيق</span>
                </span>
            </a>

            <nav class="admin-side-nav" aria-label="Admin sections">
                <a href="#cv-templates"><span>قوالب السيرة</span><small>Templates</small></a>
                <a href="#education"><span>محتوى التعليم</span><small>Education</small></a>
                <a href="#job-news-form"><span>إضافة وظيفة</span><small>Add job</small></a>
                <a href="#job-import"><span>استيراد الوظائف</span><small>Import</small></a>
                <a href="#job-news-table"><span>إدارة الوظائف</span><small>Jobs</small></a>
                <a href="#education-list"><span>قائمة التعليم</span><small>Library</small></a>
                <a href="#leads"><span>المهتمون</span><small>Leads</small></a>
                <a href="#analyses"><span>التحليلات</span><small>Analyses</small></a>
                <a href="#generated-cvs"><span>السير المنشأة</span><small>CVs</small></a>
            </nav>

            <div class="admin-sidebar-footer">
                <a class="button button-secondary" href="{{ route('landing') }}">عرض الموقع / View site</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="button button-danger" type="submit" style="width:100%;">تسجيل الخروج / Logout</button>
                </form>
            </div>
        </aside>

        <section class="admin-main">
            <header class="admin-topbar">
                <div>
                    <span class="admin-eyebrow">لوحة متابعة النسخة الأولية / Operations dashboard</span>
                    <h1>إدارة سيرتي من مكان واحد</h1>
                    <p>تابع الطلب الحقيقي، أدر قوالب السير ومحتوى التطبيق، وراجع الوظائف والتحليلات من واجهة عمل مخصصة للإدارة بدلاً من تخطيط صفحة الهبوط.</p>
                </div>
                <div class="admin-topbar-actions">
                    <a class="button button-secondary" href="{{ route('generated-cvs.create') }}">إنشاء سيرة</a>
                    <a class="button" href="#job-import">استيراد وظائف</a>
                </div>
            </header>

            <section class="admin-kpi-grid" aria-label="Dashboard metrics">
                <article class="admin-kpi-card">
                    <span>المهتمون / Leads</span>
                    <strong>{{ $stats['landing_leads'] }}</strong>
                    <p class="muted">طلبات اهتمام من صفحة الهبوط.</p>
                </article>
                <article class="admin-kpi-card">
                    <span>تحليلات السير / Analyses</span>
                    <strong>{{ $stats['analyses'] }}</strong>
                    <p class="muted">متوسط الدرجة: {{ $stats['average_analysis_score'] }}</p>
                </article>
                <article class="admin-kpi-card">
                    <span>السير المنشأة / Generated CVs</span>
                    <strong>{{ $stats['generated_cvs'] }}</strong>
                    <p class="muted">متوسط الدرجة: {{ $stats['average_generated_score'] }}</p>
                </article>
            </section>

    @if (session('status') || session('jobs_import_error'))
        <div class="toast-stack" aria-live="polite">
            @if (session('status'))
                <section class="toast toast-success" data-toast>
                    <p>{{ session('status') }}</p>
                </section>
            @endif

            @if (session('jobs_import_error'))
                <section class="toast toast-error" data-toast>
                    <p>{{ session('jobs_import_error') }}</p>
                </section>
            @endif
        </div>
    @endif

    @if (session('jobs_import_errors') && count(session('jobs_import_errors')) > 0)
        <section class="card" style="margin-top: 18px; border-color: rgba(250,204,21,.55);">
            <h3 style="margin: 0 0 8px;">تنبيهات استيراد الوظائف</h3>
            <ul style="margin: 0; padding-inline-start: 18px; color: #fde68a;">
                @foreach (session('jobs_import_errors') as $error)
                    <li>صف {{ $error['row'] ?? '?' }}: {{ $error['message'] ?? '' }}</li>
                @endforeach
            </ul>
        </section>
    @endif

            <div class="admin-content">

    <section class="card" id="cv-templates" style="margin-top: 18px;">
        <div class="admin-section-header">
            <div>
                <h2>قوالب تصميم السيرة / CV design templates</h2>
                <p class="muted">أدر القوالب الآمنة التي تظهر في التطبيق عند تصدير السيرة. القوالب نفسها Blade موثوقة، والإدارة هنا للبيانات والصورة والحالة فقط.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.cv-templates.store') }}" enctype="multipart/form-data" style="margin-top: 16px;">
            @csrf
            <div class="grid grid-3">
                <label>الاسم بالعربية / Arabic name<input name="name_ar" required placeholder="كلاسيكي"></label>
                <label>الاسم بالإنجليزية / English name<input name="name_en" required placeholder="Classic"></label>
                <label>المعرّف / Slug<input name="slug" placeholder="classic-rtl"></label>
                <label>مفتاح العرض / Renderer key
                    <select name="renderer_key" required>
                        @foreach ($cvTemplateRenderers as $renderer)
                            <option value="{{ $renderer }}">{{ $renderer }}</option>
                        @endforeach
                    </select>
                </label>
                <label>اتجاه اللغة / Direction
                    <select name="language_direction" required>
                        <option value="rtl">RTL</option>
                        <option value="ltr">LTR</option>
                        <option value="both">RTL + LTR</option>
                    </select>
                </label>
                <label>الترتيب / Sort order<input name="sort_order" type="number" value="0" min="0"></label>
                <label>اللون الأساسي / Primary color<input name="primary_color" value="#1f2937" pattern="^#[0-9a-fA-F]{6}$"></label>
                <label>لون التمييز / Accent color<input name="accent_color" value="#2563eb" pattern="^#[0-9a-fA-F]{6}$"></label>
                <label>صورة المعاينة / Preview image<input type="file" name="preview_image" accept="image/png,image/jpeg,image/webp"></label>
            </div>
            <div class="filter-actions">
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_languages[]" value="ar" checked> العربية / Arabic</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_languages[]" value="en" checked> English</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="summary" checked> الملخص / Summary</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="skills" checked> المهارات / Skills</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="experience" checked> الخبرات / Experience</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="education" checked> التعليم / Education</label>
            </div>
            <div class="filter-actions">
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_active" value="1" checked> نشط في التطبيق / Active in app</label>
                <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_default" value="1"> القالب الافتراضي / Default template</label>
            </div>
            <button class="button" type="submit">حفظ القالب / Save template</button>
        </form>

        <div style="margin-top: 20px;">
            @if ($cvTemplates->count() > 0)
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>المعاينة</th><th>القالب</th><th>المعرّف</th><th>الحالة</th><th>اللغات</th><th>إجراء</th></tr></thead>
                        <tbody>
                            @foreach ($cvTemplates as $template)
                                <tr>
                                    <td>
                                        @if ($template->preview_image_path)
                                            <img src="{{ asset('storage/'.$template->preview_image_path) }}" alt="{{ $template->name_ar }}" style="width: 76px; height: 96px; object-fit: cover; border-radius: 10px; border: 1px solid rgba(148,163,184,.3);">
                                        @else
                                            <span class="badge badge-neutral">بدون صورة / No image</span>
                                        @endif
                                    </td>
                                    <td><strong>{{ $template->name_ar }}</strong><br><span class="muted">{{ $template->name_en }} · {{ $template->renderer_key }}</span></td>
                                    <td>{{ $template->slug }}</td>
                                    <td>
                                        <span class="badge {{ $template->is_active ? 'badge-success' : 'badge-neutral' }}">{{ $template->is_active ? 'نشط / Active' : 'غير نشط / Inactive' }}</span>
                                        @if ($template->is_default)
                                            <span class="badge badge-info">افتراضي / Default</span>
                                        @endif
                                    </td>
                                    <td>{{ implode(', ', $template->supported_languages ?: []) }}</td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="button button-secondary" type="button" onclick="document.getElementById('cv-template-edit-{{ $template->id }}').showModal()">تعديل / Edit</button>
                                            @unless ($template->is_default)
                                                <form method="POST" action="{{ route('admin.cv-templates.default', $template) }}">
                                                    @csrf
                                                    <button class="button button-secondary" type="submit">افتراضي / Default</button>
                                                </form>
                                            @endunless
                                            <form method="POST" action="{{ route('admin.cv-templates.destroy', $template) }}" onsubmit="return confirm('أرشفة هذا القالب؟ / Archive this template?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="button button-danger" type="submit">أرشفة / Archive</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @foreach ($cvTemplates as $template)
                    <dialog class="admin-drawer" id="cv-template-edit-{{ $template->id }}" dir="rtl">
                        <div class="drawer-body">
                            <div class="drawer-header">
                                <div>
                                    <h3>تعديل قالب السيرة / Edit CV template</h3>
                                    <p class="muted">{{ $template->name_ar }} · {{ $template->slug }}</p>
                                </div>
                                <button class="drawer-close" type="button" onclick="document.getElementById('cv-template-edit-{{ $template->id }}').close()">×</button>
                            </div>
                            <form method="POST" action="{{ route('admin.cv-templates.update', $template) }}" enctype="multipart/form-data">
                                @csrf
                                @method('PATCH')
                                <div class="grid grid-2">
                                    <label>الاسم بالعربية / Arabic name<input name="name_ar" value="{{ $template->name_ar }}" required></label>
                                    <label>الاسم بالإنجليزية / English name<input name="name_en" value="{{ $template->name_en }}" required></label>
                                    <label>المعرّف / Slug<input name="slug" value="{{ $template->slug }}" required></label>
                                    <label>مفتاح العرض / Renderer key
                                        <select name="renderer_key" required>
                                            @foreach ($cvTemplateRenderers as $renderer)
                                                <option value="{{ $renderer }}" @selected($template->renderer_key === $renderer)>{{ $renderer }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>اتجاه اللغة / Direction
                                        <select name="language_direction" required>
                                            <option value="rtl" @selected($template->language_direction === 'rtl')>RTL</option>
                                            <option value="ltr" @selected($template->language_direction === 'ltr')>LTR</option>
                                            <option value="both" @selected($template->language_direction === 'both')>RTL + LTR</option>
                                        </select>
                                    </label>
                                    <label>الترتيب / Sort order<input name="sort_order" type="number" value="{{ $template->sort_order }}" min="0"></label>
                                    <label>اللون الأساسي / Primary color<input name="primary_color" value="{{ $template->color_tokens['primary'] ?? '#1f2937' }}" pattern="^#[0-9a-fA-F]{6}$"></label>
                                    <label>لون التمييز / Accent color<input name="accent_color" value="{{ $template->color_tokens['accent'] ?? '#2563eb' }}" pattern="^#[0-9a-fA-F]{6}$"></label>
                                    <label>صورة المعاينة / Preview image<input type="file" name="preview_image" accept="image/png,image/jpeg,image/webp"></label>
                                </div>
                                <div class="filter-actions">
                                    <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_languages[]" value="ar" @checked(in_array('ar', $template->supported_languages ?: [], true))> العربية / Arabic</label>
                                    <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_languages[]" value="en" @checked(in_array('en', $template->supported_languages ?: [], true))> English</label>
                                    @foreach (['summary' => 'الملخص / Summary', 'skills' => 'المهارات / Skills', 'experience' => 'الخبرات / Experience', 'education' => 'التعليم / Education'] as $section => $label)
                                        <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="supported_sections[]" value="{{ $section }}" @checked(in_array($section, $template->supported_sections ?: [], true))> {{ $label }}</label>
                                    @endforeach
                                </div>
                                <div class="filter-actions">
                                    <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_active" value="1" @checked($template->is_active)> نشط في التطبيق / Active in app</label>
                                    <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;"><input class="table-check" type="checkbox" name="is_default" value="1" @checked($template->is_default)> القالب الافتراضي / Default template</label>
                                </div>
                                <div class="filter-actions">
                                    <button class="button" type="submit">حفظ التعديلات / Save changes</button>
                                    <button class="button button-secondary" type="button" onclick="document.getElementById('cv-template-edit-{{ $template->id }}').close()">إلغاء / Cancel</button>
                                </div>
                            </form>
                        </div>
                    </dialog>
                @endforeach
                <div class="pagination-wrap">{{ $cvTemplates->links() }}</div>
            @else
                <div class="empty-state">
                    <div class="empty-state-icon">＋</div>
                    <h3>لا توجد قوالب سير بعد / No CV templates yet</h3>
                    <p class="muted">أضف أول قالب ليتمكن المستخدمون من اختيار تصميم السيرة عند التصدير. / Add the first template so users can choose a CV design when exporting.</p>
                </div>
            @endif
        </div>
    </section>

    <section class="card" id="education" style="margin-top: 18px;">
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

    <section class="card" id="job-news-form" style="margin-top: 18px;">
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
                <label>صالح من<input name="valid_from" type="date"></label>
                <label>صالح حتى<input name="valid_until" type="date"></label>
                <label>الترتيب<input name="sort_order" type="number" value="0" min="0"></label>
                <label>الرابط<input name="url" type="url" placeholder="https://..."></label>
                <label>رابط التقديم<input name="apply_url" type="url" placeholder="https://..."></label>
            </div>
            <label>العنوان<input name="title" required></label>
            <label>التفاصيل<textarea name="body" required placeholder="اكتب تفاصيل الخبر أو الفرصة"></textarea></label>
            <label style="display: flex; gap: 8px; align-items: center;"><input type="checkbox" name="is_published" value="1" checked style="width: auto;"> منشور في التطبيق</label>
            <button class="button" type="submit">حفظ خبر الوظيفة</button>
        </form>
    </section>

    <section class="card" id="job-import" style="margin-top: 18px;">
        <div class="admin-section-header">
            <div>
                <h2>استيراد الوظائف من Excel أو Google Sheets</h2>
                <p class="muted">
                    ارفع ملف <code>.xlsx</code> أو <code>.csv</code> أو اسحب من Google Sheets المنشور كـ CSV. الصفوف ذات نفس <code>job_id</code> يتم تحديثها بدلاً من تكرارها.
                </p>
            </div>
            <a class="button button-secondary" href="{{ route('admin.job-news.template') }}">تنزيل القالب / Download template</a>
        </div>

        <div class="grid grid-2" style="margin-top: 16px;">
            <form method="POST" action="{{ route('admin.job-news.import') }}" enctype="multipart/form-data" data-loading-form>
                @csrf
                <label>ملف الوظائف / Jobs file
                    <input type="file" name="jobs_file" accept=".xlsx,.xls,.csv,.ods" required>
                </label>
                <button class="button" type="submit" data-loading-button><span data-loading-label>رفع وحفظ الوظائف / Upload jobs</span></button>
            </form>

            <div class="sync-card">
                <p class="muted" style="margin: 0 0 8px;">
                    رابط الجدول الحالي / Current sheet URL:
                    <code>{{ config('services.jobs_sheet.csv_url') ?: 'غير مهيّأ / Not configured' }}</code>
                </p>
                @if ($jobsSheetLastSync)
                    <p>
                        آخر مزامنة: {{ \Illuminate\Support\Carbon::parse($jobsSheetLastSync['synced_at'])->diffForHumans() }} •
                        {{ $jobsSheetLastSync['created'] }} جديد، {{ $jobsSheetLastSync['updated'] }} تحديث، {{ $jobsSheetLastSync['skipped'] }} تخطي
                    </p>
                    <p class="muted">
                        Last sync: {{ \Illuminate\Support\Carbon::parse($jobsSheetLastSync['synced_at'])->diffForHumans() }} •
                        {{ $jobsSheetLastSync['created'] }} new, {{ $jobsSheetLastSync['updated'] }} updated, {{ $jobsSheetLastSync['skipped'] }} skipped
                    </p>
                @else
                    <p>آخر مزامنة: لم تتم بعد / Last sync: Never</p>
                @endif
                <form method="POST" action="{{ route('admin.job-news.sync') }}" data-loading-form>
                    @csrf
                    <button class="button" type="submit" data-loading-button @disabled(! config('services.jobs_sheet.csv_url'))>
                        <span data-loading-label>مزامنة الآن / Sync now</span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="card" id="job-news-table" style="margin-top: 18px;">
        <div class="admin-section-header">
            <div>
                <h2>إدارة الوظائف / Job management</h2>
                <p class="muted">ابحث وفلتر الوظائف قبل ظهورها في تطبيق Flutter. النتائج الحالية: {{ $jobNewsItems->total() }}.</p>
            </div>
        </div>

        <div class="metric-row">
            <div class="metric-card"><strong>{{ $jobNewsSummary['total'] }}</strong><span>الإجمالي / Total</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['active'] }}</strong><span>نشطة / Active</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['expired'] }}</strong><span>منتهية / Expired</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['future'] }}</strong><span>قادمة / Future</span></div>
            <div class="metric-card"><strong>{{ $jobNewsSummary['draft'] }}</strong><span>مسودة / Draft</span></div>
        </div>

        <form method="GET" action="{{ route('admin.index') }}" class="filter-panel">
            <div class="filter-row">
                <label>بحث / Search
                    <input name="job_q" value="{{ $jobFilters['job_q'] ?? '' }}" placeholder="العنوان، الشركة، المدينة، job_id">
                </label>
                <label>المصدر / Source
                    <select name="job_source">
                        <option value="">كل المصادر / All sources</option>
                        @foreach ($jobSourceLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($jobFilters['job_source'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>الحالة / Status
                    <select name="job_status">
                        <option value="">كل الحالات / All statuses</option>
                        @foreach ($jobStatusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($jobFilters['job_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>اللغة / Language
                    <select name="job_language">
                        <option value="">كل اللغات / All languages</option>
                        <option value="ar" @selected(($jobFilters['job_language'] ?? '') === 'ar')>العربية / Arabic</option>
                        <option value="en" @selected(($jobFilters['job_language'] ?? '') === 'en')>English</option>
                    </select>
                </label>
            </div>
            <div class="filter-actions">
                <button class="button" type="submit">تطبيق الفلاتر / Apply filters</button>
                <a class="button button-secondary" href="{{ route('admin.index') }}#job-news-table">مسح / Clear</a>
                <a class="chip {{ ($jobFilters['job_status'] ?? '') === 'active' ? 'chip-active' : '' }}" href="{{ $jobFilterUrl(['job_status' => 'active']) }}#job-news-table">نشطة / Active</a>
                <a class="chip {{ ($jobFilters['job_status'] ?? '') === 'expired' ? 'chip-active' : '' }}" href="{{ $jobFilterUrl(['job_status' => 'expired']) }}#job-news-table">منتهية / Expired</a>
                <a class="chip {{ ($jobFilters['job_source'] ?? '') === 'google_sheet' ? 'chip-active' : '' }}" href="{{ $jobFilterUrl(['job_source' => 'google_sheet']) }}#job-news-table">Google Sheets</a>
            </div>
        </form>

        @if ($jobNewsItems->count() > 0)
            <form id="jobs-bulk-form" method="POST" action="{{ route('admin.job-news.bulk') }}" onsubmit="return confirm('تنفيذ الإجراء على الوظائف المحددة؟ / Apply action to selected jobs?')">
                @csrf
                @foreach ($jobFilters as $key => $value)
                    @if (filled($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
            </form>
            <div class="bulk-bar">
                <div>
                    <label style="display:flex; grid-template-columns: none; align-items:center; gap:8px; margin:0;">
                        <input class="table-check" type="checkbox" data-check-all=".job-row-check">
                        تحديد الكل / Select all
                    </label>
                </div>
                <div>
                    <select name="action" form="jobs-bulk-form" required>
                        <option value="">اختر إجراء / Choose action</option>
                        <option value="publish">نشر / Publish</option>
                        <option value="unpublish">إلغاء النشر / Unpublish</option>
                        <option value="delete">حذف / Delete</option>
                    </select>
                    <button class="button button-secondary" type="submit" form="jobs-bulk-form">تطبيق على المحدد / Apply</button>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead><tr><th></th><th>اللغة</th><th>العنوان</th><th>الشركة</th><th>الموقع</th><th>الصلاحية</th><th>المصدر</th><th>منشور</th><th>إجراء</th></tr></thead>
                    <tbody>
                        @foreach ($jobNewsItems as $item)
                            @php
                                $today = today();
                                if (! $item->is_published) {
                                    $status = 'draft';
                                    $statusClass = 'badge-neutral';
                                } elseif ($item->valid_from && $item->valid_from->isAfter($today)) {
                                    $status = 'future';
                                    $statusClass = 'badge-warning';
                                } elseif ($item->valid_until && $item->valid_until->isBefore($today)) {
                                    $status = 'expired';
                                    $statusClass = 'badge-danger';
                                } else {
                                    $status = 'active';
                                    $statusClass = 'badge-success';
                                }
                                $sourceClass = match ($item->source) {
                                    'google_sheet' => 'badge-info',
                                    'excel_upload' => 'badge-success',
                                    default => 'badge-neutral',
                                };
                            @endphp
                            <tr>
                                <td><input class="table-check job-row-check" type="checkbox" name="ids[]" value="{{ $item->id }}" form="jobs-bulk-form"></td>
                                <td>{{ $item->language === 'ar' ? 'العربية' : 'English' }}</td>
                                <td><strong>{{ $item->title }}</strong><br><span class="muted">{{ $item->source_row_key ?: 'بدون معرف / No key' }}</span></td>
                                <td>{{ $item->company ?: '-' }}</td>
                                <td>{{ $item->location ?: '-' }}</td>
                                <td><span class="badge {{ $statusClass }}">{{ $jobStatusLabels[$status] }}</span><br><span class="muted">حتى / Until: {{ $item->valid_until?->format('Y-m-d') ?: '-' }}</span></td>
                                <td><span class="badge {{ $sourceClass }}">{{ $jobSourceLabels[$item->source] ?? $item->source ?? 'manual' }}</span></td>
                                <td>{{ $item->is_published ? 'نعم / Yes' : 'لا / No' }}</td>
                                <td>
                                    <div class="table-actions">
                                        <button class="button button-secondary" type="button" onclick="document.getElementById('job-edit-{{ $item->id }}').showModal()">تعديل / Edit</button>
                                        <form method="POST" action="{{ route('admin.job-news.destroy', $item) }}" onsubmit="return confirm('حذف هذه الوظيفة؟ / Delete this job?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-danger" type="submit">حذف / Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach ($jobNewsItems as $item)
                <dialog class="admin-drawer" id="job-edit-{{ $item->id }}" dir="rtl">
                    <div class="drawer-body">
                        <div class="drawer-header">
                            <div>
                                <h3>تعديل الوظيفة / Edit job</h3>
                                <p class="muted">{{ $item->title }}</p>
                            </div>
                            <button class="drawer-close" type="button" onclick="document.getElementById('job-edit-{{ $item->id }}').close()">×</button>
                        </div>
                        <form method="POST" action="{{ route('admin.job-news.update', $item) }}">
                            @csrf
                            @method('PATCH')
                            @foreach ($jobFilters as $key => $value)
                                @if (filled($value))
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <input type="hidden" name="jobs_page" value="{{ $jobNewsItems->currentPage() }}">
                            <div class="grid grid-2">
                                <label>اللغة / Language
                                    <select name="language" required>
                                        <option value="ar" @selected($item->language === 'ar')>العربية</option>
                                        <option value="en" @selected($item->language === 'en')>English</option>
                                    </select>
                                </label>
                                <label>الشركة / Company<input name="company" value="{{ $item->company }}"></label>
                                <label>الموقع / Location<input name="location" value="{{ $item->location }}"></label>
                                <label>تاريخ النشر / Published at<input name="published_at" type="datetime-local" value="{{ $item->published_at?->format('Y-m-d\\TH:i') }}"></label>
                                <label>صالح من / Valid from<input name="valid_from" type="date" value="{{ $item->valid_from?->format('Y-m-d') }}"></label>
                                <label>صالح حتى / Valid until<input name="valid_until" type="date" value="{{ $item->valid_until?->format('Y-m-d') }}"></label>
                                <label>الترتيب / Sort order<input name="sort_order" type="number" value="{{ $item->sort_order }}" min="0"></label>
                                <label>الرابط / URL<input name="url" type="url" value="{{ $item->url }}"></label>
                                <label>رابط التقديم / Apply URL<input name="apply_url" type="url" value="{{ $item->apply_url }}"></label>
                            </div>
                            <label>العنوان / Title<input name="title" value="{{ $item->title }}" required></label>
                            <label>التفاصيل / Details<textarea name="body" required>{{ $item->body }}</textarea></label>
                            <label style="display:flex; grid-template-columns:none; gap:8px; align-items:center;">
                                <input class="table-check" type="checkbox" name="is_published" value="1" @checked($item->is_published)> منشور في التطبيق / Published in app
                            </label>
                            <div class="filter-actions">
                                <button class="button" type="submit">حفظ التعديلات / Save changes</button>
                                <button class="button button-secondary" type="button" onclick="document.getElementById('job-edit-{{ $item->id }}').close()">إلغاء / Cancel</button>
                            </div>
                        </form>
                    </div>
                </dialog>
            @endforeach

            <div class="pagination-wrap">{{ $jobNewsItems->links() }}</div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">＋</div>
                <h3>لا توجد وظائف مطابقة / No matching jobs</h3>
                <p class="muted">غيّر الفلاتر أو استورد ملف وظائف جديد. / Change filters or import a new jobs file.</p>
                <a class="button" href="#job-import">استيراد الوظائف / Import jobs</a>
            </div>
        @endif
    </section>

    <section class="card" id="education-list" style="margin-top: 18px;">
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
        <div class="pagination-wrap">{{ $educationContents->links() }}</div>
    </section>

    <section class="card" id="leads" style="margin-top: 18px;">
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
        <div class="pagination-wrap">{{ $leads->links() }}</div>
    </section>

    <section class="card" id="analyses" style="margin-top: 18px;">
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
        <div class="pagination-wrap">{{ $analyses->links() }}</div>
    </section>

    <section class="card" id="generated-cvs" style="margin-top: 18px;">
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
        <div class="pagination-wrap">{{ $generatedCvs->links() }}</div>
    </section>
            </div>
        </section>
    </div>
@endsection
