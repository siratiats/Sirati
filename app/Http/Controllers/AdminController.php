<?php

namespace App\Http\Controllers;

use App\Models\CvAnalysis;
use App\Models\CvTemplate;
use App\Models\EducationContent;
use App\Models\GeneratedCv;
use App\Models\JobNews;
use App\Models\LandingLead;
use App\Services\JobsGoogleSheetSyncService;
use App\Services\JobsImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.index', [
            'stats' => $this->dashboardStats(),
            'jobNewsSummary' => $this->jobNewsSummary(),
            'jobsSheetLastSync' => Cache::get('jobs_sheet_last_sync'),
        ]);
    }

    public function cvTemplates(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.cv-templates', [
            'cvTemplates' => CvTemplate::query()
                ->ordered()
                ->paginate(12, ['*'], 'templates_page')
                ->withQueryString(),
            'cvTemplateRenderers' => array_keys(config('cv_templates.renderers', [])),
        ]);
    }

    public function education(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.education', [
            'educationContents' => EducationContent::query()
                ->orderBy('language')
                ->orderBy('sort_order')
                ->latest()
                ->paginate(25, ['*'], 'education_page')
                ->withQueryString(),
        ]);
    }

    public function jobs(Request $request)
    {
        $this->authorizeAdmin($request);

        $jobNewsQuery = $this->jobNewsAdminQuery($request);

        return view('admin.jobs', [
            'jobNewsItems' => $jobNewsQuery
                ->paginate(25, ['*'], 'jobs_page')
                ->withQueryString(),
            'jobNewsFilters' => $request->only(['job_q', 'job_source', 'job_status', 'job_language']),
            'jobNewsSummary' => $this->jobNewsSummary(),
            'jobsSheetLastSync' => Cache::get('jobs_sheet_last_sync'),
        ]);
    }

    public function leads(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.leads', [
            'leads' => LandingLead::latest()->paginate(20, ['*'], 'leads_page')->withQueryString(),
        ]);
    }

    public function analyses(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.analyses', [
            'analyses' => CvAnalysis::latest()->paginate(20, ['*'], 'analyses_page')->withQueryString(),
        ]);
    }

    public function generatedCvs(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.generated-cvs', [
            'generatedCvs' => GeneratedCv::latest()->paginate(20, ['*'], 'generated_page')->withQueryString(),
        ]);
    }

    private function dashboardStats(): array
    {
        return [
            'landing_leads' => LandingLead::count(),
            'analyses' => CvAnalysis::count(),
            'generated_cvs' => GeneratedCv::count(),
            'average_analysis_score' => round((float) CvAnalysis::avg('score_total'), 1),
            'average_generated_score' => round((float) GeneratedCv::avg('score_total'), 1),
        ];
    }

    private function jobNewsAdminQuery(Request $request)
    {
        return JobNews::query()
            ->when($request->filled('job_q'), function ($query) use ($request): void {
                $term = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('job_q')) . '%';

                $query->where(function ($query) use ($term): void {
                    $query->where('title', 'like', $term)
                        ->orWhere('company', 'like', $term)
                        ->orWhere('location', 'like', $term)
                        ->orWhere('source_row_key', 'like', $term);
                });
            })
            ->when($request->filled('job_source'), fn ($query) => $query->where('source', $request->input('job_source')))
            ->when($request->filled('job_language'), fn ($query) => $query->where('language', $request->input('job_language')))
            ->when($request->filled('job_status'), function ($query) use ($request): void {
                $today = today();

                match ($request->input('job_status')) {
                    'active' => $query
                        ->where('is_published', true)
                        ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
                        ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today)),
                    'expired' => $query->whereNotNull('valid_until')->whereDate('valid_until', '<', $today),
                    'future' => $query->whereNotNull('valid_from')->whereDate('valid_from', '>', $today),
                    'draft' => $query->where('is_published', false),
                    default => null,
                };
            })
            ->orderBy('language')
            ->orderBy('sort_order')
            ->latest('published_at')
            ->latest();
    }

    private function jobNewsSummary(): array
    {
        $today = today();

        return [
            'total' => JobNews::count(),
            'active' => JobNews::query()->active()->count(),
            'expired' => JobNews::query()->whereNotNull('valid_until')->whereDate('valid_until', '<', $today)->count(),
            'future' => JobNews::query()->whereNotNull('valid_from')->whereDate('valid_from', '>', $today)->count(),
            'draft' => JobNews::query()->where('is_published', false)->count(),
        ];
    }

    public function storeCvTemplate(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $this->validatedCvTemplateData($request);
        $template = null;

        DB::transaction(function () use (&$template, $validated, $request): void {
            if ($validated['is_default']) {
                CvTemplate::query()->update(['is_default' => false]);
                $validated['is_active'] = true;
            }

            $template = CvTemplate::create($validated);
            $this->storeCvTemplatePreview($request, $template);
        });

        return redirect()->route('admin.cv-templates.index')->with('status', 'CV template saved.');
    }

    public function updateCvTemplate(Request $request, CvTemplate $cvTemplate)
    {
        $this->authorizeAdmin($request);

        $validated = $this->validatedCvTemplateData($request, $cvTemplate);

        DB::transaction(function () use ($cvTemplate, $validated, $request): void {
            if ($validated['is_default']) {
                CvTemplate::query()->whereKeyNot($cvTemplate->id)->update(['is_default' => false]);
                $validated['is_active'] = true;
            }

            $cvTemplate->update($validated);
            $this->storeCvTemplatePreview($request, $cvTemplate);
        });

        return redirect()->route('admin.cv-templates.index')->with('status', 'CV template updated.');
    }

    public function setDefaultCvTemplate(Request $request, CvTemplate $cvTemplate)
    {
        $this->authorizeAdmin($request);

        DB::transaction(function () use ($cvTemplate): void {
            CvTemplate::query()->whereKeyNot($cvTemplate->id)->update(['is_default' => false]);
            $cvTemplate->forceFill([
                'is_active' => true,
                'is_default' => true,
            ])->save();
        });

        return redirect()->route('admin.cv-templates.index')->with('status', 'Default template set.');
    }

    public function destroyCvTemplate(Request $request, CvTemplate $cvTemplate)
    {
        $this->authorizeAdmin($request);

        if ($cvTemplate->is_default && CvTemplate::query()->active()->whereKeyNot($cvTemplate->id)->count() === 0) {
            return redirect()->route('admin.cv-templates.index')
                ->with('jobs_import_error', 'Cannot archive the only active default template.');
        }

        if ($cvTemplate->is_default) {
            $replacement = CvTemplate::query()->active()->whereKeyNot($cvTemplate->id)->ordered()->first();
            $replacement?->forceFill(['is_default' => true])->save();
        }

        $cvTemplate->delete();

        return redirect()->route('admin.cv-templates.index')->with('status', 'CV template archived.');
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

        return redirect()->route('admin.education.index')->with('status', 'Education content saved.');
    }

    public function destroyEducationContent(Request $request, EducationContent $educationContent)
    {
        $this->authorizeAdmin($request);

        $educationContent->delete();

        return redirect()->route('admin.education.index')->with('status', 'Education content deleted.');
    }

    public function storeJobNews(Request $request)
    {
        $this->authorizeAdmin($request);

        JobNews::create($this->validatedJobNewsData($request) + [
            'sort_order' => 0,
            'is_published' => false,
            'source' => JobsImportService::SOURCE_MANUAL,
        ]);

        return redirect()->route('admin.jobs.index')->with('status', 'Job saved.');
    }

    public function updateJobNews(Request $request, JobNews $jobNews)
    {
        $this->authorizeAdmin($request);

        $jobNews->update($this->validatedJobNewsData($request));

        return redirect()->route('admin.jobs.index', $request->only(['job_q', 'job_source', 'job_status', 'job_language', 'jobs_page']))
            ->with('status', 'Job updated.');
    }

    public function bulkUpdateJobNews(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:job_news,id'],
            'action' => ['required', 'in:publish,unpublish,delete'],
        ]);

        $query = JobNews::query()->whereIn('id', $validated['ids']);
        $count = $query->count();

        match ($validated['action']) {
            'publish' => $query->update(['is_published' => true]),
            'unpublish' => $query->update(['is_published' => false]),
            'delete' => $query->delete(),
        };

        $message = match ($validated['action']) {
            'publish' => "{$count} jobs published.",
            'unpublish' => "{$count} jobs unpublished.",
            'delete' => "{$count} jobs deleted.",
        };

        return redirect()->route('admin.jobs.index', $request->only(['job_q', 'job_source', 'job_status', 'job_language']))
            ->with('status', $message);
    }

    public function destroyJobNews(Request $request, JobNews $jobNews)
    {
        $this->authorizeAdmin($request);

        $jobNews->delete();

        return redirect()->route('admin.jobs.index')->with('status', 'Job deleted.');
    }

    public function importJobsFromFile(Request $request, JobsImportService $importer)
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'jobs_file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv,txt,ods'],
        ]);

        try {
            $result = $importer->importUploadedFile(
                $request->file('jobs_file'),
                JobsImportService::SOURCE_UPLOAD,
            );
        } catch (\Throwable $e) {
            return redirect()->route('admin.jobs.index')
                ->with('jobs_import_error', 'File import failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.jobs.index')
            ->with('status', $this->formatImportStatus('File import', $result))
            ->with('jobs_import_errors', $result['errors']);
    }

    public function syncJobsFromGoogleSheet(Request $request, JobsGoogleSheetSyncService $sync)
    {
        $this->authorizeAdmin($request);

        try {
            $result = $sync->sync();
        } catch (\Throwable $e) {
            return redirect()->route('admin.jobs.index')
                ->with('jobs_import_error', 'Google Sheets sync failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.jobs.index')
            ->with('status', $this->formatImportStatus('Google Sheets sync', $result))
            ->with('jobs_import_errors', $result['errors']);
    }

    public function downloadJobsTemplate(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);

        $rows = [
            ['job_id', 'language', 'title', 'company', 'location', 'body', 'url', 'apply_url', 'published_at', 'valid_from', 'valid_until', 'sort_order', 'is_published'],
            ['JOB-001', 'en', 'Flutter Developer', 'Hope Company', 'Riyadh', 'Build Flutter screens and integrate backend APIs.', 'https://example.com/jobs/JOB-001', 'https://example.com/apply/JOB-001', '2026-06-29', '2026-06-29', '2026-07-29', '0', '1'],
            ['JOB-002', 'en', 'Backend Engineer', 'Example Co.', 'Remote', 'Build and maintain Laravel services.', 'https://example.com/jobs/JOB-002', 'https://example.com/apply/JOB-002', '2026-06-29', '2026-06-29', '2026-07-29', '0', '1'],
        ];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens Arabic correctly
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'jobs-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formatImportStatus(string $action, array $result): string
    {
        return sprintf(
            '%s: %d created, %d updated, %d skipped, %d errors.',
            $action,
            $result['created'],
            $result['updated'],
            $result['skipped'],
            count($result['errors']),
        );
    }

    private function validatedCvTemplateData(Request $request, ?CvTemplate $template = null): array
    {
        $validated = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:140', Rule::unique('cv_templates', 'slug')->ignore($template?->id)],
            'renderer_key' => ['required', Rule::in(array_keys(config('cv_templates.renderers', [])))],
            'preview_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'language_direction' => ['required', 'in:rtl,ltr,both'],
            'supported_languages' => ['required', 'array', 'min:1'],
            'supported_languages.*' => ['in:ar,en'],
            'supported_sections' => ['nullable', 'array'],
            'supported_sections.*' => ['string', 'max:60'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name_en'] ?: $validated['name_ar']);
        $validated['supported_sections'] = array_values($validated['supported_sections'] ?? []);
        $validated['color_tokens'] = array_filter([
            'primary' => $validated['primary_color'] ?? null,
            'accent' => $validated['accent_color'] ?? null,
        ]);
        $validated['config_json'] = [];
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['is_default'] = (bool) ($validated['is_default'] ?? false);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        unset($validated['preview_image'], $validated['primary_color'], $validated['accent_color']);

        return $validated;
    }

    private function storeCvTemplatePreview(Request $request, CvTemplate $template): void
    {
        if (! $request->hasFile('preview_image')) {
            return;
        }

        if ($template->preview_image_path) {
            Storage::disk('public')->delete($template->preview_image_path);
        }

        $path = $request->file('preview_image')->store('cv-template-previews/'.$template->slug, 'public');
        $template->forceFill(['preview_image_path' => $path])->save();
    }

    private function validatedJobNewsData(Request $request): array
    {
        return $request->validate([
            'language' => ['required', 'in:ar,en'],
            'title' => ['required', 'string', 'max:180'],
            'company' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:3000'],
            'url' => ['nullable', 'url', 'max:255'],
            'apply_url' => ['nullable', 'url', 'max:500'],
            'published_at' => ['nullable', 'date'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_published' => ['nullable', 'boolean'],
        ]) + [
            'sort_order' => 0,
            'is_published' => false,
        ];
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
