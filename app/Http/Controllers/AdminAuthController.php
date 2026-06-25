<?php

namespace App\Http\Controllers;

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

    public function store(Request $request): RedirectResponse
    {
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

        if (! $this->isAllowedAdmin($request->user()?->email)) {
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

    private function isAllowedAdmin(?string $email): bool
    {
        $allowedEmails = config('services.admin.emails', []);

        if ($allowedEmails === []) {
            return app()->environment(['local', 'testing']);
        }

        return $email !== null && in_array($email, $allowedEmails, true);
    }
}