<?php

namespace Database\Seeders;

use App\Models\CampaignTemplate;
use App\Models\Category;
use App\Models\DeliverableOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CampaignDefaultsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Video Content',
            'Social Media',
            'UGC',
        ];

        foreach ($categories as $categoryName) {
            $category = Category::query()->updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                [
                    'workspace_id' => null,
                    'name' => $categoryName,
                ],
            );

            $reel = DeliverableOption::query()->where('workspace_id', null)->where('slug', 'ig_reel')->first();
            $story = DeliverableOption::query()->where('workspace_id', null)->where('slug', 'ig_story')->first();
            $tiktok = DeliverableOption::query()->where('workspace_id', null)->where('slug', 'tiktok_video')->first();

            CampaignTemplate::query()->updateOrCreate(
                [
                    'workspace_id' => null,
                    'category_id' => $category->id,
                    'name' => $categoryName.' Standard Brief',
                ],
                [
                    'is_global' => true,
                    'deliverable_options' => array_values(array_filter([
                        $this->buildTemplateDeliverable($reel, 1, 5000),
                        $this->buildTemplateDeliverable($story, 2, 1500),
                        $this->buildTemplateDeliverable($tiktok, 1, 4000),
                    ])),
                    'form_schema' => [
                        'sections' => [
                            [
                                'title' => 'Campaign Details',
                                'fields' => [
                                    [
                                        'name' => 'pitch',
                                        'type' => 'textarea',
                                        'label' => 'Why work with this creator?',
                                    ],
                                    [
                                        'name' => 'goals',
                                        'type' => 'select',
                                        'label' => 'Campaign Goal',
                                        'options' => ['Sales', 'Awareness', 'App Installs'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            );
        }
    }

    private function buildTemplateDeliverable(?DeliverableOption $option, int $quantity, float $unitPrice): ?array
    {
        if (! $option) {
            return null;
        }

        $fieldValues = [];

        foreach ((array) ($option->fields ?? []) as $fieldDefinition) {
            $fieldKey = (string) data_get($fieldDefinition, 'key', '');

            if ($fieldKey === '') {
                continue;
            }

            $fieldValues[$fieldKey] = data_get($fieldDefinition, 'default', '');
        }

        $fieldValues['quantity'] = $quantity;
        $fieldValues['unit_price'] = $unitPrice;

        return [
            'deliverable_option_id' => $option->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'fields' => $fieldValues,
        ];
    }
}
