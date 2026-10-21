/// Filter state for the job-news list. 'all' means "no filter" for every
/// chip group, which is what the API expects as an omitted parameter.
class JobNewsFilters {
  const JobNewsFilters({
    this.preset = all,
    this.category = all,
    this.city = all,
    this.query = '',
  });

  static const all = 'all';

  /// Preset and city both expose a "remote" option; either one means the
  /// API's is_remote filter.
  static const remote = 'remote';

  final String preset;
  final String category;
  final String city;
  final String query;

  String? get presetParam => preset == all ? null : preset;
  String? get categoryParam => category == all ? null : category;
  String? get cityParam => city == all ? null : city;
  bool get isRemote => city == remote || preset == remote;

  JobNewsFilters copyWith({
    String? preset,
    String? category,
    String? city,
    String? query,
  }) =>
      JobNewsFilters(
        preset: preset ?? this.preset,
        category: category ?? this.category,
        city: city ?? this.city,
        query: query ?? this.query,
      );

  @override
  bool operator ==(Object other) =>
      other is JobNewsFilters &&
      other.preset == preset &&
      other.category == category &&
      other.city == city &&
      other.query == query;

  @override
  int get hashCode => Object.hash(preset, category, city, query);
}
