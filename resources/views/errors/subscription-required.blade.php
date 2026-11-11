@extends('layouts.sirati')

@section('title', $titles['en'].' | Siratie')

@section('content')
    {{-- A Premium-only link opened in a browser (e.g. a CV PDF on the free plan). --}}
    <section class="hero-card" dir="rtl" lang="ar">
        <h1>{{ $titles['ar'] }}</h1>
        <p>{{ $messages['ar'] }}</p>
    </section>

    <section class="card" dir="ltr" lang="en" style="margin-top: 18px;">
        <h2>{{ $titles['en'] }}</h2>
        <p>{{ $messages['en'] }}</p>
    </section>
@endsection
