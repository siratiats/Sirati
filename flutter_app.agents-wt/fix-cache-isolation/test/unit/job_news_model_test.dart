import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/models/job_news.dart';

void main() {
  group('JobNews Model Invariants & Deserialization', () {
    test('Correctly parses full personalized and verified payload', () {
      final json = {
        'id': 42,
        'title': 'مطور برمجيات ذكاء اصطناعي (AI Software Engineer)',
        'company': 'سدايا SDAIA',
        'location': 'الرياض',
        'body': 'تفاصيل الإعلان ومتطلبات التقديم',
        'category': 'tech',
        'is_featured': true,
        'is_verified': true,
        'source_type': 'gov',
        'employment_type': 'tamheer',
        'experience_level': 'entry',
        'salary_min': 10000,
        'salary_max': 15000,
        'salary_range': '10,000 - 15,000 ر.س',
        'skills_required': ['Python', 'Flutter', 'PyTorch'],
        'match_score': 92,
        'match_reasons': ['مجال الوظيفة يطابق مجالك المهني', 'تطابق في 2 من المهارات المطلوبة'],
        'matched_skills': ['Python', 'Flutter'],
        'missing_skills': ['PyTorch'],
        'source_badge': '🏛️ جهة حكومية معتمدة',
        'action_url': 'https://sdaia.gov.sa/jobs',
        'published_at': '2026-09-12T12:00:00Z',
      };

      final job = JobNews.fromJson(json);

      expect(job.id, 42);
      expect(job.isVerified, isTrue);
      expect(job.sourceType, 'gov');
      expect(job.isGov, isTrue);
      expect(job.isEnterprise, isFalse);
      expect(job.employmentType, 'tamheer');
      expect(job.isTamheer, isTrue);
      expect(job.experienceLevel, 'entry');
      expect(job.salaryMin, 10000);
      expect(job.salaryMax, 15000);
      expect(job.salaryRange, '10,000 - 15,000 ر.س');
      expect(job.skillsRequired, containsAll(['Python', 'Flutter', 'PyTorch']));
      expect(job.matchScore, 92);
      expect(job.matchReasons.length, 2);
      expect(job.matchedSkills, containsAll(['Python', 'Flutter']));
      expect(job.missingSkills, containsAll(['PyTorch']));
      expect(job.sourceBadge, '🏛️ جهة حكومية معتمدة');
    });

    test('Enterprise source type flags isEnterprise correctly', () {
      final json = {
        'id': 101,
        'title': 'مهندس حلول سحابية',
        'is_verified': true,
        'source_type': 'enterprise',
        'employment_type': 'full_time',
      };

      final job = JobNews.fromJson(json);

      expect(job.isVerified, isTrue);
      expect(job.isGov, isFalse);
      expect(job.isEnterprise, isTrue);
      expect(job.isTamheer, isFalse);
    });

    test('Robust handling of nulls and adversarial boundary data', () {
      final json = <String, dynamic>{
        'id': '999', // numeric string ID
        'is_verified': 1, // truthy int instead of bool
        'salary_min': '8000', // string number
        'salary_max': null,
        'skills_required': 'not_a_list', // invalid type gracefully handled
        'match_reasons': null,
        'matched_skills': null,
      };

      final job = JobNews.fromJson(json);

      expect(job.id, 999);
      expect(job.isVerified, isTrue);
      expect(job.salaryMin, 8000);
      expect(job.salaryMax, isNull);
      expect(job.skillsRequired, isEmpty);
      expect(job.matchReasons, isEmpty);
      expect(job.matchedSkills, isEmpty);
      expect(job.missingSkills, isEmpty);
      expect(job.matchScore, isNull);
      expect(job.isGov, isFalse);
      expect(job.isEnterprise, isFalse);
      expect(job.isTamheer, isFalse);
    });
  });
}
