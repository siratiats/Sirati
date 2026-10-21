import 'package:flutter/material.dart';
import 'package:sirati/features/jobs/domain/job_news_feed.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

class ProfileContextBanner extends StatelessWidget {
  final bool english;
  final JobNewsProfileContext profile;
  final bool isMatchedActive;
  final VoidCallback onToggleMatched;

  const ProfileContextBanner({
    super.key,
    required this.english,
    required this.profile,
    required this.isMatchedActive,
    required this.onToggleMatched,
  });

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final careerField = profile.careerField;
    final city = profile.city;
    final hasCareer = careerField.isNotEmpty;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: context.sirati.primaryLight.withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: context.sirati.primary.withValues(alpha: 0.25),
        ),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: context.sirati.primary,
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.auto_awesome_rounded,
              color: c.onPrimary,
              size: 18,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  english ? 'Personalized For You' : 'فرص مهنية مخصصة لك',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: context.sirati.primaryDark,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  hasCareer
                      ? '$careerField${city.isNotEmpty ? ' · $city' : ''}'
                      : (english
                          ? 'Showing jobs tailored to your background.'
                          : 'عرض وظائف متوافقة مع مهاراتك وخبراتك.'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 11.5,
                    color: context.sirati.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          PressScale(
            child: Material(
              // brandSurface keeps white text at AA in dark mode too, where
              // primaryDark is too light for it.
              color: isMatchedActive ? c.brandSurface : null,
              type: isMatchedActive
                  ? MaterialType.canvas
                  : MaterialType.transparency,
              shape: StadiumBorder(
                side: isMatchedActive
                    ? BorderSide.none
                    : BorderSide(color: c.primaryDark, width: 1.2),
              ),
              child: InkWell(
                onTap: onToggleMatched,
                customBorder: const StadiumBorder(),
                child: Padding(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (isMatchedActive) ...[
                        Icon(
                          Icons.check_rounded,
                          size: 13,
                          color: c.onBrandSurface,
                        ),
                        const SizedBox(width: 4),
                      ],
                      Text(
                        isMatchedActive
                            ? (english ? 'Matched ✓' : 'المطابقة ✓')
                            : (english ? 'Match 🎯' : 'المطابقة 🎯'),
                        style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: isMatchedActive
                              ? c.onBrandSurface
                              : c.primaryDark,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
