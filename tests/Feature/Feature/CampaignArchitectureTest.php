<?php

use App\Enums\CampaignApplicationStatus;
use App\Enums\CampaignSlotStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignTemplate;
use App\Models\Category;
use App\Models\DeliverableOption;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CampaignService;
use Database\Seeders\CampaignDefaultsSeeder;
use Database\Seeders\DeliverableOptionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('campaign defaults seeder creates global categories and templates', function () {
    $this->seed(CampaignDefaultsSeeder::class);

    expect(Category::query()->whereNull('workspace_id')->count())->toBeGreaterThan(0)
        ->and(CampaignTemplate::query()->whereNull('workspace_id')->count())->toBeGreaterThan(0);
});

test('deliverable options seeder creates global options', function () {
    $this->seed(DeliverableOptionsSeeder::class);

    expect(\App\Models\DeliverableOption::query()->whereNull('workspace_id')->count())->toBeGreaterThan(0);
});

test('campaign service creates private campaign with normalized deliverables and budget', function () {
    $brandOwner = User::factory()->create();

    $brandWorkspace = Workspace::factory()->brand()->create(['owner_id' => $brandOwner->id]);

    $category = Category::factory()->global()->create();

    $reelOption = DeliverableOption::query()->create([
        'workspace_id' => null,
        'name' => 'Instagram Reel',
        'slug' => 'ig_reel',
    ]);

    $storyOption = DeliverableOption::query()->create([
        'workspace_id' => null,
        'name' => 'Instagram Story',
        'slug' => 'ig_story',
    ]);

    $template = CampaignTemplate::factory()->global()->create([
        'category_id' => $category->id,
        'deliverable_options' => [
            [
                'deliverable_option_id' => $reelOption->id,
                'min' => 0,
                'max' => 2,
                'unit_price' => 5000,
            ],
            [
                'deliverable_option_id' => $storyOption->id,
                'min' => 1,
                'max' => 5,
                'unit_price' => 1500,
            ],
        ],
    ]);

    $deliverables = [
        [
            'deliverable_option_id' => $reelOption->id,
            'type_slug' => 'ig_reel',
            'label' => 'Instagram Reel',
            'qty' => 1,
            'unit_price' => 5000,
        ],
        [
            'deliverable_option_id' => $storyOption->id,
            'type_slug' => 'ig_story',
            'label' => 'Instagram Story',
            'qty' => 3,
            'unit_price' => 1500,
        ],
    ];

    $service = app(CampaignService::class);

    app()->instance('current.workspace', $brandWorkspace);

    $campaign = $service->createCampaign(
        template: $template,
        contentBrief: [
            'pitch' => 'We love your style and want to launch our sneaker line with your audience.',
            'goals' => 'Awareness',
        ],
        deliverables: $deliverables,
        title: 'Sneaker Launch Wave 1',
    );

    $campaign = Campaign::query()->findOrFail($campaign->id);

    expect($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->is_public)->toBeFalse()
        ->and((float) $campaign->total_budget)->toBe(9500.00)
        ->and(count($campaign->deliverables))->toBe(2)
        ->and((float) $campaign->deliverables[1]['subtotal'])->toBe(4500.0)
        ->and($campaign->deliverables[0]['deliverable_option_id'])->toBe($reelOption->id)
        ->and($campaign->content_brief['goals'])->toBe('Awareness');
});

test('campaign application and slot support marketplace and inquiry execution records', function () {
    $brandOwner = User::factory()->create();
    $creatorOwner = User::factory()->create();

    $brandWorkspace = Workspace::factory()->brand()->create(['owner_id' => $brandOwner->id]);
    $creatorWorkspace = Workspace::factory()->creator()->create(['owner_id' => $creatorOwner->id]);

    $deliverableOption = DeliverableOption::query()->create([
        'workspace_id' => null,
        'name' => 'Placeholder',
        'slug' => 'placeholder-option',
    ]);

    $creatorProduct = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'name' => 'UGC Starter Package',
        'base_price' => 2500,
    ]);

    app()->instance('current.workspace', $brandWorkspace);

    $campaign = app(CampaignService::class)->createCampaign(
        template: null,
        contentBrief: ['pitch' => 'Need summer launch assets.'],
        deliverables: [[
            'deliverable_option_id' => $deliverableOption->id,
            'type_slug' => 'placeholder-option',
            'label' => 'Placeholder',
            'qty' => 1,
            'unit_price' => 2500,
        ]],
        title: 'Summer Launch',
        isPublic: true,
        status: CampaignStatus::Published,
    );

    $service = app(CampaignService::class);

    $application = $service->submitApplication(
        campaign: $campaign,
        creatorWorkspace: $creatorWorkspace,
        product: $creatorProduct,
    );

    $slot = $service->approveApplication(application: $application);

    expect($campaign->applications()->count())->toBe(1)
        ->and($campaign->slots()->count())->toBe(1)
        ->and($application->refresh()->status)->toBe(CampaignApplicationStatus::Approved)
        ->and($slot->status)->toBe(CampaignSlotStatus::Pending)
        ->and($slot->application?->id)->toBe($application->id);
});

test('campaign service creates private inquiry campaign and execution slot without application', function () {
    $brandOwner = User::factory()->create();
    $creatorOwner = User::factory()->create();

    $brandWorkspace = Workspace::factory()->brand()->create(['owner_id' => $brandOwner->id]);
    $creatorWorkspace = Workspace::factory()->creator()->create(['owner_id' => $creatorOwner->id]);

    $deliverableOption = DeliverableOption::query()->create([
        'workspace_id' => null,
        'name' => 'Short Video',
        'slug' => 'short-video',
    ]);

    $creatorProduct = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'name' => 'UGC Video Bundle',
        'base_price' => 4200,
    ]);

    app()->instance('current.workspace', $brandWorkspace);

    $slot = app(CampaignService::class)->createInquiryCampaignSlot(
        creatorWorkspace: $creatorWorkspace,
        product: $creatorProduct,
        contentBrief: ['goal' => 'Acquire 100 trial signups.'],
        deliverables: [[
            'deliverable_option_id' => $deliverableOption->id,
            'type_slug' => 'short-video',
            'label' => 'Short Video',
            'qty' => 2,
            'unit_price' => 2100,
        ]],
        title: 'Private Creator Inquiry',
    );

    $campaign = $slot->campaign()->firstOrFail();

    expect($slot->application_id)->toBeNull()
        ->and($slot->status)->toBe(CampaignSlotStatus::Pending)
        ->and($campaign->is_public)->toBeFalse()
        ->and($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->slots()->count())->toBe(1)
        ->and($campaign->applications()->count())->toBe(0);
});
