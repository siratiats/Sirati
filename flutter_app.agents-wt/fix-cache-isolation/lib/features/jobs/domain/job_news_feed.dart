import 'package:sirati/shared/models/job_news.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';

/// Match scores at or above this are shown with the "high match" style.
const highMatchThreshold = 80;

bool isHighMatch(int? score) => (score ?? 0) >= highMatchThreshold;

/// What the server knows about the viewer's profile, used to explain and
/// toggle the "matched to you" preset.
class JobNewsProfileContext {
  const JobNewsProfileContext({
    required this.hasProfile,
    this.careerField = '',
    this.city = '',
  });

  final bool hasProfile;
  final String careerField;
  final String city;

  /// The API sends `job_title_name` and `location`. The banner originally
  /// read `career_field` / `city`, keys the API never sends, so it could
  /// never show the user's field. Both spellings are accepted.
  factory JobNewsProfileContext.fromJson(Map<String, dynamic> json) {
    String first(List<String> keys) {
      for (final key in keys) {
        final value = json[key]?.toString().trim() ?? '';
        if (value.isNotEmpty) return value;
      }
      return '';
    }

    return JobNewsProfileContext(
      hasProfile: json['has_profile'] == true,
      careerField: first(const ['job_title_name', 'career_field']),
      city: first(const ['location', 'city']),
    );
  }
}

/// Parsed `/mobile/job-news` payload.
class JobNewsFeed {
  const JobNewsFeed({
    required this.title,
    required this.items,
    this.profile,
    this.offline = false,
  });

  final String title;
  final List<JobNews> items;
  final JobNewsProfileContext? profile;

  /// True when served from the offline cache.
  final bool offline;

  /// Highlighted first item, if any.
  JobNews? get featured => items.isEmpty ? null : items.first;

  /// Everything after the featured item. With a single result this is empty:
  /// the old code fell back to the full list, which showed that one job
  /// twice (as the featured card and again under "Latest").
  List<JobNews> get latest => items.length <= 1 ? const [] : items.sublist(1);

  factory JobNewsFeed.fromPayload(
    Map<String, dynamic> data, {
    required bool english,
  }) {
    final rawItems = data['items'];
    final rawProfile = data['profile_context'];
    final title = data['title']?.toString().trim() ?? '';
    return JobNewsFeed(
      title: title.isNotEmpty ? title : _defaultTitle(english),
      // Skip malformed entries instead of rendering a blank id-0 job.
      items: rawItems is List
          ? rawItems
              .whereType<Map<String, dynamic>>()
              .map(JobNews.fromJson)
              .toList(growable: false)
          : const [],
      profile: rawProfile is Map<String, dynamic>
          ? JobNewsProfileContext.fromJson(rawProfile)
          : null,
      offline: MobileContentService.isOfflinePayload(data),
    );
  }

  /// Shown when the first load fails and nothing is cached.
  factory JobNewsFeed.empty({required bool english}) =>
      JobNewsFeed(title: _defaultTitle(english), items: const []);

  static String _defaultTitle(bool english) =>
      english ? 'Job News' : 'أخبار الوظائف';
}

String _normalizeSkill(String s) =>
    s.trim().toLowerCase().replaceAll(RegExp(r'\s+'), ' ');

/// Whether [skill] is covered by one of the user's [matchedSkills].
///
/// Case- and spacing-insensitive, and a matched phrase counts inside a longer
/// skill only as whole words: "React" covers "React Native", but "Java" does
/// not cover "JavaScript". Blank entries never match (a blank used to match
/// every skill through `contains('')`).
bool isSkillMatched(String skill, Iterable<String> matchedSkills) {
  final target = _normalizeSkill(skill);
  if (target.isEmpty) return false;
  for (final raw in matchedSkills) {
    final m = _normalizeSkill(raw);
    if (m.isEmpty) continue;
    if (m == target) return true;
    // Raw strings: `\p{…}` must reach the regex engine unescaped.
    final wholeWord = RegExp(
      r'(^|[^\p{L}\p{N}])' + RegExp.escape(m) + r'($|[^\p{L}\p{N}])',
      unicode: true,
    );
    if (wholeWord.hasMatch(target)) return true;
  }
  return false;
}

/// Match score limited to 0–100 for display; null stays null.
int? displayMatchScore(int? score) => score?.clamp(0, 100);
