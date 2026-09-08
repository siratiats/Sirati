# Sprint 4 — sign-off addendum: backfill verified, and one thing it exposed

You were right and I was wrong to close it. I framed the backfill as conditional on whether stored CVs exist, when the two load paths — `CvSchemaMigrator` on local draft open, and hydration for PDF — mean *any* stored document hits it. "Probably no data yet" is not a reason to leave a repair unwritten; it is a reason it would not have been noticed. The correction was the right call.

**The backfill is correct on both sides.** Verified below. But re-reading the PHP hydrate path to check it turned up a pre-existing defect that erases the field the backfill just repaired, and I would not close Sprint 4 without it on the record.

---

## Verified: the repair is right

**Flutter.** `_backfillProficiencyEnglish` runs after the version loop, unconditionally — so a document already at v1 still gets repaired, which is the whole point:

```dart
currentData['schema_version'] = currentVersion;
final filled = _backfillProficiencyEnglish(currentData);
return MigrationResult(…, wasMigrated: applied.isNotEmpty || filled, …);
```

`_backfillSection` walks `skills[].level`, `skills[].category` and `languages[].level`, and `_fillEmptyEn` is guarded on both sides — empty `ar` or non-empty `en` returns false, unknown `ar` returns false. So it is idempotent (second pass → `wasMigrated: false`, as you say), non-destructive for custom values, and does not touch the version. Schema stays 1. Data repair, not a migration — and the docblock says exactly that, which is what makes it findable in six months.

**PHP.** `LanguageSkill::fromArray` fills at hydrate through the same table:

```php
level: ProficiencyStorageKeys::fillEmptyEnglish(
    LocalizedText::fromArray($value['level'] ?? null),
    ProficiencyStorageKeys::LANGUAGE_LEVEL,
),
```

`fillEmptyEnglish` has the same three guards as the Dart side. Repairing at hydrate rather than at write is the right choice — an English PDF of an old payload is correct immediately, without waiting for the Flutter app to open, migrate and re-save that document. Two independent repairs on two independent read paths, which is what "does not wait for a Flutter re-save" buys.

And feeding empty `en` on all five levels into the existing HTML test is the population check I asked for. It now proves the repair, not just the write:

```php
$this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $remainder, …);
```

That is the finding closed, properly.

---

## What this exposed — the PHP `Skill` model drops `level`, `category` and `id`

Not caused by this round. But it deletes exactly what this round just fixed, so it belongs here.

`app/Cv/Skill.php` carries one field:

```php
public function __construct(public LocalizedText $name = new LocalizedText) {}

public static function fromArray(array|string $value): self
{
    return new self(name: LocalizedText::fromArray($value['name'] ?? $value));
}

public function toArray(): array { return ['name' => $this->name->toArray()]; }
```

The Flutter model carries four:

```dart
class Skill {
  final String? id;
  final LocalizedText name;
  final LocalizedText level;
  final LocalizedText category;
```

So `level`, `category` and `id` are silently discarded by any PHP hydrate → serialize round-trip. Two of those paths persist the result:

| path | what happens |
|---|---|
| `GeneratedCvController:473` — `'document' => $document->toArray()` on create/update | client-sent skills are stored stripped |
| `GeneratedCv::duplicateForUser()` — `$copy->document = …->duplicate()->toArray()` | duplicating a CV deletes every skill's level, category and id |

And the client *does* send documents — `'document' => ['nullable', 'array']` at line 369, hydrated at 420, re-serialized at 473 — while `GeneratedCvResource:37` hands `$this->cvDocument()->toArray()` back. So the primary save-and-reload cycle is lossy: the app writes `{name, level, category, id}`, the API returns `{name}`.

Nothing catches it because the JSON schema declares the collection shapelessly:

```php
'skills' => ['type' => 'array', 'items' => ['type' => 'object']],
```

**Three consequences, in order.**

1. **The bilingual level and category are deleted, not just un-translated.** Everything this round built — the storage keys, the `englishFor` maps, the Flutter backfill — writes into fields the backend removes on the next save. The Flutter-local path is unaffected, so this shows up as data that survives locally and vanishes after a sync or a duplicate.
2. **`id` going with them costs identity.** It is the `ValueKey` behind reorder and per-entry edit in `SkillsLanguagesEditor`. A document that round-trips through the API comes back with every skill id null.
3. **`ProficiencyStorageKeys::SKILL` has no call site.** Nine entries, mirrored from Flutter for symmetry, and unreachable — because `Skill::fromArray` has no `level` or `category` to fill. It reads as covered and is not. (`LANGUAGE_LEVEL` is live and correct; only the skill half is orphaned.)

Point 3 is worth pausing on, because it is rule 6 arriving one layer down from where we last found it. The map was written to mirror a Flutter class, not against the PHP class it actually had to serve — and there was no consumer to make the mismatch visible.

**Fix.** Give PHP `Skill` the three missing fields, mirroring `LanguageSkill`:

```php
public function __construct(
    public LocalizedText $name = new LocalizedText,
    public LocalizedText $level = new LocalizedText,
    public LocalizedText $category = new LocalizedText,
    public ?string $id = null,
) {}
```

with `fillEmptyEnglish(…, ProficiencyStorageKeys::SKILL)` on `level` and `category` in `fromArray` — which gives the `SKILL` map its call site — and both emitted from `toArray()`. The string branch of `fromArray` stays as it is for legacy comma-separated input.

**The test that makes it stay fixed** is a round-trip identity assertion, not a field checklist: build a document with every field populated, run `CvDocument::fromArray($doc->toArray())`, and assert the result equals the input. That covers `Skill` today and every model that grows a field later — which is the version of this defect that would otherwise recur. It is the same assertion I would have wanted against the Sprint 1 lossy migration (18 fields in, 13 out), and it is the general form of both.

Worth checking the other five models the same way while the round-trip test is being written — `Certification`, `Project`, `ExperienceEntry`, `EducationEntry`, `CustomSection`. I have not compared those against their Flutter counterparts; the test would answer it in one run rather than by inspection.

---

## Status

| | Status |
|---|---|
| Backfill — Flutter migrator | **verified** — unconditional, idempotent, guarded, version untouched |
| Backfill — PHP hydrate | **verified** — same maps, repairs on read, no re-save dependency |
| Population check in the HTML test | **verified** — empty `en` on all five levels, still zero Arabic |
| PHP `Skill` drops `level`, `category`, `id` | **open** — pre-existing, live on two persistence paths |
| `ProficiencyStorageKeys::SKILL` unreachable | open — closes with the above |

Sprint 4's own scope is done. This one is older than Sprint 4 and sits in the CV document contract rather than in i18n — so it is a ticket of its own, not a fifth round. But it should be filed now, while the two models are side by side on screen, because it quietly undoes the work that just shipped.

## One note on the review, not the code

I closed a finding on an assumption about your data instead of checking the load paths, which were three greps away. The rule I have been applying to the implementation — trace the value to where a user sees it — applies to a reviewer deciding what is safe to leave open. Noted, and the round-trip identity test above is the version of this finding that does not depend on either of us guessing right.
