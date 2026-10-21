import 'package:sirati/core/flavors/app_flavor.dart';
import 'package:sirati/main.dart' as app;

Future<void> main() async {
  AppFlavor.bootstrap(AppFlavor.dev);
  await app.startSiratiApp();
}
