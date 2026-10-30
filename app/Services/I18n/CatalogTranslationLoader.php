<?php

namespace App\Services\I18n;

use Illuminate\Contracts\Translation\Loader;

/**
 * Laravel's validation lines come from the label catalog (serverValidation*,
 * serverAttribute*), so validation errors the app shows follow the app
 * language and are editable like any label. Lines the catalog does not cover
 * keep the lang/ files.
 */
final class CatalogTranslationLoader implements Loader
{
    /** Laravel validation line => catalog key. */
    public const VALIDATION_LINES = [
        'required' => 'serverValidationRequired',
        'string' => 'serverValidationString',
        'email' => 'serverValidationEmail',
        'file' => 'serverValidationFile',
        'image' => 'serverValidationImage',
        'mimes' => 'serverValidationMimes',
        'max.string' => 'serverValidationMaxString',
        'max.file' => 'serverValidationMaxFile',
        'max.numeric' => 'serverValidationMaxNumeric',
        'max.array' => 'serverValidationMaxArray',
        'min.string' => 'serverValidationMinString',
        'min.file' => 'serverValidationMinFile',
        'min.numeric' => 'serverValidationMinNumeric',
        'min.array' => 'serverValidationMinArray',
        'between.numeric' => 'serverValidationBetweenNumeric',
        'between.string' => 'serverValidationBetweenString',
        'size.string' => 'serverValidationSizeString',
        'in' => 'serverValidationIn',
        'integer' => 'serverValidationInteger',
        'numeric' => 'serverValidationNumeric',
        'boolean' => 'serverValidationBoolean',
        'array' => 'serverValidationArray',
        'regex' => 'serverValidationRegex',
        'url' => 'serverValidationUrl',
        'date' => 'serverValidationDate',
        'exists' => 'serverValidationExists',
        'unique' => 'serverValidationUnique',
        'confirmed' => 'serverValidationConfirmed',
        'required_if' => 'serverValidationRequiredIf',
        'accepted' => 'serverValidationAccepted',
        'alpha_dash' => 'serverValidationAlphaDash',
    ];

    /** Field name => catalog key for its display name. */
    public const ATTRIBUTES = [
        'name' => 'serverAttributeName',
        'full_name' => 'serverAttributeFullName',
        'email' => 'serverAttributeEmail',
        'password' => 'serverAttributePassword',
        'current_password' => 'serverAttributeCurrentPassword',
        'code' => 'serverAttributeCode',
        'phone' => 'serverAttributePhone',
        'location' => 'serverAttributeLocation',
        'job_title' => 'serverAttributeJobTitle',
        'job_title_id' => 'serverAttributeJobTitle',
        'job_title_other' => 'serverAttributeJobTitleOther',
        'avatar' => 'serverAttributeAvatar',
        'role_interest' => 'serverAttributeRoleInterest',
        'target_job_title' => 'serverAttributeTargetJobTitle',
        'notes' => 'serverAttributeNotes',
        'resume_text' => 'serverAttributeResumeText',
        'resume_file' => 'serverAttributeResumeFile',
        'linkedin' => 'serverAttributeLinkedin',
        'language' => 'serverAttributeLanguage',
        'summary_input' => 'serverAttributeSummary',
        'skills_input' => 'serverAttributeSkills',
        'experience_input' => 'serverAttributeExperience',
        'education_input' => 'serverAttributeEducation',
        'certifications_input' => 'serverAttributeCertifications',
        'job_description' => 'serverAttributeJobDescription',
        'job_description_input' => 'serverAttributeJobDescription',
        'draft' => 'serverAttributeDraft',
        'template' => 'serverAttributeTemplate',
    ];

    public function __construct(private readonly Loader $inner)
    {
    }

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->inner->load($locale, $group, $namespace);

        if ($group !== 'validation' || ($namespace !== null && $namespace !== '*')) {
            return $lines;
        }

        $text = app(ServerText::class);
        foreach (self::VALIDATION_LINES as $line => $key) {
            data_set($lines, $line, self::laravelPlaceholders($text->get($key, [], $locale)));
        }
        foreach (self::ATTRIBUTES as $field => $key) {
            $lines['attributes'][$field] = $text->get($key, [], $locale);
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->inner->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->inner->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->inner->namespaces();
    }

    /** ICU "{attribute}" is Laravel ":attribute". */
    private static function laravelPlaceholders(string $value): string
    {
        return preg_replace('/\{(\w+)\}/u', ':$1', $value) ?? $value;
    }
}
