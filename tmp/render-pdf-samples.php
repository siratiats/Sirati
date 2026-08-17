<?php

use App\Models\CvTemplate;
use App\Models\GeneratedCv;
use App\Services\Cv\CvMarkdownRenderer;
use App\Services\CvTemplateRenderer;
use Dompdf\Dompdf;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$outputDirectory = __DIR__.'/pdfs';
if (! is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0777, true);
}

$markdownRenderer = $app->make(CvMarkdownRenderer::class);
$templateRenderer = $app->make(CvTemplateRenderer::class);

foreach (['en', 'ar'] as $language) {
    $arabic = $language === 'ar';
    $markdown = $arabic
        ? "## الملخص المهني\n\nمطور برمجيات متخصص في بناء الأنظمة الموثوقة وتحسين تجربة المستخدم.\n\n## الخبرة العملية\n\n- طورت واجهات API لخدمة فرق المنتجات الداخلية\n- حسنت جودة الاختبارات الآلية ووضوح التوثيق\n- أدرت تسليم المزايا بالتعاون مع فرق متعددة\n\n## المهارات\n\n- Laravel وPHP وSQL\n- REST APIs وGit وDocker\n\n## التعليم\n\nبكالوريوس علوم الحاسب — جامعة الملك سعود — 2020"
        : "## Professional Summary\n\nSoftware developer focused on reliable systems and measurable product outcomes.\n\n## Experience\n\n- Built APIs for internal product teams\n- Improved automated test quality and documentation\n- Coordinated feature delivery across multiple teams\n\n## Skills\n\n- Laravel, PHP, and SQL\n- REST APIs, Git, and Docker\n\n## Education\n\nBSc Computer Science — King Saud University — 2020";

    foreach (['classic_rtl' => 'generated-cvs.pdf', 'modern_rtl' => 'generated-cvs.templates.modern-rtl'] as $rendererKey => $view) {
        $template = new CvTemplate([
            'name_ar' => 'نموذج',
            'name_en' => 'Template',
            'slug' => $rendererKey,
            'renderer_key' => $rendererKey,
            'supported_languages' => ['ar', 'en'],
            'color_tokens' => ['primary' => '#12384f', 'accent' => '#008f82'],
        ]);
        $cv = new GeneratedCv([
            'full_name' => $arabic ? 'أحمد علي' : 'Ahmed Ali',
            'email' => 'ahmed@example.com',
            'phone' => '+966 50 000 0000',
            'linkedin' => 'linkedin.com/in/ahmed',
            'location' => $arabic ? 'الرياض، السعودية' : 'Riyadh, Saudi Arabia',
            'target_job_title' => $arabic ? 'مطور برمجيات' : 'Software Developer',
            'language' => $language,
            'skills_input' => 'Laravel, PHP, SQL',
            'experience_input' => 'Experience details',
            'education_input' => 'Education details',
            'generated_markdown' => $markdown,
            'form_payload' => [],
            'score_total' => 88,
            'grade' => 'A',
        ]);
        $pdfData = [
            'name' => $markdownRenderer->shapeText($cv->full_name, $language),
            'targetJobTitle' => $markdownRenderer->shapeText($cv->target_job_title, $language),
            'contacts' => array_map(
                fn (string $value): string => $markdownRenderer->shapeText($value, $language),
                [$cv->email, $cv->phone, $cv->linkedin, $cv->location],
            ),
            'contentHtml' => $markdownRenderer->render($markdown, $language),
        ];
        $html = view($view, [
            'generatedCv' => $cv,
            'pdfData' => $pdfData,
            'cv' => $templateRenderer->viewModel($cv, $template),
            'template' => $template,
        ])->render();
        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();
        file_put_contents(
            $outputDirectory."/{$rendererKey}-{$language}.pdf",
            $pdf->output(),
        );
    }
}
