<?php

namespace App\Support;

use App\Models\Campaign;

class CampaignBookingPayloadFormatter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromCampaign(Campaign $campaign, ?float $budget = null): array
    {
        $contentBrief = is_array($campaign->content_brief) ? $campaign->content_brief : [];
        $sections = (array) data_get($contentBrief, '_form_schema.sections', []);

        return [
            'mode' => 'existing',
            'selected_campaign_id' => $campaign->id,
            'meta' => [
                'campaign_name' => $campaign->title,
                'total_budget' => $budget ?? (float) $campaign->total_budget,
            ],
            'form_schema' => [
                'sections' => $sections,
            ],
            'answers' => self::extractAnswersFromContentBrief($contentBrief, $sections),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function fromInquiryInput(string $creatorName, float $budget, array $input): array
    {
        return array_merge(
            ['mode' => 'new'],
            InquiryCampaignSkeleton::build(
                creatorName: $creatorName,
                budget: $budget,
                campaignName: (string) data_get($input, 'campaign_name', ''),
                answers: [
                    'main_goal' => (string) data_get($input, 'main_goal', data_get($input, 'campaign_goals', '')),
                    'pitch' => (string) data_get($input, 'pitch', ''),
                    'product_service_link' => (string) data_get($input, 'product_service_link', ''),
                    'mandatory_mention' => (string) data_get($input, 'mandatory_mention', ''),
                ],
            )
        );
    }

    /**
     * @param  array<string, mixed>  $contentBrief
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    public static function extractAnswersFromContentBrief(array $contentBrief, array $sections): array
    {
        $answers = [];

        foreach ($sections as $section) {
            foreach ((array) data_get($section, 'fields', []) as $field) {
                $fieldName = (string) data_get($field, 'name', '');
                if ($fieldName === '') {
                    continue;
                }

                $answers[$fieldName] = data_get($contentBrief, $fieldName, '');
            }
        }

        return $answers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeDeliverables(mixed $deliverables): array
    {
        if (! is_array($deliverables)) {
            return [];
        }

        $normalized = [];

        foreach ($deliverables as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function extractProductRequirements(array $data): array
    {
        $requirements = [];

        foreach ($data as $key => $value) {
            if (is_numeric((string) $key)) {
                $requirements[(string) $key] = $value;
            }
        }

        return $requirements;
    }
}
