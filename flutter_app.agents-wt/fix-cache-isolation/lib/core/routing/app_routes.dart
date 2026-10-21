/// Named route table (SIRATI-13).
class AppRoutes {
  AppRoutes._();

  static const splash = '/';
  static const home = '/home';
  static const login = '/login';
  static const register = '/register';
  static const history = '/history';
  static const createCv = '/create-cv';
  static const myCvs = '/mycvs';
  static const education = '/education';
  static const jobNews = '/job-news';
  static const privacy = '/privacy';
  static const settings = '/settings';
  static const notifications = '/notifications';
  static const premium = '/premium';
  static const gallery = '/gallery';
  static const cvBuilder = '/builder';
  static const notFound = '/not-found';

  static const cvPrefix = '/cv/';
  static const cvBuilderPrefix = '/builder/';
  static const analysisPrefix = '/analysis/';
  static const educationItemPrefix = '/education/';

  static String cv(int id) => '$cvPrefix$id';
  static String builder([String? id]) =>
      id != null ? '$cvBuilderPrefix$id' : cvBuilder;
  static String analysis(int id) => '$analysisPrefix$id';
  static String educationItem(int id) => '$educationItemPrefix$id';
}

class ParsedRoute {
  const ParsedRoute({
    required this.name,
    this.id,
    this.stringId,
    this.requiresEntitlement = false,
    this.unknown = false,
    this.arguments,
  });

  /// `RouteSettings.arguments` of the push that produced this route.
  final Object? arguments;

  ParsedRoute withArguments(Object? arguments) => ParsedRoute(
        name: name,
        id: id,
        stringId: stringId,
        requiresEntitlement: requiresEntitlement,
        unknown: unknown,
        arguments: arguments,
      );

  final String name;
  final int? id;
  final String? stringId;
  final bool requiresEntitlement;
  final bool unknown;
}
