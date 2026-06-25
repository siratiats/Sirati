@extends('layouts.sirati')

@section('title', 'تسجيل دخول الإدارة | Sirati')

@section('content')
    <section class="hero-card" style="max-width: 560px; margin: 0 auto;">
        <h1>تسجيل دخول الإدارة</h1>
        <p>ادخل بحساب المدير لمتابعة التحليلات والسير المولدة وطلبات المهتمين.</p>

        @if (session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf

            <label>
                البريد الإلكتروني
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" dir="ltr">
                @error('email') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>
                كلمة المرور
                <input type="password" name="password" required autocomplete="current-password" dir="ltr">
                @error('password') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label style="display: flex; grid-template-columns: none; align-items: center; gap: 8px; font-weight: 700;">
                <input type="checkbox" name="remember" value="1" style="width: auto;">
                تذكرني
            </label>

            <button class="button" type="submit">دخول لوحة الإدارة</button>
        </form>
    </section>
@endsection