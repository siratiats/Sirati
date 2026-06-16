<?php

namespace App\Http\Controllers;

use App\Models\LandingLead;
use Illuminate\Http\Request;

class LandingLeadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_interest' => ['required', 'in:analyze,create,both'],
            'target_job_title' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        LandingLead::create([
            ...$validated,
            'source' => 'landing_page',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return redirect()
            ->route('landing')
            ->with('status', 'تم تسجيل اهتمامك بنجاح. سنرسل لك نسخة تجربة Sirati عند إطلاقها.');
    }
}
