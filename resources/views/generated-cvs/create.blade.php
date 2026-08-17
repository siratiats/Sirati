@extends('layouts.sirati')

@section('title', __('generated_cvs.create.title'))

@section('content')
    <section class="hero-card">
        <h1>{{ __('generated_cvs.create.heading') }}</h1>
        <p>{{ __('generated_cvs.create.intro') }}</p>

        @if (! config('services.openai.api_key'))
            <div class="alert">{{ __('generated_cvs.create.basic_mode_notice') }}</div>
        @endif

        <form method="POST" action="{{ route('generated-cvs.store') }}">
            @csrf

            <div class="grid grid-2">
                <label>{{ __('generated_cvs.create.full_name') }}<input name="full_name" value="{{ old('full_name') }}" required>@error('full_name') <span class="error">{{ $message }}</span> @enderror</label>
                <label>{{ __('generated_cvs.create.target_job_title') }}<input name="target_job_title" value="{{ old('target_job_title') }}" required>@error('target_job_title') <span class="error">{{ $message }}</span> @enderror</label>
                <label>{{ __('generated_cvs.create.email') }}<input type="email" name="email" value="{{ old('email') }}">@error('email') <span class="error">{{ $message }}</span> @enderror</label>
                <label>{{ __('generated_cvs.create.phone') }}<input name="phone" value="{{ old('phone') }}">@error('phone') <span class="error">{{ $message }}</span> @enderror</label>
                <label>{{ __('generated_cvs.create.linkedin') }}<input name="linkedin" value="{{ old('linkedin') }}" placeholder="https://linkedin.com/in/username">@error('linkedin') <span class="error">{{ $message }}</span> @enderror</label>
                <label>{{ __('generated_cvs.create.location') }}<input name="location" value="{{ old('location') }}">@error('location') <span class="error">{{ $message }}</span> @enderror</label>
            </div>

            <label>
                {{ __('generated_cvs.create.language') }}
                <select name="language">
                    <option value="ar" @selected(old('language', 'ar') === 'ar')>{{ __('generated_cvs.create.arabic') }}</option>
                    <option value="en" @selected(old('language') === 'en')>{{ __('generated_cvs.create.english') }}</option>
                </select>
            </label>

            <label>{{ __('generated_cvs.create.summary') }}<textarea name="summary_input">{{ old('summary_input') }}</textarea>@error('summary_input') <span class="error">{{ $message }}</span> @enderror</label>
            <label>
                {{ __('generated_cvs.create.skills') }}
                <span class="muted">{{ __('generated_cvs.create.skills_hint') }}</span>
                <div class="skill-builder" data-skill-builder>
                    <div class="skill-entry">
                        <input data-skill-input placeholder="{{ __('generated_cvs.create.skill_example') }}">
                        <button class="button button-secondary" type="button" data-skill-add>{{ __('generated_cvs.create.add_skill') }}</button>
                    </div>

                    <div class="skill-suggestions" aria-label="{{ __('generated_cvs.create.suggested_skills') }}">
                        @foreach (['Laravel', 'PHP', 'REST API', 'SQL', 'MySQL', 'Git', 'Docker', 'Redis', 'Queue Jobs', 'Testing', 'AWS', 'CI/CD'] as $skill)
                            <button class="skill-suggestion" type="button" data-skill-suggestion="{{ $skill }}">{{ $skill }}</button>
                        @endforeach
                    </div>

                    <div class="skill-chips" data-skill-list aria-live="polite"></div>

                    <textarea class="skill-store" name="skills_input" required placeholder="{{ __('generated_cvs.create.skills_placeholder') }}">{{ old('skills_input') }}</textarea>
                </div>
                @error('skills_input') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>{{ __('generated_cvs.create.experience') }}<textarea name="experience_input" required placeholder="{{ __('generated_cvs.create.experience_placeholder') }}">{{ old('experience_input') }}</textarea>@error('experience_input') <span class="error">{{ $message }}</span> @enderror</label>
            <label>{{ __('generated_cvs.create.education') }}<textarea name="education_input" required>{{ old('education_input') }}</textarea>@error('education_input') <span class="error">{{ $message }}</span> @enderror</label>
            <label>{{ __('generated_cvs.create.certifications') }}<textarea name="certifications_input">{{ old('certifications_input') }}</textarea>@error('certifications_input') <span class="error">{{ $message }}</span> @enderror</label>

            <button class="button" type="submit">{{ __('generated_cvs.create.submit') }}</button>
        </form>
    </section>

    <script>
        document.documentElement.classList.add('js');
        const removeSkillLabel = @json(__('generated_cvs.create.remove_skill'));

        document.querySelectorAll('[data-skill-builder]').forEach((builder) => {
            const input = builder.querySelector('[data-skill-input]');
            const addButton = builder.querySelector('[data-skill-add]');
            const list = builder.querySelector('[data-skill-list]');
            const store = builder.querySelector('[name="skills_input"]');
            store.required = false;

            const splitSkills = (value) => value
                .split(/[\u060C,\n]/)
                .map((skill) => skill.trim())
                .filter(Boolean);

            const skills = new Set(splitSkills(store.value));

            const sync = () => {
                store.value = Array.from(skills).join('\u060C ');
                list.innerHTML = '';

                skills.forEach((skill) => {
                    const chip = document.createElement('span');
                    chip.className = 'skill-chip';
                    chip.textContent = skill;

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.textContent = '×';
                    remove.setAttribute('aria-label', `${removeSkillLabel} ${skill}`);
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
