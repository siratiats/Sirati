import 'package:flutter/material.dart';

import 'package:sirati/core/network/api_config.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/language_toggle.dart';

class PrivacyPolicyScreen extends StatelessWidget {
  const PrivacyPolicyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(english ? 'Privacy Policy' : 'سياسة الخصوصية'),
        actions: const [
          Padding(
            padding: EdgeInsetsDirectional.only(end: 12),
            child: LanguageToggle(),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
        children: [
          _Section(
            title: english ? 'What We Collect' : 'ما البيانات التي نجمعها؟',
            body: english
                ? 'We collect account details (name, email, optional phone and location), the CV content you enter or upload, analysis results, subscription entitlement metadata, and a device push-notification token (FCM) so Sirati can analyze, improve, save, and notify you about your CVs.'
                : 'نجمع بيانات الحساب (الاسم، البريد، والجوال والموقع اختياريًا)، ومحتوى السيرة الذي تدخله أو ترفعه، ونتائج التحليل، وبيانات صلاحية الاشتراك، ورمز إشعارات الجهاز (FCM) لنتمكن من تحليل سيرتك وتحسينها وحفظها وإرسال الإشعارات.',
          ),
          _Section(
            title: english ? 'How We Use It' : 'كيف نستخدم البيانات؟',
            body: english
                ? 'Your data is used to calculate ATS scores, generate improved CVs, show your history, personalize the dashboard, and deliver optional push notifications. We never sell your data or use it for third-party advertising tracking.'
                : 'نستخدم بياناتك لحساب درجة ATS، وإنشاء سير محسنة، وعرض السجل، وتخصيص لوحة التحكم، وإرسال إشعارات اختيارية. لا نبيع بياناتك الشخصية ولا نستخدمها لتتبع الإعلانات.',
          ),
          _Section(
            title: english
                ? 'Third-Party Sub-Processors'
                : 'معالجو البيانات من الأطراف الثالثة',
            body: english
                ? 'To deliver secure AI generation and platform features, we use trusted sub-processors:\n• OpenAI & DeepInfra: AI resume analysis and enhancement.\n• RevenueCat: In-app purchase verification and subscription management.\n• Firebase (Google): Push notification delivery.\n• Sentry: Crash reporting and error diagnostics with PII scrubbing.'
                : 'لتقديم خدمات الذكاء الاصطناعي وإدارة المنصة، نتعامل مع مزودي خدمات موثوقين:\n• OpenAI و DeepInfra: معالجة نصوص السيرة وتحليلها بالذكاء الاصطناعي.\n• RevenueCat: إدارة وتأكيد صلاحيات الاشتراكات داخل التطبيق.\n• Firebase (Google): إرسال إشعارات الهاتف.\n• Sentry: تتبع الأعطال التقنية مع حجب وحذف أي بيانات شخصية أو نصوص للسير.',
          ),
          _Section(
            title: english
                ? 'Data Retention & Deletion'
                : 'الاحتفاظ بالبيانات وحذفها',
            body: english
                ? 'Your data is retained while your account is active. You can delete individual generated CVs from My CVs. You can permanently delete your entire account and all associated data at any time from Settings → Delete account. Transient guest web analyses are automatically pruned within 24 hours.'
                : 'نحتفظ ببياناتك طوال فترة نشاط حسابك. يمكنك حذف السير الفردية من شاشة سيراتي. ويمكنك حذف حسابك بالكامل وجميع بياناتك نهائياً في أي وقت من الإعدادات ← حذف الحساب. كما تُحذف التحليلات المؤقتة لزوار الويب تلقائياً خلال 24 ساعة.',
          ),
          _Section(
            title: english ? 'Contact & Support' : 'التواصل والدعم',
            body: english
                ? 'For any privacy inquiries or to exercise your rights under the Saudi Personal Data Protection Law (PDPL), contact us at: ${ApiConfig.supportEmail}'
                : 'لأي استفسارات تتعلق بالخصوصية أو ممارسة حقوقك بموجب نظام حماية البيانات الشخصية (PDPL)، يمكنك التواصل معنا عبر: ${ApiConfig.supportEmail}',
          ),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  final String title;
  final String body;

  const _Section({required this.title, required this.body});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: context.sirati.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: context.sirati.border.withValues(alpha: .7)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(
            header: true,
            child: Text(
              title,
              textAlign: TextAlign.start,
              style: TextStyle(
                fontSize: 18,
                height: 1.3,
                fontWeight: FontWeight.w800,
                // 18px bold is below the large-text size: needs 4.5:1.
                color: context.sirati.primaryInk,
              ),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            body,
            textAlign: TextAlign.start,
            style: TextStyle(
              fontSize: 15,
              height: 1.7,
              color: context.sirati.textPrimary,
            ),
          ),
        ],
      ),
    );
  }
}
