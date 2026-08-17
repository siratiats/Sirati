<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use App\Services\AtsScoringService;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class JobTitleSeeder extends Seeder
{
    public function run(): void
    {
        $validCategories = array_keys(AtsScoringService::jobKeywords());

        foreach ($this->titles() as $index => $title) {
            if (! in_array($title['category'], $validCategories, true)) {
                throw new InvalidArgumentException(
                    "Job title category [{$title['category']}] is not a valid AtsScoringService job keyword key."
                );
            }

            JobTitle::query()->updateOrCreate(
                ['slug' => $title['slug']],
                [
                    'name_ar' => $title['name_ar'],
                    'name_en' => $title['name_en'],
                    'category' => $title['category'],
                    'keywords' => $title['keywords'],
                    'is_active' => $title['is_active'] ?? true,
                    'sort_order' => $title['sort_order'] ?? ($index + 1),
                ],
            );
        }
    }

    /**
     * 70 MENA/Gulf-oriented titles + "other". Arabic primary, English secondary.
     *
     * @return list<array{
     *     slug: string,
     *     name_ar: string,
     *     name_en: string,
     *     category: string,
     *     keywords: list<string>,
     *     sort_order?: int,
     *     is_active?: bool
     * }>
     */
    private function titles(): array
    {
        return [
            // software (12)
            ['slug' => 'software-engineer', 'name_ar' => 'مهندس برمجيات', 'name_en' => 'Software Engineer', 'category' => 'software', 'keywords' => ['software', 'engineer', 'programming', 'backend', 'frontend', 'api']],
            ['slug' => 'laravel-developer', 'name_ar' => 'مطور Laravel', 'name_en' => 'Laravel Developer', 'category' => 'software', 'keywords' => ['laravel', 'php', 'backend', 'api', 'sql']],
            ['slug' => 'frontend-developer', 'name_ar' => 'مطور واجهات أمامية', 'name_en' => 'Frontend Developer', 'category' => 'software', 'keywords' => ['frontend', 'react', 'javascript', 'ui', 'css']],
            ['slug' => 'backend-developer', 'name_ar' => 'مطور خلفية', 'name_en' => 'Backend Developer', 'category' => 'software', 'keywords' => ['backend', 'api', 'php', 'node', 'sql']],
            ['slug' => 'mobile-developer', 'name_ar' => 'مطور تطبيقات جوال', 'name_en' => 'Mobile Developer', 'category' => 'software', 'keywords' => ['flutter', 'mobile', 'ios', 'android', 'app']],
            ['slug' => 'full-stack-developer', 'name_ar' => 'مطور Full Stack', 'name_en' => 'Full Stack Developer', 'category' => 'software', 'keywords' => ['fullstack', 'javascript', 'api', 'frontend', 'backend']],
            ['slug' => 'devops-engineer', 'name_ar' => 'مهندس DevOps', 'name_en' => 'DevOps Engineer', 'category' => 'software', 'keywords' => ['devops', 'ci/cd', 'cloud', 'docker', 'kubernetes']],
            ['slug' => 'qa-engineer', 'name_ar' => 'مهندس ضمان جودة', 'name_en' => 'QA Engineer', 'category' => 'software', 'keywords' => ['qa', 'testing', 'automation', 'selenium', 'quality']],
            ['slug' => 'android-developer', 'name_ar' => 'مطور أندرويد', 'name_en' => 'Android Developer', 'category' => 'software', 'keywords' => ['android', 'kotlin', 'java', 'mobile', 'app']],
            ['slug' => 'ios-developer', 'name_ar' => 'مطور iOS', 'name_en' => 'iOS Developer', 'category' => 'software', 'keywords' => ['ios', 'swift', 'mobile', 'app', 'xcode']],
            ['slug' => 'cybersecurity-specialist', 'name_ar' => 'أخصائي أمن سيبراني', 'name_en' => 'Cybersecurity Specialist', 'category' => 'software', 'keywords' => ['security', 'cybersecurity', 'soc', 'penetration', 'network']],
            ['slug' => 'systems-administrator', 'name_ar' => 'مسؤول أنظمة', 'name_en' => 'Systems Administrator', 'category' => 'software', 'keywords' => ['sysadmin', 'linux', 'windows', 'servers', 'network']],

            // marketing (10)
            ['slug' => 'digital-marketing-specialist', 'name_ar' => 'أخصائي تسويق رقمي', 'name_en' => 'Digital Marketing Specialist', 'category' => 'marketing', 'keywords' => ['marketing', 'digital', 'seo', 'sem', 'campaigns']],
            ['slug' => 'social-media-specialist', 'name_ar' => 'أخصائي وسائل تواصل', 'name_en' => 'Social Media Specialist', 'category' => 'marketing', 'keywords' => ['social media', 'content', 'community', 'instagram', 'campaign']],
            ['slug' => 'seo-specialist', 'name_ar' => 'أخصائي تحسين محركات البحث', 'name_en' => 'SEO Specialist', 'category' => 'marketing', 'keywords' => ['seo', 'search', 'keywords', 'analytics', 'content']],
            ['slug' => 'content-creator', 'name_ar' => 'صانع محتوى', 'name_en' => 'Content Creator', 'category' => 'marketing', 'keywords' => ['content', 'writing', 'brand', 'social media', 'copywriting']],
            ['slug' => 'brand-manager', 'name_ar' => 'مدير علامة تجارية', 'name_en' => 'Brand Manager', 'category' => 'marketing', 'keywords' => ['brand', 'marketing', 'strategy', 'positioning', 'campaign']],
            ['slug' => 'performance-marketer', 'name_ar' => 'مسوّق أداء', 'name_en' => 'Performance Marketer', 'category' => 'marketing', 'keywords' => ['ppc', 'google ads', 'meta ads', 'roi', 'analytics']],
            ['slug' => 'marketing-manager', 'name_ar' => 'مدير تسويق', 'name_en' => 'Marketing Manager', 'category' => 'marketing', 'keywords' => ['marketing', 'strategy', 'campaign', 'team', 'budget']],
            ['slug' => 'pr-specialist', 'name_ar' => 'أخصائي علاقات عامة', 'name_en' => 'PR Specialist', 'category' => 'marketing', 'keywords' => ['pr', 'communications', 'media', 'brand', 'reputation']],
            ['slug' => 'email-marketing-specialist', 'name_ar' => 'أخصائي تسويق بالبريد', 'name_en' => 'Email Marketing Specialist', 'category' => 'marketing', 'keywords' => ['email', 'crm', 'campaign', 'automation', 'nurture']],
            ['slug' => 'growth-marketer', 'name_ar' => 'مسوّق نمو', 'name_en' => 'Growth Marketer', 'category' => 'marketing', 'keywords' => ['growth', 'acquisition', 'funnel', 'analytics', 'experimentation']],

            // sales (10)
            ['slug' => 'sales-representative', 'name_ar' => 'مندوب مبيعات', 'name_en' => 'Sales Representative', 'category' => 'sales', 'keywords' => ['sales', 'quota', 'prospecting', 'crm', 'negotiation']],
            ['slug' => 'account-manager', 'name_ar' => 'مدير حسابات', 'name_en' => 'Account Manager', 'category' => 'sales', 'keywords' => ['account management', 'crm', 'retention', 'revenue', 'clients']],
            ['slug' => 'business-development-manager', 'name_ar' => 'مدير تطوير أعمال', 'name_en' => 'Business Development Manager', 'category' => 'sales', 'keywords' => ['business development', 'partnerships', 'pipeline', 'sales', 'negotiation']],
            ['slug' => 'sales-manager', 'name_ar' => 'مدير مبيعات', 'name_en' => 'Sales Manager', 'category' => 'sales', 'keywords' => ['sales', 'team', 'quota', 'pipeline', 'leadership']],
            ['slug' => 'key-account-manager', 'name_ar' => 'مدير حسابات رئيسية', 'name_en' => 'Key Account Manager', 'category' => 'sales', 'keywords' => ['key accounts', 'enterprise', 'revenue', 'crm', 'relationship']],
            ['slug' => 'inside-sales', 'name_ar' => 'مبيعات داخلية', 'name_en' => 'Inside Sales Executive', 'category' => 'sales', 'keywords' => ['inside sales', 'telesales', 'crm', 'leads', 'pipeline']],
            ['slug' => 'retail-sales-associate', 'name_ar' => 'بائع تجزئة', 'name_en' => 'Retail Sales Associate', 'category' => 'sales', 'keywords' => ['retail', 'sales', 'customer service', 'pos', 'upsell']],
            ['slug' => 'real-estate-agent', 'name_ar' => 'وسيط عقاري', 'name_en' => 'Real Estate Agent', 'category' => 'sales', 'keywords' => ['real estate', 'sales', 'negotiation', 'clients', 'property']],
            ['slug' => 'channel-sales-manager', 'name_ar' => 'مدير مبيعات قنوات', 'name_en' => 'Channel Sales Manager', 'category' => 'sales', 'keywords' => ['channel', 'partners', 'sales', 'pipeline', 'revenue']],
            ['slug' => 'pre-sales-consultant', 'name_ar' => 'استشاري ما قبل البيع', 'name_en' => 'Pre-Sales Consultant', 'category' => 'sales', 'keywords' => ['presales', 'demo', 'solution', 'rfps', 'sales']],

            // management (10)
            ['slug' => 'project-manager', 'name_ar' => 'مدير مشاريع', 'name_en' => 'Project Manager', 'category' => 'management', 'keywords' => ['project management', 'planning', 'stakeholders', 'roadmap', 'agile']],
            ['slug' => 'product-manager', 'name_ar' => 'مدير منتج', 'name_en' => 'Product Manager', 'category' => 'management', 'keywords' => ['product', 'roadmap', 'stakeholders', 'strategy', 'backlog']],
            ['slug' => 'operations-manager', 'name_ar' => 'مدير عمليات', 'name_en' => 'Operations Manager', 'category' => 'management', 'keywords' => ['operations', 'process', 'efficiency', 'team', 'planning']],
            ['slug' => 'general-manager', 'name_ar' => 'مدير عام', 'name_en' => 'General Manager', 'category' => 'management', 'keywords' => ['leadership', 'strategy', 'budget', 'operations', 'p&l']],
            ['slug' => 'office-manager', 'name_ar' => 'مدير مكتب', 'name_en' => 'Office Manager', 'category' => 'management', 'keywords' => ['office', 'administration', 'coordination', 'vendors', 'team']],
            ['slug' => 'program-manager', 'name_ar' => 'مدير برامج', 'name_en' => 'Program Manager', 'category' => 'management', 'keywords' => ['program', 'portfolio', 'stakeholders', 'planning', 'delivery']],
            ['slug' => 'team-lead', 'name_ar' => 'قائد فريق', 'name_en' => 'Team Lead', 'category' => 'management', 'keywords' => ['leadership', 'team', 'coaching', 'delivery', 'planning']],
            ['slug' => 'strategy-manager', 'name_ar' => 'مدير استراتيجية', 'name_en' => 'Strategy Manager', 'category' => 'management', 'keywords' => ['strategy', 'planning', 'analysis', 'stakeholders', 'roadmap']],
            ['slug' => 'customer-success-manager', 'name_ar' => 'مدير نجاح العملاء', 'name_en' => 'Customer Success Manager', 'category' => 'management', 'keywords' => ['customer success', 'retention', 'onboarding', 'accounts', 'nps']],
            ['slug' => 'scrum-master', 'name_ar' => 'سكرم ماستر', 'name_en' => 'Scrum Master', 'category' => 'management', 'keywords' => ['scrum', 'agile', 'facilitation', 'team', 'ceremony']],

            // finance (8)
            ['slug' => 'accountant', 'name_ar' => 'محاسب', 'name_en' => 'Accountant', 'category' => 'finance', 'keywords' => ['accounting', 'bookkeeping', 'excel', 'audit', 'ledger']],
            ['slug' => 'financial-analyst', 'name_ar' => 'محلل مالي', 'name_en' => 'Financial Analyst', 'category' => 'finance', 'keywords' => ['financial analysis', 'forecast', 'excel', 'budget', 'reporting']],
            ['slug' => 'finance-manager', 'name_ar' => 'مدير مالي', 'name_en' => 'Finance Manager', 'category' => 'finance', 'keywords' => ['finance', 'budget', 'p&l', 'forecast', 'team']],
            ['slug' => 'auditor', 'name_ar' => 'مدقق', 'name_en' => 'Auditor', 'category' => 'finance', 'keywords' => ['audit', 'compliance', 'controls', 'risk', 'accounting']],
            ['slug' => 'treasury-specialist', 'name_ar' => 'أخصائي خزينة', 'name_en' => 'Treasury Specialist', 'category' => 'finance', 'keywords' => ['treasury', 'cash flow', 'banking', 'liquidity', 'finance']],
            ['slug' => 'credit-analyst', 'name_ar' => 'محلل ائتمان', 'name_en' => 'Credit Analyst', 'category' => 'finance', 'keywords' => ['credit', 'risk', 'analysis', 'banking', 'underwriting']],
            ['slug' => 'payroll-specialist', 'name_ar' => 'أخصائي رواتب', 'name_en' => 'Payroll Specialist', 'category' => 'finance', 'keywords' => ['payroll', 'compensation', 'accounting', 'compliance', 'hris']],
            ['slug' => 'investment-analyst', 'name_ar' => 'محلل استثمار', 'name_en' => 'Investment Analyst', 'category' => 'finance', 'keywords' => ['investment', 'portfolio', 'valuation', 'equity', 'research']],

            // data (8)
            ['slug' => 'data-analyst', 'name_ar' => 'محلل بيانات', 'name_en' => 'Data Analyst', 'category' => 'data', 'keywords' => ['sql', 'excel', 'dashboard', 'analytics', 'reporting']],
            ['slug' => 'data-scientist', 'name_ar' => 'عالم بيانات', 'name_en' => 'Data Scientist', 'category' => 'data', 'keywords' => ['machine learning', 'python', 'statistics', 'modeling', 'data analysis']],
            ['slug' => 'business-intelligence-analyst', 'name_ar' => 'محلل ذكاء أعمال', 'name_en' => 'BI Analyst', 'category' => 'data', 'keywords' => ['power bi', 'tableau', 'dashboard', 'sql', 'reporting']],
            ['slug' => 'data-engineer', 'name_ar' => 'مهندس بيانات', 'name_en' => 'Data Engineer', 'category' => 'data', 'keywords' => ['etl', 'pipelines', 'sql', 'python', 'warehouse']],
            ['slug' => 'reporting-analyst', 'name_ar' => 'محلل تقارير', 'name_en' => 'Reporting Analyst', 'category' => 'data', 'keywords' => ['reporting', 'excel', 'sql', 'dashboard', 'kpi']],
            ['slug' => 'analytics-manager', 'name_ar' => 'مدير تحليلات', 'name_en' => 'Analytics Manager', 'category' => 'data', 'keywords' => ['analytics', 'team', 'insights', 'dashboard', 'strategy']],
            ['slug' => 'ml-engineer', 'name_ar' => 'مهندس تعلم آلي', 'name_en' => 'ML Engineer', 'category' => 'data', 'keywords' => ['machine learning', 'python', 'models', 'mlops', 'data']],
            ['slug' => 'research-analyst', 'name_ar' => 'محلل أبحاث', 'name_en' => 'Research Analyst', 'category' => 'data', 'keywords' => ['research', 'analysis', 'excel', 'reporting', 'insights']],

            // hr (7)
            ['slug' => 'hr-specialist', 'name_ar' => 'أخصائي موارد بشرية', 'name_en' => 'HR Specialist', 'category' => 'hr', 'keywords' => ['hr', 'policies', 'employee relations', 'onboarding', 'hris']],
            ['slug' => 'recruiter', 'name_ar' => 'مسؤول توظيف', 'name_en' => 'Recruiter', 'category' => 'hr', 'keywords' => ['recruitment', 'talent acquisition', 'sourcing', 'interviews', 'hiring']],
            ['slug' => 'hr-manager', 'name_ar' => 'مدير موارد بشرية', 'name_en' => 'HR Manager', 'category' => 'hr', 'keywords' => ['hr', 'leadership', 'policies', 'performance management', 'team']],
            ['slug' => 'talent-acquisition-specialist', 'name_ar' => 'أخصائي استقطاب مواهب', 'name_en' => 'Talent Acquisition Specialist', 'category' => 'hr', 'keywords' => ['talent acquisition', 'recruitment', 'employer branding', 'sourcing', 'hiring']],
            ['slug' => 'compensation-benefits-specialist', 'name_ar' => 'أخصائي تعويضات ومزايا', 'name_en' => 'Compensation & Benefits Specialist', 'category' => 'hr', 'keywords' => ['compensation', 'benefits', 'payroll', 'grading', 'hr']],
            ['slug' => 'learning-development-specialist', 'name_ar' => 'أخصائي تعلم وتطوير', 'name_en' => 'L&D Specialist', 'category' => 'hr', 'keywords' => ['learning', 'training', 'development', 'onboarding', 'performance']],
            ['slug' => 'hrbp', 'name_ar' => 'شريك أعمال موارد بشرية', 'name_en' => 'HR Business Partner', 'category' => 'hr', 'keywords' => ['hrbp', 'employee relations', 'strategy', 'performance management', 'coaching']],

            // ecommerce (7)
            ['slug' => 'ecommerce-manager', 'name_ar' => 'مدير تجارة إلكترونية', 'name_en' => 'E-commerce Manager', 'category' => 'ecommerce', 'keywords' => ['ecommerce', 'marketplace', 'conversion', 'catalog', 'campaign']],
            ['slug' => 'ecommerce-specialist', 'name_ar' => 'أخصائي تجارة إلكترونية', 'name_en' => 'E-commerce Specialist', 'category' => 'ecommerce', 'keywords' => ['ecommerce', 'shopify', 'product listing', 'cart', 'orders']],
            ['slug' => 'marketplace-specialist', 'name_ar' => 'أخصائي أسواق إلكترونية', 'name_en' => 'Marketplace Specialist', 'category' => 'ecommerce', 'keywords' => ['amazon', 'marketplace', 'listing', 'retail', 'campaign']],
            ['slug' => 'product-listing-specialist', 'name_ar' => 'أخصائي إدراج منتجات', 'name_en' => 'Product Listing Specialist', 'category' => 'ecommerce', 'keywords' => ['product listing', 'catalog', 'seo', 'marketplace', 'content']],
            ['slug' => 'shopify-developer', 'name_ar' => 'مطور Shopify', 'name_en' => 'Shopify Developer', 'category' => 'ecommerce', 'keywords' => ['shopify', 'ecommerce', 'liquid', 'storefront', 'api']],
            ['slug' => 'conversion-rate-optimizer', 'name_ar' => 'أخصائي تحسين التحويل', 'name_en' => 'CRO Specialist', 'category' => 'ecommerce', 'keywords' => ['conversion', 'ab testing', 'ux', 'analytics', 'funnel']],
            ['slug' => 'catalog-manager', 'name_ar' => 'مدير كتالوج', 'name_en' => 'Catalog Manager', 'category' => 'ecommerce', 'keywords' => ['catalog', 'product listing', 'pim', 'retail', 'marketplace']],

            // Catch-all (last)
            [
                'slug' => 'other',
                'name_ar' => 'أخرى',
                'name_en' => 'Other',
                'category' => 'management',
                'keywords' => ['other', 'custom', 'general'],
                'sort_order' => 999,
            ],
        ];
    }
}
