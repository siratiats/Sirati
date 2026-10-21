import 'package:flutter/material.dart';

import 'package:sirati/core/flavors/app_flavor.dart';

/// Visual non-prod marker. Renders nothing in [AppFlavor.prod].
class FlavorBanner extends StatelessWidget {
  const FlavorBanner({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final flavor = AppFlavor.current;
    if (!flavor.showBanner) return child;
    return Banner(
      message: flavor.label,
      location: BannerLocation.topEnd,
      color: flavor.bannerColor,
      textStyle: const TextStyle(
        fontWeight: FontWeight.w800,
        fontSize: 10,
        letterSpacing: 0.6,
        color: Colors.white, // ignore: hardcoded_color -- non_prod_build_marker
      ),
      child: child,
    );
  }
}
