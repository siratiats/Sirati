<?php

namespace App\Http\Controllers;

use App\Models\CvAnalysis;
use App\Models\EducationContent;
use App\Models\GeneratedCv;
use App\Models\JobNews;
use App\Models\LandingLead;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
            'educationContents' => EducationContent::query()
                ->orderBy('language')
                ->orderBy('sort_order')
                ->latest()
                ->limit(40)
                ->get(),
            'jobNewsItems' => JobNews::query()
                ->orderBy('language')
                ->orderBy('sort_order')
                ->latest('published_at')
                ->limit(40)
                ->get(),
        ]);
    }

    public function storeEducationContent(Request $request)
    {
        $this->authorizeAdmin($request);

        EducationContent::create($request->validate([
            'language' => ['required', 'in:ar,en'],
            'type' => ['required', 'in:study,certificate,news'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:2000'],
            'duration_label' => ['nullable', 'string', 'max:80'],
            'target_role' => ['nullable', 'string', 'max:120'],
            'badge' => ['nullable', 'string', 'max:80'],
            'button_label' => ['nullable', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]) + [
            'sort_order' => 0,
            'is_featured' => false,
            'is_published' => false,
        ]);

        return redirect()->route('admin.index')->with('status', 'تم حفظ محتوى التعليم.');
    }

    public function destroyEducationContent(Request $request, EducationContent $educationContent)
    {
        $this->authorizeAdmin($request);

        $educationContent->delete();

        return redirect()->route('admin.index')->with('status', 'تم حذف محتوى التعليم.');
    }

    public function storeJobNews(Request $request)
    {
        $this->authorizeAdmin($request);

        JobNews::create($request->validate([
            'language' => ['required', 'in:ar,en'],
            'title' => ['required', 'string', 'max:180'],
            'company' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:3000'],
            'url' => ['nullable', 'url', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_published' => ['nullable', 'boolean'],
        ]) + [
            'sort_order' => 0,
            'is_published' => false,
        ]);

        return redirect()->route('admin.index')->with('status', 'تم حفظ خبر الوظيفة.');
    }

    public function destroyJobNews(Request $request, JobNews $jobNews)
    {
        $this->authorizeAdmin($request);

        $jobNews->delete();

        return redirect()->route('admin.index')->with('status', 'تم حذف خبر الوظيفة.');
    }

    private function authorizeAdmin(Request $request): void
    {
        $allowedEmails = config('services.admin.emails', []);

        if ($allowedEmails !== [] && ! in_array($request->user()?->email, $allowedEmails, true)) {
            throw new AccessDeniedHttpException('This account is not allowed to access the admin panel.');
        }

        if ($allowedEmails === [] && ! app()->environment(['local', 'testing'])) {
            throw new AccessDeniedHttpException('Set ADMIN_EMAILS before enabling admin access outside local environments.');
        }
    }
}
