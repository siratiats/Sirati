/// Bottom-navigation tabs of the authenticated shell, in display order.
enum DashboardTab {
  home('dashboard'),
  myCvs('my_cvs'),
  education('education'),
  jobNews('job_news');

  const DashboardTab(this.screenName);

  /// Analytics screen name; the shell is one route, so tabs log manually.
  final String screenName;

  /// Out-of-range indices (stale deep links, bad route args) land on home
  /// instead of crashing the tab stack.
  static DashboardTab fromIndex(int index) =>
      index >= 0 && index < values.length ? values[index] : home;
}
