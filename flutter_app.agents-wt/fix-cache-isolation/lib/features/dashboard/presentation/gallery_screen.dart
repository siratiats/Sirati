import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/theme/app_theme_controller.dart';
import 'package:sirati/shared/widgets/components.dart';

/// Debug-only Component Gallery Screen (SIRATI-23).
///
/// Enables rapid visual verification of design tokens, interactive components,
/// light/dark modes, and RTL/LTR layout transitions.
class ComponentGalleryScreen extends StatefulWidget {
  const ComponentGalleryScreen({super.key});

  static const routeName = '/gallery';

  @override
  State<ComponentGalleryScreen> createState() => _ComponentGalleryScreenState();
}

class _ComponentGalleryScreenState extends State<ComponentGalleryScreen> {
  final _inputController =
      TextEditingController(text: 'نص تجريبي / Sample text');
  bool _isLoadingButton = false;

  @override
  void dispose() {
    _inputController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Gallery should only be active in debug mode
    assert(
        kDebugMode, 'ComponentGalleryScreen must only be used in debug builds');

    final c = context.sirati;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isEn = AppLocale.isEnglish(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Component Gallery (Debug)'),
        actions: [
          // Theme Switcher Toggle
          IconButton(
            tooltip: isDark ? 'Switch to Light' : 'Switch to Dark',
            icon: Icon(isDark ? Icons.light_mode : Icons.dark_mode),
            onPressed: () {
              AppThemeController.setMode(
                isDark ? ThemeMode.light : ThemeMode.dark,
              );
            },
          ),
          // Locale Switcher Toggle
          TextButton(
            onPressed: () => AppLocale.toggle(context),
            child: Text(
              isEn ? 'عربي' : 'EN',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: c.primary,
              ),
            ),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.md),
        children: [
          _buildSectionHeader('1. Buttons (AppButton)', c.primary),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              AppButton(
                label: 'Primary Button',
                expand: false,
                onPressed: () {},
              ),
              AppButton.secondary(
                label: 'Secondary Button',
                expand: false,
                onPressed: () {},
              ),
              AppButton.text(
                label: 'Text Button',
                expand: false,
                onPressed: () {},
              ),
              AppButton.destructive(
                label: 'Destructive',
                expand: false,
                onPressed: () {},
              ),
              AppButton(
                label: 'Loading State',
                isLoading: _isLoadingButton,
                expand: false,
                onPressed: () {
                  setState(() => _isLoadingButton = !_isLoadingButton);
                },
              ),
              const AppButton(
                label: 'Disabled',
                expand: false,
                onPressed: null,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          _buildSectionHeader('2. Text Fields (AppInput)', c.primary),
          AppInput(
            label: 'Full Name / الاسم الكامل',
            controller: _inputController,
            hint: 'أدخل الاسم...',
          ),
          const SizedBox(height: AppSpacing.md),
          const AppInput(
            label: 'Input Field',
            hint: 'someone@example.com',
          ),
          const SizedBox(height: AppSpacing.lg),
          _buildSectionHeader('3. Surface Cards (AppSurfaceCard)', c.primary),
          AppSurfaceCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Default Surface Card',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  'Standard elevated card using semantic theme tokens.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          AppSurfaceCard(
            onTap: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Card tapped!')),
              );
            },
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Interactive Tappable Card',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const DirectionalIcon(Icons.chevron_right),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          _buildSectionHeader('4. Dialogs and Sheets', c.primary),
          Row(
            children: [
              Expanded(
                child: AppButton.secondary(
                  label: 'Open Dialog',
                  onPressed: () {
                    showAppDialog(
                      context: context,
                      title: 'تأكيد الحذف',
                      body:
                          'هل أنت متأكد من حذف هذا القسم؟ لا يمكن التراجع عن هذه العملية.',
                      confirmLabel: 'حذف',
                      cancelLabel: 'إلغاء',
                      destructive: true,
                    );
                  },
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: AppButton.secondary(
                  label: 'Open Sheet',
                  onPressed: () {
                    showAppBottomSheet(
                      context: context,
                      semanticLabel: 'خيارات التصدير',
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          ListTile(
                            leading: const Icon(Icons.picture_as_pdf),
                            title: const Text('تصدير كملف PDF'),
                            onTap: () => Navigator.pop(context),
                          ),
                          ListTile(
                            leading: const Icon(Icons.share),
                            title: const Text('مشاركة السيرة الذاتية'),
                            onTap: () => Navigator.pop(context),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          _buildSectionHeader('5. Empty and Loading States', c.primary),
          const AppSurfaceCard(
            child: AppEmptyState(
              icon: Icons.description_outlined,
              title: 'لا توجد سير ذاتية حتى الآن',
              subtitle:
                  'ابدأ بإنشاء أول سيرة ذاتية احترافية متوافقة مع أنظمة ATS.',
              scrollable: false,
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          const Center(
            child: BrandedLoader(),
          ),
          const SizedBox(height: AppSpacing.xl),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String title, Color color) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Text(
        title,
        style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.bold,
              color: color,
            ),
      ),
    );
  }
}
