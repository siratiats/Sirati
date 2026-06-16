<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصاً صحيحاً.',
    'email' => 'يرجى إدخال بريد إلكتروني صحيح.',
    'file' => 'يرجى رفع ملف صحيح في حقل :attribute.',
    'mimes' => 'يجب أن يكون :attribute من نوع: :values.',
    'max' => [
        'string' => 'يجب ألا يتجاوز :attribute :max حرفاً.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تكون قيمة :attribute أكبر من :max.',
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عناصر.',
    ],
    'min' => [
        'string' => 'يجب ألا يقل :attribute عن :min حرفاً.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا تكون قيمة :attribute أقل من :min.',
        'array' => 'يجب أن يحتوي :attribute على :min عناصر على الأقل.',
    ],
    'in' => 'القيمة المختارة في :attribute غير صحيحة.',

    'attributes' => [
        'full_name' => 'الاسم الكامل',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الجوال',
        'role_interest' => 'نوع الاهتمام',
        'target_job_title' => 'المسمى الوظيفي المستهدف',
        'notes' => 'الملاحظات',
        'resume_text' => 'نص السيرة الذاتية',
        'resume_file' => 'ملف السيرة الذاتية',
        'linkedin' => 'حساب لينكدإن',
        'location' => 'المدينة أو الدولة',
        'language' => 'لغة السيرة',
        'summary_input' => 'الملخص المهني',
        'skills_input' => 'المهارات الأساسية',
        'experience_input' => 'الخبرات العملية والإنجازات',
        'education_input' => 'التعليم',
        'certifications_input' => 'الشهادات والدورات',
    ],
];
