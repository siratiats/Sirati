<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use App\Services\AtsScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
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

        // Keep mobile taxonomy endpoint in sync right after seeding.
        Cache::forget('mobile.job_titles.active.v1');
    }

    /**
     * 123 MENA/Gulf-oriented titles + "other". Arabic primary, English secondary.
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
            ['slug' => 'software-engineer', 'name_ar' => 'مهندس برمجيات', 'name_en' => 'Software Engineer', 'category' => 'software', 'keywords' => ['software', 'software engineering', 'programming', 'backend', 'frontend', 'api', 'مطور برمجيات']],
            ['slug' => 'laravel-developer', 'name_ar' => 'مطور Laravel', 'name_en' => 'Laravel Developer', 'category' => 'software', 'keywords' => ['laravel', 'php', 'backend', 'api', 'sql', 'لارافيل', 'لارفيل']],
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
            ['slug' => 'data-engineer', 'name_ar' => 'مهندس بيانات', 'name_en' => 'Data Engineer', 'category' => 'data', 'keywords' => ['etl', 'pipelines', 'sql', 'python', 'data warehouse']],
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

            // tech additions (9)
            ['slug' => 'cloud-engineer', 'name_ar' => 'مهندس حوسبة سحابية', 'name_en' => 'Cloud Engineer', 'category' => 'software', 'keywords' => ['cloud infrastructure', 'amazon web services', 'azure', 'google cloud', 'terraform', 'kubernetes']],
            ['slug' => 'network-engineer', 'name_ar' => 'مهندس شبكات', 'name_en' => 'Network Engineer', 'category' => 'software', 'keywords' => ['network infrastructure', 'cisco', 'ccna', 'routing', 'firewall', 'lan/wan']],
            ['slug' => 'it-support-specialist', 'name_ar' => 'أخصائي دعم فني', 'name_en' => 'IT Support Specialist', 'category' => 'software', 'keywords' => ['it support', 'helpdesk', 'troubleshooting', 'active directory', 'ticketing system']],
            ['slug' => 'database-administrator', 'name_ar' => 'مسؤول قواعد بيانات', 'name_en' => 'Database Administrator', 'category' => 'software', 'keywords' => ['database administration', 'oracle', 'sql server', 'database backup', 'performance tuning']],
            ['slug' => 'ui-ux-designer', 'name_ar' => 'مصمم واجهات وتجربة مستخدم', 'name_en' => 'UI/UX Designer', 'category' => 'software', 'keywords' => ['ui/ux', 'figma', 'user research', 'wireframes', 'prototyping']],
            ['slug' => 'ai-engineer', 'name_ar' => 'مهندس ذكاء اصطناعي', 'name_en' => 'AI Engineer', 'category' => 'data', 'keywords' => ['artificial intelligence', 'large language models', 'natural language processing', 'deep learning', 'pytorch']],
            ['slug' => 'solutions-architect', 'name_ar' => 'معماري حلول تقنية', 'name_en' => 'Solutions Architect', 'category' => 'software', 'keywords' => ['solutions architecture', 'system design', 'enterprise architecture', 'cloud architect', 'microservices']],
            ['slug' => 'erp-consultant', 'name_ar' => 'استشاري أنظمة ERP', 'name_en' => 'ERP Consultant', 'category' => 'software', 'keywords' => ['erp systems', 'sap modules', 'oracle ebs', 'odoo', 'erp implementation']],
            ['slug' => 'it-manager', 'name_ar' => 'مدير تقنية المعلومات', 'name_en' => 'IT Manager', 'category' => 'software', 'keywords' => ['it infrastructure', 'it strategy', 'it vendors', 'service desk', 'itil']],

            // pharmacy (12)
            ['slug' => 'community-pharmacist', 'name_ar' => 'صيدلي مجتمع', 'name_en' => 'Community Pharmacist', 'category' => 'pharmacy', 'keywords' => ['pharmacist', 'dispensing', 'retail pharmacy', 'patient counseling', 'otc medicines', 'صيدلي']],
            ['slug' => 'hospital-pharmacist', 'name_ar' => 'صيدلي مستشفى', 'name_en' => 'Hospital Pharmacist', 'category' => 'pharmacy', 'keywords' => ['pharmacist', 'inpatient', 'iv admixture', 'unit dose', 'formulary']],
            ['slug' => 'clinical-pharmacist', 'name_ar' => 'صيدلي إكلينيكي', 'name_en' => 'Clinical Pharmacist', 'category' => 'pharmacy', 'keywords' => ['clinical pharmacy', 'pharmacotherapy', 'drug interactions', 'ward rounds', 'therapeutic drug monitoring']],
            ['slug' => 'pharmacy-technician', 'name_ar' => 'فني صيدلة', 'name_en' => 'Pharmacy Technician', 'category' => 'pharmacy', 'keywords' => ['pharmacy technician', 'dispensing', 'prescriptions', 'stock control', 'medication labeling']],
            ['slug' => 'pharmacy-manager', 'name_ar' => 'مدير صيدلية', 'name_en' => 'Pharmacy Manager', 'category' => 'pharmacy', 'keywords' => ['pharmacy operations', 'pharmacy inventory', 'pharmacist', 'pharmacy compliance', 'staff scheduling']],
            ['slug' => 'medical-representative', 'name_ar' => 'مندوب دعاية طبية', 'name_en' => 'Medical Representative', 'category' => 'pharmacy', 'keywords' => ['medical representative', 'product detailing', 'physicians', 'sales territory', 'pharmaceutical']],
            ['slug' => 'regulatory-affairs-specialist', 'name_ar' => 'أخصائي شؤون تنظيمية', 'name_en' => 'Regulatory Affairs Specialist', 'category' => 'pharmacy', 'keywords' => ['regulatory affairs', 'sfda registration', 'drug registration', 'drug registration dossier', 'dossier']],
            ['slug' => 'pharmacovigilance-specialist', 'name_ar' => 'أخصائي يقظة دوائية', 'name_en' => 'Pharmacovigilance Specialist', 'category' => 'pharmacy', 'keywords' => ['pharmacovigilance', 'adverse events', 'drug safety', 'psur', 'signal detection']],
            ['slug' => 'quality-control-pharmacist', 'name_ar' => 'صيدلي رقابة جودة', 'name_en' => 'Quality Control Pharmacist', 'category' => 'pharmacy', 'keywords' => ['pharmaceutical quality control', 'gmp compliance', 'hplc', 'batch release', 'method validation']],
            ['slug' => 'production-pharmacist', 'name_ar' => 'صيدلي إنتاج', 'name_en' => 'Production Pharmacist', 'category' => 'pharmacy', 'keywords' => ['pharmaceutical manufacturing', 'gmp compliance', 'batch records', 'drug formulation', 'sterile manufacturing']],
            ['slug' => 'drug-information-pharmacist', 'name_ar' => 'صيدلي معلومات دوائية', 'name_en' => 'Drug Information Pharmacist', 'category' => 'pharmacy', 'keywords' => ['drug information', 'medication queries', 'formulary', 'drug information service', 'pharmacist']],
            ['slug' => 'pharmaceutical-sales-manager', 'name_ar' => 'مدير مبيعات أدوية', 'name_en' => 'Pharmaceutical Sales Manager', 'category' => 'pharmacy', 'keywords' => ['pharmaceutical sales', 'sales targets', 'key accounts', 'hospital accounts', 'medical team']],

            // healthcare (15)
            ['slug' => 'general-practitioner', 'name_ar' => 'طبيب عام', 'name_en' => 'General Practitioner', 'category' => 'healthcare', 'keywords' => ['general practice', 'physician', 'diagnosis', 'outpatient', 'primary care', 'طبيب']],
            ['slug' => 'specialist-physician', 'name_ar' => 'طبيب أخصائي', 'name_en' => 'Specialist Physician', 'category' => 'healthcare', 'keywords' => ['specialty clinic', 'physician', 'medical consultation', 'board certified', 'clinical practice']],
            ['slug' => 'dentist', 'name_ar' => 'طبيب أسنان', 'name_en' => 'Dentist', 'category' => 'healthcare', 'keywords' => ['dentist', 'dental', 'oral health', 'restorative', 'endodontics']],
            ['slug' => 'registered-nurse', 'name_ar' => 'ممرض قانوني', 'name_en' => 'Registered Nurse', 'category' => 'healthcare', 'keywords' => ['nursing', 'patient care', 'medication administration', 'bls certified', 'vital signs', 'ممرض', 'تمريض']],
            ['slug' => 'nurse-supervisor', 'name_ar' => 'مشرف تمريض', 'name_en' => 'Nursing Supervisor', 'category' => 'healthcare', 'keywords' => ['nursing supervision', 'nurse staffing', 'nursing administration', 'nursing quality', 'nursing']],
            ['slug' => 'medical-laboratory-specialist', 'name_ar' => 'أخصائي مختبرات طبية', 'name_en' => 'Medical Laboratory Specialist', 'category' => 'healthcare', 'keywords' => ['laboratory', 'hematology', 'microbiology', 'clinical chemistry', 'specimens']],
            ['slug' => 'radiology-technologist', 'name_ar' => 'أخصائي أشعة', 'name_en' => 'Radiology Technologist', 'category' => 'healthcare', 'keywords' => ['radiology', 'x-ray', 'ct scan', 'mri imaging', 'radiation safety']],
            ['slug' => 'physiotherapist', 'name_ar' => 'أخصائي علاج طبيعي', 'name_en' => 'Physiotherapist', 'category' => 'healthcare', 'keywords' => ['physiotherapy', 'rehabilitation', 'musculoskeletal', 'exercise therapy', 'manual therapy']],
            ['slug' => 'clinical-dietitian', 'name_ar' => 'أخصائي تغذية علاجية', 'name_en' => 'Clinical Dietitian', 'category' => 'healthcare', 'keywords' => ['clinical nutrition', 'dietitian', 'meal plans', 'nutrition assessment', 'diabetes nutrition']],
            ['slug' => 'respiratory-therapist', 'name_ar' => 'أخصائي علاج تنفسي', 'name_en' => 'Respiratory Therapist', 'category' => 'healthcare', 'keywords' => ['respiratory therapy', 'ventilator', 'intensive care', 'arterial blood gas', 'oxygen therapy']],
            ['slug' => 'paramedic', 'name_ar' => 'مسعف', 'name_en' => 'Paramedic', 'category' => 'healthcare', 'keywords' => ['paramedic', 'emergency care', 'ambulance', 'acls', 'triage']],
            ['slug' => 'medical-receptionist', 'name_ar' => 'موظف استقبال طبي', 'name_en' => 'Medical Receptionist', 'category' => 'healthcare', 'keywords' => ['patient registration', 'patient appointments', 'medical records', 'hospital information system', 'insurance approvals']],
            ['slug' => 'medical-coder', 'name_ar' => 'أخصائي ترميز طبي', 'name_en' => 'Medical Coding Specialist', 'category' => 'healthcare', 'keywords' => ['medical coding', 'icd-10', 'cpt coding', 'health information', 'insurance claims']],
            ['slug' => 'infection-control-specialist', 'name_ar' => 'أخصائي مكافحة عدوى', 'name_en' => 'Infection Control Specialist', 'category' => 'healthcare', 'keywords' => ['infection control', 'infection surveillance', 'hand hygiene', 'jci accreditation', 'sterilization']],
            ['slug' => 'healthcare-administrator', 'name_ar' => 'إداري رعاية صحية', 'name_en' => 'Healthcare Administrator', 'category' => 'healthcare', 'keywords' => ['healthcare administration', 'hospital operations', 'accreditation', 'patient experience', 'hospital accreditation']],

            // engineering (15)
            ['slug' => 'civil-engineer', 'name_ar' => 'مهندس مدني', 'name_en' => 'Civil Engineer', 'category' => 'engineering', 'keywords' => ['civil engineering', 'construction projects', 'autocad', 'site supervision', 'quantity surveying', 'هندسة مدنية']],
            ['slug' => 'structural-engineer', 'name_ar' => 'مهندس إنشائي', 'name_en' => 'Structural Engineer', 'category' => 'engineering', 'keywords' => ['structural engineering', 'etabs', 'sap2000', 'concrete design', 'steel structures']],
            ['slug' => 'mechanical-engineer', 'name_ar' => 'مهندس ميكانيكي', 'name_en' => 'Mechanical Engineer', 'category' => 'engineering', 'keywords' => ['mechanical engineering', 'solidworks', 'mechanical maintenance', 'pumps', 'thermodynamics', 'مهندس ميكانيكا']],
            ['slug' => 'electrical-engineer', 'name_ar' => 'مهندس كهربائي', 'name_en' => 'Electrical Engineer', 'category' => 'engineering', 'keywords' => ['electrical engineering', 'power distribution', 'etap', 'switchgear', 'low voltage', 'مهندس كهرباء']],
            ['slug' => 'mep-engineer', 'name_ar' => 'مهندس MEP', 'name_en' => 'MEP Engineer', 'category' => 'engineering', 'keywords' => ['mep systems', 'plumbing', 'fire fighting', 'mep coordination', 'shop drawings']],
            ['slug' => 'hvac-engineer', 'name_ar' => 'مهندس تكييف وتبريد', 'name_en' => 'HVAC Engineer', 'category' => 'engineering', 'keywords' => ['hvac', 'chillers', 'load calculation', 'ductwork', 'hvac design']],
            ['slug' => 'chemical-engineer', 'name_ar' => 'مهندس كيميائي', 'name_en' => 'Chemical Engineer', 'category' => 'engineering', 'keywords' => ['chemical engineering', 'process engineering', 'aspen hysys', 'process flow diagrams', 'petrochemical']],
            ['slug' => 'industrial-engineer', 'name_ar' => 'مهندس صناعي', 'name_en' => 'Industrial Engineer', 'category' => 'engineering', 'keywords' => ['industrial engineering', 'lean manufacturing', 'six sigma', 'process improvement', 'production planning']],
            ['slug' => 'petroleum-engineer', 'name_ar' => 'مهندس بترول', 'name_en' => 'Petroleum Engineer', 'category' => 'engineering', 'keywords' => ['petroleum engineering', 'drilling', 'reservoir', 'oil and gas', 'well operations']],
            ['slug' => 'biomedical-engineer', 'name_ar' => 'مهندس أجهزة طبية', 'name_en' => 'Biomedical Engineer', 'category' => 'engineering', 'keywords' => ['biomedical engineering', 'medical equipment', 'medical equipment maintenance', 'equipment calibration', 'clinical engineering']],
            ['slug' => 'architect', 'name_ar' => 'مهندس معماري', 'name_en' => 'Architect', 'category' => 'engineering', 'keywords' => ['architecture', 'revit', 'autocad', 'design development', 'building codes']],
            ['slug' => 'site-engineer', 'name_ar' => 'مهندس موقع', 'name_en' => 'Site Engineer', 'category' => 'engineering', 'keywords' => ['site engineer', 'site supervision', 'subcontractors', 'daily reports', 'construction projects']],
            ['slug' => 'project-engineer', 'name_ar' => 'مهندس مشاريع', 'name_en' => 'Project Engineer', 'category' => 'engineering', 'keywords' => ['project engineering', 'primavera', 'submittals', 'rfi management', 'project scheduling']],
            ['slug' => 'qa-qc-engineer', 'name_ar' => 'مهندس ضبط وتوكيد جودة', 'name_en' => 'QA/QC Engineer', 'category' => 'engineering', 'keywords' => ['qa/qc', 'quality inspection', 'inspection test plans', 'non-conformance reports', 'iso 9001']],
            ['slug' => 'hse-engineer', 'name_ar' => 'مهندس سلامة وصحة مهنية', 'name_en' => 'HSE Engineer', 'category' => 'engineering', 'keywords' => ['hse management', 'nebosh', 'hse risk assessment', 'osha', 'incident investigation']],

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
