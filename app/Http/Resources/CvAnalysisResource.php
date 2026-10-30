<?php

namespace App\Http\Resources;

use App\Enums\AiStatus;
use App\Services\I18n\ServerText;
use App\Services\Subscriptions\SubscriptionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CvAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unlocked = app(SubscriptionGate::class)->allowsFullAnalysis($request->user());

        // The score is the hook and stays free. The fixes are the product.
        $strengths = $this->mobileList($this->strengths);
        $weaknesses = $this->mobileList($this->weaknesses);
        $keywordsFound = $this->mobileList($this->keywords_found);
        $keywordsMissing = $this->mobileList($this->keywords_missing);
        $quickWins = $this->mobileList($this->quick_wins);

        $limits = (array) config('monetization.free.analysis', []);

        $visibleStrengths = $unlocked ? $strengths : $this->take($strengths, $limits['strengths'] ?? 2);
        $visibleWeaknesses = $unlocked ? $weaknesses : $this->take($weaknesses, $limits['weaknesses'] ?? 2);
        $visibleKeywordsFound = $unlocked ? $keywordsFound : $this->take($keywordsFound, $limits['keywords_found'] ?? 5);
        $visibleKeywordsMissing = $unlocked ? $keywordsMissing : $this->take($keywordsMissing, $limits['keywords_missing'] ?? 0);
        $visibleQuickWins = $unlocked ? $quickWins : $this->take($quickWins, $limits['quick_wins'] ?? 1);
        $showFeedback = $unlocked || (bool) ($limits['ai_feedback'] ?? false);

        // Job match exists only when it was measured against a job description;
        // otherwise null, and keyword_source says why (issue #16).
        $keywordSource = $this->keyword_source === 'job_description' ? 'job_description' : 'category';

        return [
            'id' => $this->id,
            'target_job_title' => $this->target_job_title,
            'job_description_input' => $this->job_description_input,
            'original_filename' => $this->original_filename,
            'input_method' => $this->input_method,
            'score_total' => $this->score_total,
            'grade' => $this->grade,
            'job_match' => $keywordSource === 'job_description' ? $this->job_match : null,
            'category' => $this->category ?? 'general',
            'category_label' => $this->category_label,
            'category_label_en' => $this->category_label_en,
            'is_general_profile' => ($this->category ?? 'general') === 'general',
            // "job_description": keywords and job_match come from the pasted JD.
            // "category": no JD (or no recognisable terms in it); keywords come
            // from the generic category list and job_match is null (SIRATI-90).
            'keyword_source' => $keywordSource,
            'criteria' => $this->mobileList($this->criteria),
            'strengths' => $visibleStrengths,
            'weaknesses' => $visibleWeaknesses,
            'keywords_found' => $visibleKeywordsFound,
            'keywords_missing' => $visibleKeywordsMissing,
            'quick_wins' => $visibleQuickWins,
            'ai_status' => $this->ai_status instanceof AiStatus
                ? $this->ai_status->value
                : $this->ai_status,
            'ai_feedback' => $showFeedback ? $this->ai_feedback : null,
            'ai_error' => $this->ai_error,
            'classification_warning' => $this->classification_warning,
            'access' => $this->accessBlock($unlocked, [
                'strengths' => count($strengths) - count($visibleStrengths),
                'weaknesses' => count($weaknesses) - count($visibleWeaknesses),
                'keywords_missing' => count($keywordsMissing) - count($visibleKeywordsMissing),
                'quick_wins' => count($quickWins) - count($visibleQuickWins),
                'ai_feedback' => ($showFeedback || blank($this->ai_feedback)) ? 0 : 1,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Tells the app exactly how much is withheld, so the paywall can say
     * "٦ توصيات مخفية" instead of a generic upsell. Concrete beats vague.
     *
     * @param  array<string, int>  $hidden
     * @return array<string, mixed>
     */
    private function accessBlock(bool $unlocked, array $hidden): array
    {
        $hidden = array_map(static fn ($count): int => max(0, (int) $count), $hidden);
        $total = array_sum($hidden);

        if ($unlocked || $total === 0) {
            return [
                'is_locked' => false,
                'hidden_total' => 0,
                'hidden' => $hidden,
            ];
        }

        return [
            'is_locked' => true,
            'reason' => 'subscription_required',
            'hidden_total' => $total,
            'hidden' => $hidden,
            'title_ar' => ServerText::t('serverAnalysisLockedTitle', [], 'ar'),
            'title_en' => ServerText::t('serverAnalysisLockedTitle', [], 'en'),
            'message_ar' => ServerText::t('serverAnalysisLockedMessage', ['count' => $total], 'ar'),
            'message_en' => ServerText::t('serverAnalysisLockedMessage', ['count' => $total], 'en'),
            'cta_ar' => ServerText::t('serverAnalysisLockedCta', [], 'ar'),
            'cta_en' => ServerText::t('serverAnalysisLockedCta', [], 'en'),
        ];
    }

    /**
     * @param  list<mixed>  $items
     * @return list<mixed>
     */
    private function take(array $items, mixed $limit): array
    {
        $limit = max(0, (int) $limit);

        return $limit === 0 ? [] : array_slice($items, 0, $limit);
    }

    private function mobileList(?array $value): array
    {
        if ($value === null) {
            return [];
        }

        return array_is_list($value) ? $value : array_values($value);
    }
}
