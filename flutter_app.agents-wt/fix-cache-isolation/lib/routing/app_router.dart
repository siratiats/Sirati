import 'package:sirati/features/app/sirati_route_table.dart';

export 'package:sirati/core/routing/app_router.dart';

/// Importing this barrel registers feature screens with the core router.
final siratiRouteTableReady = () {
  registerSiratiRouteWidgets();
  return true;
}();
