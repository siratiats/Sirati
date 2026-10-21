/// Placeholder entitlement flag for monetization route guards (SIRATI-13).
///
/// RevenueCat will replace this. Default is locked so `/premium` never
/// opens paid UI until entitlements exist.
class EntitlementStore {
  EntitlementStore._();

  static bool hasPremium = false;
}
