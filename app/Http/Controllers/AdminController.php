<?php

namespace App\Http\Controllers;

use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\LandingLead;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.index', [
            'stats' => [
                'landing_leads' => LandingLead::count(),
                'analyses' => CvAnalysis::count(),
                'generated_cvs' => GeneratedCv::count(),
                'average_analysis_score' => round((float) CvAnalysis::avg('score_total'), 1),
                'average_generated_score' => round((float) GeneratedCv::avg('score_total'), 1),
            ],
            'leads' => LandingLead::latest()->limit(20)->get(),
            'analyses' => CvAnalysis::latest()->limit(20)->get(),
            'generatedCvs' => GeneratedCv::latest()->limit(20)->get(),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $token = (string) config('services.admin.access_token');

        if ($token !== '') {
            if ($request->session()->get('admin_access_granted') === true) {
                return;
            }

            if (hash_equals($token, (string) $request->query('token'))) {
                $request->session()->put('admin_access_granted', true);

                return;
            }

            abort(403, 'Admin access token is required.');
        }

        if (! app()->environment(['local', 'testing'])) {
            abort(403, 'Set ADMIN_ACCESS_TOKEN before enabling admin access outside local environments.');
        }
    }
}
