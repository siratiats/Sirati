import 'package:flutter/material.dart';

import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/components.dart';

/// Generic card wrapper used across all CV builder section editors (SIRATI-36).
///
/// Provides a unified visual style, entry count badge, collapse/expand toggle,
/// and primary "Add Entry" action.
class SectionCardWrapper extends StatefulWidget {
  final String title;
  final String? subtitle;
  final IconData icon;
  final int entryCount;
  final VoidCallback? onAdd;
  final String? addLabel;
  final Widget child;
  final bool initialExpanded;
  final String? validationError;

  const SectionCardWrapper({
    super.key,
    required this.title,
    this.subtitle,
    required this.icon,
    this.entryCount = 0,
    this.onAdd,
    this.addLabel,
    required this.child,
    this.initialExpanded = true,
    this.validationError,
  });

  @override
  State<SectionCardWrapper> createState() => _SectionCardWrapperState();
}

class _SectionCardWrapperState extends State<SectionCardWrapper> {
  late bool _expanded = widget.initialExpanded;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final l10n = AppLocalizations.of(context);

    return AppSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header
          InkWell(
            onTap: () => setState(() => _expanded = !_expanded),
            borderRadius: BorderRadius.circular(AppRadius.md),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.xs),
                    decoration: BoxDecoration(
                      color: c.primaryLight,
                      borderRadius: BorderRadius.circular(AppRadius.sm),
                    ),
                    child: Icon(widget.icon, size: 20, color: c.primary),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Text(
                              widget.title,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleSmall
                                  ?.copyWith(
                                    fontWeight: FontWeight.bold,
                                  ),
                            ),
                            if (widget.entryCount > 0) ...[
                              const SizedBox(width: AppSpacing.xs),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 6,
                                  vertical: 2,
                                ),
                                decoration: BoxDecoration(
                                  color: c.surfaceHigh,
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  '${widget.entryCount}',
                                  style: Theme.of(context)
                                      .textTheme
                                      .labelSmall
                                      ?.copyWith(
                                        color: c.textSecondary,
                                        fontWeight: FontWeight.bold,
                                      ),
                                ),
                              ),
                            ],
                          ],
                        ),
                        if (widget.subtitle != null)
                          Text(
                            widget.subtitle!,
                            style:
                                Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: c.textSecondary,
                                    ),
                          ),
                      ],
                    ),
                  ),
                  Icon(
                    _expanded
                        ? Icons.keyboard_arrow_up
                        : Icons.keyboard_arrow_down,
                    color: c.textHint,
                  ),
                ],
              ),
            ),
          ),

          // Inline validation error
          if (widget.validationError != null &&
              widget.validationError!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.xs),
              child: Row(
                children: [
                  Icon(Icons.error_outline, size: 14, color: c.error),
                  const SizedBox(width: AppSpacing.xxs),
                  Expanded(
                    child: Text(
                      widget.validationError!,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: c.error,
                          ),
                    ),
                  ),
                ],
              ),
            ),

          // Collapsible Content
          if (_expanded) ...[
            const Divider(height: AppSpacing.lg),
            widget.child,
            if (widget.onAdd != null) ...[
              const SizedBox(height: AppSpacing.md),
              AppButton.secondary(
                label: widget.addLabel ?? l10n.addItem,
                icon: Icons.add,
                onPressed: widget.onAdd,
              ),
            ],
          ],
        ],
      ),
    );
  }
}

/// Generic reorderable entry list supporting RTL drag physics and accessible
/// Up / Down keyboard buttons (SIRATI-40).
class ReorderableEntryList<T> extends StatelessWidget {
  final List<T> items;
  final Widget Function(BuildContext context, T item, int index) itemBuilder;
  final void Function(int oldIndex, int newIndex) onReorder;
  final void Function(int index)? onDelete;

  const ReorderableEntryList({
    super.key,
    required this.items,
    required this.itemBuilder,
    required this.onReorder,
    this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    final l10n = AppLocalizations.of(context);

    return ReorderableListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: items.length,
      // ignore: deprecated_member_use
      onReorder: onReorder,
      itemBuilder: (context, index) {
        final item = items[index];
        final isFirst = index == 0;
        final isLast = index == items.length - 1;

        return KeyedSubtree(
          key: ValueKey(item.hashCode ^ index),
          child: Container(
            margin: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Reorder Drag Handle & Accessible Up/Down Actions
                Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    ReorderableDragStartListener(
                      index: index,
                      child: const Padding(
                        padding: EdgeInsets.symmetric(
                          vertical: AppSpacing.xs,
                          horizontal: AppSpacing.xxs,
                        ),
                        child: Icon(Icons.drag_indicator, size: 20),
                      ),
                    ),
                    // Accessible move up button
                    IconButton(
                      icon: const Icon(Icons.arrow_upward, size: 16),
                      tooltip: l10n.moveUp,
                      onPressed:
                          isFirst ? null : () => onReorder(index, index - 1),
                      constraints:
                          const BoxConstraints(minWidth: 32, minHeight: 28),
                      padding: EdgeInsets.zero,
                    ),
                    // Accessible move down button
                    IconButton(
                      icon: const Icon(Icons.arrow_downward, size: 16),
                      tooltip: l10n.moveDown,
                      onPressed:
                          isLast ? null : () => onReorder(index, index + 2),
                      constraints:
                          const BoxConstraints(minWidth: 32, minHeight: 28),
                      padding: EdgeInsets.zero,
                    ),
                  ],
                ),
                const SizedBox(width: AppSpacing.xs),
                // Entry Body
                Expanded(
                  child: itemBuilder(context, item, index),
                ),
                // Delete button
                if (onDelete != null)
                  IconButton(
                    icon: const Icon(Icons.delete_outline, size: 18),
                    color: context.sirati.error,
                    tooltip: l10n.deleteItem,
                    onPressed: () => onDelete!(index),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}
