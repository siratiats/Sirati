@extends('layouts.sirati')

@section('title', 'إنشاء سيرة متوافقة مع ATS | Sirati')

@section('content')
    <section class="hero-card">
        <h1>أنشئ سيرة ذاتية متوافقة مع ATS</h1>
        <p>املأ البيانات الأساسية، وسيتم إنشاء سيرة منظمة مع تحسينات تلقائية في الصياغة والملخص والمهارات.</p>

        @if (! config('services.openai.api_key'))
            <div class="alert">بعض الميزات المتقدمة غير متاحة حالياً، وسيظهر القالب الأساسي بشكل طبيعي.</div>
        @endif

        <form method="POST" action="{{ route('generated-cvs.store') }}">
            @csrf

            <div class="grid grid-2">
                <label>الاسم الكامل<input name="full_name" value="{{ old('full_name') }}" required>@error('full_name') <span class="error">{{ $message }}</span> @enderror</label>
                <label>المسمى الوظيفي المستهدف<input name="target_job_title" value="{{ old('target_job_title') }}" required>@error('target_job_title') <span class="error">{{ $message }}</span> @enderror</label>
                <label>البريد الإلكتروني<input type="email" name="email" value="{{ old('email') }}">@error('email') <span class="error">{{ $message }}</span> @enderror</label>
                <label>الجوال<input name="phone" value="{{ old('phone') }}">@error('phone') <span class="error">{{ $message }}</span> @enderror</label>
                <label>حساب لينكدإن<input name="linkedin" value="{{ old('linkedin') }}" placeholder="https://linkedin.com/in/username">@error('linkedin') <span class="error">{{ $message }}</span> @enderror</label>
                <label>المدينة/الدولة<input name="location" value="{{ old('location') }}">@error('location') <span class="error">{{ $message }}</span> @enderror</label>
            </div>

            <label>
                لغة السيرة
                <select name="language">
                    <option value="ar" @selected(old('language', 'ar') === 'ar')>العربية</option>
                    <option value="en" @selected(old('language') === 'en')>الإنجليزية</option>
                </select>
            </label>

            <label>ملخصك الحالي أو نبذة عنك<textarea name="summary_input">{{ old('summary_input') }}</textarea>@error('summary_input') <span class="error">{{ $message }}</span> @enderror</label>
            <label>
                المهارات الأساسية
                <span class="muted">أضف المهارات واحدة تلو الأخرى، أو اختر من المقترحات.</span>
                <div class="skill-builder" data-skill-builder>
                    <div class="skill-entry">
                        <input data-skill-input placeholder="مثال: Laravel، REST API، SQL">
                        <button class="button button-secondary" type="button" data-skill-add>إضافة مهارة</button>
                    </div>

                    <div class="skill-suggestions" aria-label="مهارات مقترحة">
                        @foreach (['Laravel', 'PHP', 'REST API', 'SQL', 'MySQL', 'Git', 'Docker', 'Redis', 'Queue Jobs', 'Testing', 'AWS', 'CI/CD'] as $skill)
                            <button class="skill-suggestion" type="button" data-skill-suggestion="{{ $skill }}">{{ $skill }}</button>
                        @endforeach
                    </div>

                    <div class="skill-chips" data-skill-list aria-live="polite"></div>

                    <textarea class="skill-store" name="skills_input" required placeholder="Laravel، REST API، SQL، Git، Agile...">{{ old('skills_input') }}</textarea>
                </div>
                @error('skills_input') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>الخبرات العملية والإنجازات<textarea name="experience_input" required placeholder="اكتب خبراتك مع التواريخ والإنجازات الرقمية إن وجدت">{{ old('experience_input') }}</textarea>@error('experience_input') <span class="error">{{ $message }}</span> @enderror</label>
            <label>التعليم<textarea name="education_input" required>{{ old('education_input') }}</textarea>@error('education_input') <span class="error">{{ $message }}</span> @enderror</label>
            <label>الشهادات والدورات<textarea name="certifications_input">{{ old('certifications_input') }}</textarea>@error('certifications_input') <span class="error">{{ $message }}</span> @enderror</label>

            <button class="button" type="submit">إنشاء السيرة الآن</button>
        </form>
    </section>

    <script>
        document.documentElement.classList.add('js');

        document.querySelectorAll('[data-skill-builder]').forEach((builder) => {
            const input = builder.querySelector('[data-skill-input]');
            const addButton = builder.querySelector('[data-skill-add]');
            const list = builder.querySelector('[data-skill-list]');
            const store = builder.querySelector('[name="skills_input"]');
            store.required = false;

            const splitSkills = (value) => value
                .split(/[،,\n]/)
                .map((skill) => skill.trim())
                .filter(Boolean);

            const skills = new Set(splitSkills(store.value));

            const sync = () => {
                store.value = Array.from(skills).join('، ');
                list.innerHTML = '';

                skills.forEach((skill) => {
                    const chip = document.createElement('span');
                    chip.className = 'skill-chip';
                    chip.textContent = skill;

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.textContent = '×';
                    remove.setAttribute('aria-label', `حذف ${skill}`);
                    remove.addEventListener('click', () => {
                        skills.delete(skill);
                        sync();
                    });

                    chip.appendChild(remove);
                    list.appendChild(chip);
                });
            };

            const addSkill = (value) => {
                splitSkills(value).forEach((skill) => skills.add(skill));
                input.value = '';
                sync();
                input.focus();
            };

            addButton.addEventListener('click', () => addSkill(input.value));
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ',') {
                    event.preventDefault();
                    addSkill(input.value);
                }
            });

            builder.querySelectorAll('[data-skill-suggestion]').forEach((button) => {
                button.addEventListener('click', () => addSkill(button.dataset.skillSuggestion || button.textContent));
            });

            sync();
        });
    </script>
@endsection
