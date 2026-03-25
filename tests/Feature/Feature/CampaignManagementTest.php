<?php

use App\Enums\CampaignSlotStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignSlot;
use App\Models\CampaignTemplate;
use App\Models\Category;
use App\Models\DeliverableOption;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CampaignCategoryService;
use App\Services\CampaignTemplateService;
use App\Services\DeliverableOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function actingBrandContext(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->brand()->create([
        'owner_id' => $user->id,
        'currency' => 'USD',
        'onboarding_completed' => true,
        'onboarding_completed_at' => now(),
    ]);

    test()->actingAs($user);
    Livewire::actingAs($user);
    app()->instance('current.workspace', $workspace);
    session(['current_workspace_id' => $workspace->id]);

    return [$user, $workspace];
}

test('campaign category service supports workspace CRUD and global copy', function () {
    [, $workspace] = actingBrandContext();

    $service = app(CampaignCategoryService::class);

    $created = $service->createWorkspaceCategory($workspace, 'Brand Originals');

    expect($created->workspace_id)->toBe($workspace->id)
        ->and($created->name)->toBe('Brand Originals');

    $updated = $service->updateWorkspaceCategory($workspace, $created, 'Brand Originals Updated');

    expect($updated->name)->toBe('Brand Originals Updated');

    $global = Category::factory()->global()->create([
        'name' => 'Video Content Global A',
        'slug' => 'video-content-global-a',
    ]);

    $copied = $service->copyGlobalCategory($workspace, $global);

    expect($copied->workspace_id)->toBe($workspace->id)
        ->and($copied->name)->toBe('Video Content Global A');

    $service->deleteWorkspaceCategory($workspace, $updated);

    $this->assertDatabaseMissing('categories', ['id' => $updated->id]);
});

test('deliverable option service supports CRUD, normalization and global copy', function () {
    [, $workspace] = actingBrandContext();

    $service = app(DeliverableOptionService::class);

    $created = $service->createWorkspaceOption($workspace, [
        'name' => 'Instagram Reel',
        'is_active' => true,
        'fields' => [
            ['key' => 'Duration Seconds', 'label' => 'Duration', 'type' => 'number', 'required' => true],
            ['key' => 'usage_rights', 'label' => 'Usage Rights', 'type' => 'select', 'options_text' => 'Organic, Paid Ads'],
        ],
    ]);

    expect($created->workspace_id)->toBe($workspace->id)
        ->and($created->fields[0]['key'])->toBe('duration_seconds')
        ->and($created->fields[1]['options'])->toBe(['Organic', 'Paid Ads']);

    $originalSlug = $created->slug;

    $updated = $service->updateWorkspaceOption($workspace, $created, [
        'name' => 'Instagram Reel Updated',
        'is_active' => false,
        'fields' => [
            ['key' => 'frames', 'label' => 'Frames', 'type' => 'number', 'required' => false],
        ],
    ]);

    expect($updated->name)->toBe('Instagram Reel Updated')
        ->and($updated->is_active)->toBeFalse()
        ->and($updated->slug)->toBe($originalSlug)
        ->and($updated->fields[0]['key'])->toBe('duration_seconds');

    $global = DeliverableOption::query()->create([
        'workspace_id' => null,
        'name' => 'TikTok Video',
        'slug' => 'tiktok_video',
        'is_active' => true,
        'fields' => [
            ['key' => 'duration_seconds', 'label' => 'Duration (seconds)', 'type' => 'number'],
        ],
    ]);

    $copy = $service->copyGlobalOption($workspace, $global);

    expect($copy->workspace_id)->toBe($workspace->id)
        ->and($copy->slug)->toBe('tiktok-video');

    $service->deleteWorkspaceOption($workspace, $updated);

    $this->assertDatabaseMissing('deliverable_options', ['id' => $updated->id]);
});

test('campaign template service supports CRUD and global copy', function () {
    [, $workspace] = actingBrandContext();

    $category = Category::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Social Media',
        'slug' => 'social-media',
    ]);

    $service = app(CampaignTemplateService::class);

    $created = $service->createWorkspaceTemplate($workspace, [
        'category_id' => $category->id,
        'name' => 'Q2 Social Sprint',
        'deliverable_options' => [
            ['deliverable_option_id' => 1, 'quantity' => 1, 'unit_price' => 5000, 'fields' => ['quantity' => 1, 'unit_price' => 5000]],
        ],
        'form_schema' => [
            'sections' => [
                ['title' => 'Campaign Details', 'fields' => [['name' => 'goal', 'label' => 'Goal', 'type' => 'text']]],
            ],
        ],
    ]);

    expect($created->workspace_id)->toBe($workspace->id)
        ->and($created->name)->toBe('Q2 Social Sprint');

    $updated = $service->updateWorkspaceTemplate($workspace, $created, [
        'name' => 'Q2 Social Sprint Updated',
        'category_id' => $category->id,
        'deliverable_options' => $created->deliverable_options,
        'form_schema' => $created->form_schema,
    ]);

    expect($updated->name)->toBe('Q2 Social Sprint Updated');

    $globalCategory = Category::factory()->global()->create([
        'name' => 'Video Content Global B',
        'slug' => 'video-content-global-b',
    ]);

    $globalTemplate = CampaignTemplate::factory()->global()->create([
        'category_id' => $globalCategory->id,
        'name' => 'Global Video Brief',
    ]);

    $copy = $service->copyGlobalTemplate($workspace, $globalTemplate->load('category'));

    expect($copy->workspace_id)->toBe($workspace->id)
        ->and($copy->name)->toBe('Global Video Brief (Copy)');

    $service->deleteWorkspaceTemplate($workspace, $updated);

    $this->assertDatabaseMissing('campaign_templates', ['id' => $updated->id]);
});

test('categories management page can create and copy categories', function () {
    [, $workspace] = actingBrandContext();

    $global = Category::factory()->global()->create([
        'name' => 'UGC Global A',
        'slug' => 'ugc-global-a',
    ]);

    Livewire::test('pages::campaigns.categories')
        ->set('formName', 'Brand Campaigns')
        ->call('saveCategory')
        ->assertHasNoErrors()
        ->call('confirmCopyGlobal', $global->id)
        ->call('copyGlobalConfirmed');

    $this->assertDatabaseHas('categories', [
        'workspace_id' => $workspace->id,
        'name' => 'Brand Campaigns',
    ]);

    $this->assertDatabaseHas('categories', [
        'workspace_id' => $workspace->id,
        'name' => 'UGC Global A',
    ]);
});

test('deliverable options management page can create and copy options', function () {
    [, $workspace] = actingBrandContext();

    $globalOption = DeliverableOption::query()->create([
        'workspace_id' => null,
        'name' => 'Instagram Story',
        'slug' => 'ig_story',
        'is_active' => true,
        'fields' => [],
    ]);

    Livewire::test('pages::campaigns.deliverable-options')
        ->set('name', 'YouTube Integration')
        ->set('fields', [
            ['key' => 'integration_minutes', 'label' => 'Integration Minutes', 'type' => 'number', 'required' => true, 'options_text' => ''],
        ])
        ->call('saveOption')
        ->assertHasNoErrors()
        ->call('confirmCopyGlobal', $globalOption->id)
        ->call('copyGlobalConfirmed');

    $this->assertDatabaseHas('deliverable_options', [
        'workspace_id' => $workspace->id,
        'slug' => 'youtube-integration',
    ]);

    $this->assertDatabaseHas('deliverable_options', [
        'workspace_id' => $workspace->id,
        'name' => 'Instagram Story',
    ]);
});

test('templates management page can create and copy templates', function () {
    [, $workspace] = actingBrandContext();

    $workspaceCategory = Category::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Social Media',
        'slug' => 'social-media',
    ]);

    $workspaceOption = DeliverableOption::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Instagram Reel',
        'slug' => 'ig_reel',
        'is_active' => true,
        'fields' => [],
    ]);

    $globalCategory = Category::factory()->global()->create([
        'name' => 'Video Content Global C',
        'slug' => 'video-content-global-c',
    ]);

    $globalTemplate = CampaignTemplate::factory()->global()->create([
        'category_id' => $globalCategory->id,
        'name' => 'Global Brief',
        'deliverable_options' => [
            [
                'deliverable_option_id' => $workspaceOption->id,
                'quantity' => 1,
                'unit_price' => 1000,
                'fields' => ['quantity' => 1, 'unit_price' => 1000],
            ],
        ],
        'form_schema' => [
            'sections' => [
                ['title' => 'Campaign Details', 'fields' => [['name' => 'goal', 'label' => 'Goal', 'type' => 'text']]],
            ],
        ],
    ]);

    Livewire::test('pages::campaigns.templates')
        ->set('name', 'Workspace Brief')
        ->set('categoryId', $workspaceCategory->id)
        ->set('briefFields', [
            ['key' => 'goal', 'label' => 'Goal', 'type' => 'text', 'options_text' => ''],
        ])
        ->set('templateDeliverables', [
            ['deliverable_option_id' => $workspaceOption->id, 'quantity' => 1, 'unit_price' => 5000],
        ])
        ->call('createTemplate')
        ->assertHasNoErrors()
        ->call('confirmCopyGlobal', $globalTemplate->id)
        ->call('copyGlobalConfirmed');

    $this->assertDatabaseHas('campaign_templates', [
        'workspace_id' => $workspace->id,
        'name' => 'Workspace Brief',
    ]);

    $this->assertDatabaseHas('campaign_templates', [
        'workspace_id' => $workspace->id,
        'name' => 'Global Brief (Copy)',
    ]);
});

test('campaign create page shows only workspace owned templates', function () {
    [, $workspace] = actingBrandContext();

    $workspaceCategory = Category::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Social Media',
        'slug' => 'social-media',
    ]);

    $globalCategory = Category::factory()->global()->create([
        'name' => 'UGC Global B',
        'slug' => 'ugc-global-b',
    ]);

    $workspaceTemplate = CampaignTemplate::factory()->create([
        'workspace_id' => $workspace->id,
        'category_id' => $workspaceCategory->id,
        'name' => 'Workspace Template',
    ]);

    CampaignTemplate::factory()->global()->create([
        'category_id' => $globalCategory->id,
        'name' => 'Global Template',
    ]);

    test()
        ->get(route('campaigns.create'))
        ->assertOk()
        ->assertSee($workspaceTemplate->name)
        ->assertDontSee('Global Template');
});

test('campaign show page status actions update campaign state', function () {
    [, $workspace] = actingBrandContext();

    $template = CampaignTemplate::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $campaign = Campaign::factory()->create([
        'workspace_id' => $workspace->id,
        'template_id' => $template->id,
        'status' => CampaignStatus::Draft,
        'is_public' => false,
    ]);

    Livewire::test('pages::campaigns.show', ['campaign' => $campaign])
        ->call('openStatusModal', 'publish')
        ->call('confirmStatusChange');

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Published)
        ->and($campaign->is_public)->toBeTrue();

    Livewire::test('pages::campaigns.show', ['campaign' => $campaign])
        ->call('openStatusModal', 'pause')
        ->call('confirmStatusChange');

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Paused)
        ->and($campaign->is_public)->toBeTrue();

    Livewire::test('pages::campaigns.show', ['campaign' => $campaign])
        ->call('openStatusModal', 'close')
        ->call('confirmStatusChange');

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Closed)
        ->and($campaign->is_public)->toBeFalse();
});

test('campaign show page visibility action toggles public flag', function () {
    [, $workspace] = actingBrandContext();

    $template = CampaignTemplate::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $campaign = Campaign::factory()->create([
        'workspace_id' => $workspace->id,
        'template_id' => $template->id,
        'status' => CampaignStatus::Published,
        'is_public' => true,
    ]);

    Livewire::test('pages::campaigns.show', ['campaign' => $campaign])
        ->call('openVisibilityModal', 'private')
        ->call('confirmVisibilityChange');

    $campaign->refresh();

    expect($campaign->is_public)->toBeFalse()
        ->and($campaign->status)->toBe(CampaignStatus::Published);
});

test('campaign show page lists campaign slots', function () {
    [, $workspace] = actingBrandContext();

    $campaign = Campaign::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $creatorWorkspace = Workspace::factory()->creator()->create();
    $product = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'is_active' => true,
        'is_public' => true,
    ]);

    CampaignSlot::query()->create([
        'campaign_id' => $campaign->id,
        'application_id' => null,
        'creator_workspace_id' => $creatorWorkspace->id,
        'product_id' => $product->id,
        'status' => CampaignSlotStatus::Pending,
        'deliverables' => $campaign->deliverables,
        'content_brief' => $campaign->content_brief,
    ]);

    Livewire::test('pages::campaigns.show', ['campaign' => $campaign])
        ->assertDontSee('No slots created yet')
        ->assertSee($creatorWorkspace->name);
});
