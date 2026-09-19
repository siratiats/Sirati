<?php

namespace App\Http\Resources;

use App\Enums\AiStatus;
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

        return [
            'id' => $this->id,
            'target_job_title' => $this->target_job_title,
            'job_description_input' => $this->job_description_input,
            'original_filename' => $this->original_filename,
            'input_method' => $this->input_method,
            'score_total' => $this->score_total,
            'grade' => $this->grade,
            'job_match' => $this->job_match,
            'category' => $this->category ?? 'general',
            'category_label' => $this->category_label,
            'category_label_en' => $this->category_label_en,
            'is_general_profile' => ($this->category ?? 'general') === 'general',
            // Tells the client whether the keyword score was built against the
            // pasted job description or fell back to the generic category list
            // (SIRATI-90). Derived here so we don't need a new column on
            // cv_analyses just for a boolean the JD input already answers.
            'keyword_source' => trim((string) $this->job_description_input) !== ''
                ? 'job_description'
                : 'category',
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
            'title_ar' => 'باقي التحليل مخفي',
            'title_en' => 'The rest of your analysis is locked',
            'message_ar' => 'لديك '.$total.' ملاحظة وتوصية إضافية تشرح كيف ترفع درجتك. اشترك لعرضها كاملة.',
            'message_en' => 'You have '.$total.' more findings and recommendations explaining how to raise your score. Subscribe to see them all.',
            'cta_ar' => 'عرض التحليل الكامل',
            'cta_en' => 'Unlock full analysis',
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
