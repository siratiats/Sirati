@if (! empty($cv['is_watermarked']))
    <style>
        .sirati-preview-watermark {
            position: fixed;
            top: 35%;
            left: 5%;
            width: 90%;
            text-align: center;
            transform: rotate(-30deg);
            -webkit-transform: rotate(-30deg);
            z-index: 9999;
            pointer-events: none;
            user-select: none;
            -webkit-user-select: none;
        }
        .sirati-preview-watermark-badge {
            display: inline-block;
            padding: 16px 32px;
            border: 3px dashed rgba(220, 38, 38, 0.45);
            border-radius: 8px;
            color: rgba(220, 38, 38, 0.4);
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        @media print {
            .sirati-preview-watermark {
                display: block !important;
                visibility: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .sirati-preview-watermark-badge {
                border-color: rgba(220, 38, 38, 0.6) !important;
                color: rgba(220, 38, 38, 0.6) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
    <div class="sirati-preview-watermark">
        <div class="sirati-preview-watermark-badge">
            {{ $cv['watermark_text'] ?? 'PREVIEW · SIRATI' }}
        </div>
    </div>
@endif
