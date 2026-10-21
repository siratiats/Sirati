import 'package:flutter/material.dart';

/// Caps auth forms at a readable width on iPad-sized screens.
///
/// Phone layouts are unchanged because 460 px exceeds typical portrait width.
class AuthFormConstraint extends StatelessWidget {
  static const double maxWidth = 460;

  final Widget child;

  const AuthFormConstraint({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.topCenter,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: maxWidth),
        child: child,
      ),
    );
  }
}
