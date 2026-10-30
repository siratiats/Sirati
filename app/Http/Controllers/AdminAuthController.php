<?php

namespace App\Http\Controllers;

use App\Services\Admin\AdminAllowList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(): View
    {
        return view('admin.login');
    }

    public function store(Request $request, AdminAllowList $allowList): RedirectResponse
    {
        // Misconfiguration is a 503 before any credential check, never a fake "wrong password".
        $allowList->assertConfigured();

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'بيانات تسجيل الدخول غير صحيحة.',
            ]);
        }

        $request->session()->regenerate();

        if (! $allowList->allows($request->user()?->email)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'هذا الحساب لا يملك صلاحية الدخول إلى لوحة الإدارة.',
            ]);
        }

        return redirect()->intended(route('admin.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'تم تسجيل الخروج بنجاح.');
    }
}
