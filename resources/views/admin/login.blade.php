@extends('layouts.sirati')

@section('html_lang', 'en')
@section('html_dir', 'ltr')
@section('title', 'Admin Login | Sirati')

@section('content')
    <section class="hero-card" style="max-width: 560px; margin: 0 auto;">
        <h1>Admin login</h1>
        <p>Sign in with an admin account to manage analyses, generated CVs, jobs, content, and leads.</p>

        @if (session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf

            <label>
                Email address
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" dir="ltr">
                @error('email') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>
                Password
                <input type="password" name="password" required autocomplete="current-password" dir="ltr">
                @error('password') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label style="display: flex; grid-template-columns: none; align-items: center; gap: 8px; font-weight: 700;">
                <input type="checkbox" name="remember" value="1" style="width: auto;">
                Remember me
            </label>

            <button class="button" type="submit">Enter admin panel</button>
        </form>
    </section>
@endsection