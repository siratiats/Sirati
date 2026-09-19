<?php

namespace App\Services\Ai\Prompts;

/**
 * Layout contract for generate_cv's cv_markdown, shared by every provider.
 *
 * The headings and line shapes match what App\Cv\LegacySectionParser reads
 * back into structured entries for the ATS classic template. Changing this
 * text requires bumping CachedCvAiProvider::PROMPT_VERSION.
 */
final class CvMarkdownFormat
{
    public static function instructions(): string
    {
        return <<<'TEXT'
cv_markdown format (follow exactly; omit a section when the inputs have no facts for it, never write placeholders):
- First line "# Full Name", then one line of contact details separated by " | ", before the first section.
- Use "## " section headings in this order. English: Professional Summary, Core Competencies, Professional Experience, Education, Certifications, Projects, Professional Development, Languages. Arabic: الملخص المهني، الكفاءات الأساسية، الخبرة المهنية، التعليم، الشهادات، المشاريع، التطوير المهني، اللغات.
- Professional Summary: one paragraph of 3-4 sentences, written without first-person pronouns ("I", "my", "أنا") and without closing lines about wanting the job.
- Core Competencies: one line per group as "Category | skill, skill, skill", where Category is a short group name you choose (e.g. "Technical Skills | SQL, Python" or "المهارات التقنية | SQL، Python"), never one of the skills.
- Professional Experience: each job starts with the line "Job Title | Company | Location | Start – End" (use Present / حتى الآن for a current role, skip unknown parts, never invent dates), then an optional one-line role description, then 3-5 lines starting with "- " for achievements. Separate jobs with a blank line.
- Education: one line per entry as "Degree | Institution | Start – End".
- Certifications: one line per entry as "- Certification Name | Issuer | Year".
- Projects: "Project Name | Role" followed by "- " lines.
- Professional Development: "- " lines for courses, training or awards.
- Languages: a single line naming each language with its level, e.g. "Arabic: Native | English: Fluent" or "العربية: اللغة الأم | الإنجليزية: جيد جداً". Never write the word "Language" in place of the language name.
- Plain text only inside lines: no bold, italics, tables or emojis.
TEXT;
    }
}
