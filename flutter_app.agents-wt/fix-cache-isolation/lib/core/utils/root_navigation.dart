import 'package:flutter/material.dart';

/// Replaces the entire navigator stack with [page] as the new root.
///
/// Auth entry points (login, register, OTP, splash session restore, logout)
/// must use this exactly once so competing routes cannot leave an empty
/// surface after a delayed session change.
void replaceRoot(BuildContext context, Widget page) {
  Navigator.of(context).pushAndRemoveUntil(
    MaterialPageRoute(builder: (_) => page),
    (route) => false,
  );
}
