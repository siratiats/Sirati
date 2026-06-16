@extends('layouts.sirati')

@section('title', 'فحص السيرة | Sirati')

@section('content')
    <section class="hero-card">
        <h1>ابدأ تحليل سيرتك الذاتية</h1>
        <p>ارفع ملف PDF أو TXT، أو الصق نص السيرة، ثم أدخل المسمى الوظيفي المستهدف للحصول على درجة التوافق مع ATS وتوصيات تحسين واضحة.</p>

        <p>
            <a class="button button-secondary" href="{{ route('analyses.create', ['demo' => 1]) }}">جرّب بسيرة تجريبية</a>
        </p>

        @if (! config('services.openai.api_key'))
            <div class="alert">بعض الميزات المتقدمة غير متاحة حالياً، وسيتم عرض التحليل الأساسي.</div>
        @endif

        <form method="POST" action="{{ route('analyses.store') }}" enctype="multipart/form-data">
            @csrf

            <label>
                المسمى الوظيفي المستهدف
                <input name="target_job_title" value="{{ old('target_job_title', $demo['target_job_title'] ?? '') }}" placeholder="مثال: مطور باك إند Laravel" required>
                @error('target_job_title') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>
                رفع السيرة (PDF أو TXT)
                <input type="file" name="resume_file" accept=".pdf,.txt,application/pdf,text/plain">
                @error('resume_file') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>
                أو الصق نص السيرة هنا
                <textarea name="resume_text" placeholder="الصق نص السيرة الذاتية الكامل...">{{ old('resume_text', $demo['resume_text'] ?? '') }}</textarea>
                @error('resume_text') <span class="error">{{ $message }}</span> @enderror
            </label>

            <button class="button" type="submit">تحليل السيرة الآن</button>
        </form>
    </section>
@endsection
