<?php

namespace Database\Seeders;

use App\Models\DeliverableOption;
use Illuminate\Database\Seeder;

class DeliverableOptionsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'name' => 'Instagram Reel',
                'slug' => 'ig_reel',
                'fields' => [
                    ['key' => 'duration_seconds', 'label' => 'Duration (seconds)', 'type' => 'number'],
                    ['key' => 'usage_rights', 'label' => 'Usage Rights', 'type' => 'select', 'options' => ['Organic', 'Paid Ads', 'Whitelisting']],
                ],
            ],
            [
                'name' => 'Instagram Story',
                'slug' => 'ig_story',
                'fields' => [
                    ['key' => 'frames', 'label' => 'Number of Frames', 'type' => 'number'],
                    ['key' => 'post_date', 'label' => 'Preferred Post Date', 'type' => 'date'],
                ],
            ],
            [
                'name' => 'TikTok Video',
                'slug' => 'tiktok_video',
                'fields' => [
                    ['key' => 'duration_seconds', 'label' => 'Duration (seconds)', 'type' => 'number'],
                    ['key' => 'platform', 'label' => 'Platform', 'type' => 'select', 'options' => ['TikTok', 'Instagram Reels', 'YouTube Shorts']],
                ],
            ],
            [
                'name' => 'YouTube Video',
                'slug' => 'yt_video',
                'fields' => [
                    ['key' => 'integration_minutes', 'type' => 'number', 'label' => 'Integration Minutes'],
                    ['key' => 'video_length_minutes', 'type' => 'number', 'label' => 'Total Video Length (minutes)'],
                ],
            ],
            [
                'name' => 'Blog Post',
                'slug' => 'blog_post',
                'fields' => [
                    ['key' => 'word_count', 'type' => 'number', 'label' => 'Word Count'],
                    ['key' => 'platform', 'type' => 'text', 'label' => 'Publishing Platform'],
                ],
            ],
        ];

        foreach ($defaults as $option) {
            DeliverableOption::query()->updateOrCreate(
                [
                    'workspace_id' => null,
                    'slug' => $option['slug'],
                ],
                [
                    'name' => $option['name'],
                    'is_active' => true,
                    'fields' => $option['fields'] ?? null,
                ],
            );
        }
    }
}
