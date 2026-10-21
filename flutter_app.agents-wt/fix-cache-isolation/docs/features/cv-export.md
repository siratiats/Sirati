# CV export

The export feature contains a PDF options bottom sheet and controller for a generated CV: users can choose output language and template, inspect a filename, download PDF bytes, or invoke the device share sheet. This bottom sheet is currently not connected to the app's CV screens.

## User flow

- A caller can open `CvExportDialog.show` with a generated CV, template list, initial template, API client, and optional upgrade callback. The sheet starts a preview request on open. Users switch Arabic/English export language and template; either choice reloads the preview. Arabic export also offers a Latin filename option.
- A premium template can show a watermark notice. Download requests PDF bytes and shows progress and a success snackbar. Share PDF requests bytes when needed, then opens the native share sheet. A 403/premium response shows a subscription prompt with Upgrade to Pro when a callback was supplied.
- The controller has idle, preview-loading, preview-ready, downloading, ready, error, and premium-locked states. The sheet visibly renders downloading progress and the premium-locked prompt; preview HTML and ordinary error messages are not displayed in the sheet.
- In the current connected flow, My CVs and Generated CV instead offer a template picker (when applicable) and open the PDF URL externally. The generated CV also offers plain-text Share.

## Entry points

- There is no `AppRoutes` route, tab, deep link, or notification target for `CvExportDialog`. No code outside `lib/features/cv_export/` calls it. The connected CV entry points are `AppRoutes.myCvs` (`/mycvs`) and `AppRoutes.cv(id)` (`/cv/<id>`), via `lib/features/cv_builder/presentation/my_cvs_screen.dart` and `lib/features/cv_builder/presentation/generated_cv_screen.dart`.

## Code map

| Path | Responsibility |
| --- | --- |
| `lib/features/cv_export/presentation/cv_export_dialog.dart` | Modal controls, localized labels, download/share buttons, progress and premium notice. |
| `lib/features/cv_export/controllers/pdf_export_controller.dart` | `ChangeNotifier` export state, preview and PDF requests, bytes, native share. |
| `lib/features/cv_export/utils/export_file_namer.dart` | Safe Arabic or Latin PDF filenames, including Arabic-to-Latin fallback. |
| `lib/features/cv_builder/presentation/my_cvs_screen.dart`, `lib/features/cv_builder/presentation/generated_cv_screen.dart` | Current connected PDF-link actions; they do not instantiate the export dialog. |
| `lib/shared/models/generated_cv.dart`, `lib/shared/models/cv_template.dart`, `lib/core/network/api_client.dart` | CV/template fields and authenticated network requests. |

## State and data

- Export uses a dialog-owned `PdfExportController` (`ChangeNotifier`), not Riverpod or a sealed outcome. `PdfExportState` holds status, progress, PDF bytes, generated filename, preview HTML, watermark, language, Latin fallback, template, and an error message. The controller is disposed when the sheet closes.
- PDF bytes and preview HTML live only in memory during the dialog. The implementation does not write a downloaded file, and it has no SharedPreferences or secure-storage key to wipe. `sharePdf` wraps the bytes in an `XFile` and calls `Share.shareXFiles`.
- Filename comes from the CV's full name and target role. Arabic mode keeps sanitized Arabic characters; English or Latin fallback transliterates known Arabic characters and removes illegal filename characters.

## API

| Method and path | Request and response |
| --- | --- |
| `GET /generated-cvs/<id>/preview?template=<slug>&language=<ar\|en>` | Reads `data.html` and `data.is_watermarked` into controller state. |
| `GET /generated-cvs/<id>/download?template=<slug>&language=<ar\|en>` | Reads raw PDF bytes. `ApiException` 403 or a message containing `premium` produces `premiumLocked`; other failures set `error`. |

These requests only run if a caller opens the dialog. The connected screens use `GET /mobile/cv-templates?lang=<ar\|en>` and `GET /generated-cvs/<id>` through `CvApiService`, then open a returned `pdf_url` or `template_pdf_url` with an optional `template` query parameter.

## Localization and RTL

- The dialog sets `Directionality` from the selected export language and contains Arabic/English labels. Template chips use `nameAr` or `nameEn`; Latin fallback appears only for Arabic export. Filename formatting is separate from UI language.
- The connected generated CV screen chooses document direction from the CV language, falling back to markdown direction detection; the connected template picker follows the app locale.

## Tests

- `test/features/cv_export/export_file_namer_test.dart`: Arabic/Latin names, illegal-character stripping, transliteration, and empty-input fallbacks.
- `test/features/cv_export/pdf_export_controller_test.dart`: initial state, language changes, byte download, premium 403, preview HTML and watermark state.
- `test/features/cv_builder/generated_cv_test.dart` and `test/features/cv_builder/my_cvs_controller_test.dart`: connected PDF-link fallback and template query behavior.

## Known gaps

- `lib/features/cv_export/presentation/cv_export_dialog.dart:8`: no app screen instantiates the export dialog, so its preview and native PDF-share flow is not reachable through the current UI.
- `lib/features/cv_export/presentation/cv_export_dialog.dart:64`: preview is fetched, but the dialog does not render `previewHtml`, a preview-loading indicator, or ordinary `errorMessage` text.
- `lib/features/cv_export/controllers/pdf_export_controller.dart:46`: `copyWith(pdfBytes: null)` retains old bytes because it uses `pdfBytes ?? this.pdfBytes`; switching template/language may then share a stale PDF without downloading the new selection.
- `lib/features/cv_export/presentation/cv_export_dialog.dart:329`: Download fetches bytes and reports success but does not save them as a named file. The displayed progress values are fixed controller milestones rather than network progress (`lib/features/cv_export/controllers/pdf_export_controller.dart:185`).
