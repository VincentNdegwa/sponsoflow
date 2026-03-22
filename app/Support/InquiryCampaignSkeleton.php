<?php

namespace App\Support;

class InquiryCampaignSkeleton
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public static function build(string $creatorName, float $budget, ?string $campaignName = null, array $answers = []): array
    {
        $schema = self::formSchema();

        return [
            'meta' => [
                'campaign_name' => $campaignName ?: self::defaultCampaignName($creatorName),
                'total_budget' => round($budget, 2),
            ],
            'form_schema' => $schema,
            'answers' => array_merge(self::defaultAnswers(), $answers),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formSchema(): array
    {
        return [
            'sections' => [
                [
                    'title' => 'FAQ Briefing',
                    'fields' => [
                        [
                            'name' => 'main_goal',
                            'type' => self::resolveType('select'),
                            'label' => 'What is the main goal?',
                            'options' => ['Awareness', 'Sales', 'Content Creation'],
                            'required' => true,
                        ],
                        [
                            'name' => 'pitch',
                            'type' => self::resolveType('textarea'),
                            'label' => 'The Pitch',
                            'required' => true,
                        ],
                        [
                            'name' => 'product_service_link',
                            'type' => self::resolveType('text'),
                            'label' => 'Product/Service Link',
                            'required' => true,
                        ],
                        [
                            'name' => 'mandatory_mention',
                            'type' => self::resolveType('text'),
                            'label' => 'Mandatory Mention',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function uiSchema(): array
    {
        return [
            'metadata' => [
                [
                    'key' => 'campaign_name',
                    'label' => 'Campaign Name',
                    'type' => self::resolveType('text'),
                    'readonly' => false,
                    'required' => true,
                    'help' => 'Auto-filled and editable.',
                ],
                [
                    'key' => 'budget',
                    'label' => 'Total Budget',
                    'type' => self::resolveType('number'),
                    'readonly' => true,
                    'required' => true,
                    'help' => 'Auto-filled from product price.',
                ],
            ],
            'sections' => [
                [
                    'title' => 'FAQ Briefing',
                    'fields' => [
                        [
                            'key' => 'main_goal',
                            'label' => 'What is the main goal?',
                            'type' => self::resolveType('select'),
                            'required' => true,
                            'options' => [
                                ['value' => 'awareness', 'label' => 'Awareness'],
                                ['value' => 'sales', 'label' => 'Sales'],
                                ['value' => 'content_creation', 'label' => 'Content Creation'],
                            ],
                        ],
                        [
                            'key' => 'pitch',
                            'label' => 'The Pitch',
                            'type' => self::resolveType('textarea'),
                            'required' => true,
                            'placeholder' => 'Tell the creator why they are a perfect fit for this.',
                        ],
                        [
                            'key' => 'product_service_link',
                            'label' => 'Product/Service Link',
                            'type' => self::resolveType('text'),
                            'required' => true,
                            'placeholder' => 'https://yourbrand.com/product',
                        ],
                        [
                            'key' => 'mandatory_mention',
                            'label' => 'Mandatory Mention',
                            'type' => self::resolveType('text'),
                            'required' => false,
                            'placeholder' => 'Specific phrase or discount code',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function defaultCampaignName(string $creatorName): string
    {
        return 'Project with @'.$creatorName;
    }

    /**
     * @return array<string, string>
     */
    public static function defaultAnswers(): array
    {
        return [
            'main_goal' => '',
            'pitch' => '',
            'product_service_link' => '',
            'mandatory_mention' => '',
        ];
    }

    private static function resolveType(string $preferred): string
    {
        $availableTypes = CampaignFieldTypeRegistry::keys();

        if (in_array($preferred, $availableTypes, true)) {
            return $preferred;
        }

        return 'text';
    }
}
