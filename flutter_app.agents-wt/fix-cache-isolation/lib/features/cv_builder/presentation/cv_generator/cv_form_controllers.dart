import 'package:flutter/widgets.dart';
import 'package:sirati/features/cv_builder/domain/cv_form_data.dart';

/// Owns the wizard's text controllers and converts them to and from
/// [CvFormData]. Language is not a text field, so it lives on the screen.
class CvFormControllers {
  final name = TextEditingController();
  final email = TextEditingController();
  final phone = TextEditingController();
  final linkedin = TextEditingController();
  final location = TextEditingController();
  final jobTitle = TextEditingController();
  final jobDescription = TextEditingController();
  final summary = TextEditingController();
  final skills = TextEditingController();
  final experience = TextEditingController();
  final education = TextEditingController();
  final certifications = TextEditingController();

  List<TextEditingController> get all => [
        name,
        email,
        phone,
        linkedin,
        location,
        jobTitle,
        jobDescription,
        summary,
        skills,
        experience,
        education,
        certifications,
      ];

  /// Controller behind an AI-enhanceable field key.
  TextEditingController forEnhanceableField(String field) => switch (field) {
        'skills' => skills,
        'summary' => summary,
        'experience' => experience,
        'education' => education,
        'certifications' => certifications,
        _ => throw ArgumentError.value(field, 'field', 'not enhanceable'),
      };

  CvFormData read({required String language}) => CvFormData(
        fullName: name.text,
        email: email.text,
        phone: phone.text,
        linkedin: linkedin.text,
        location: location.text,
        targetJobTitle: jobTitle.text,
        jobDescription: jobDescription.text,
        summary: summary.text,
        skills: skills.text,
        experience: experience.text,
        education: education.text,
        certifications: certifications.text,
        language: language,
      );

  void fill(CvFormData data) {
    name.text = data.fullName;
    email.text = data.email;
    phone.text = data.phone;
    linkedin.text = data.linkedin;
    location.text = data.location;
    jobTitle.text = data.targetJobTitle;
    jobDescription.text = data.jobDescription;
    summary.text = data.summary;
    skills.text = data.skills;
    experience.text = data.experience;
    education.text = data.education;
    certifications.text = data.certifications;
  }

  void addListener(VoidCallback listener) {
    for (final c in all) {
      c.addListener(listener);
    }
  }

  void removeListener(VoidCallback listener) {
    for (final c in all) {
      c.removeListener(listener);
    }
  }

  void dispose() {
    for (final c in all) {
      c.dispose();
    }
  }
}
