@props([
    'campaignDetails' => null,
    'campaignDeliverables' => null,
    'requirementData' => null,
    'requirements' => null,
])

@php
    $formatLabel = static fn (string $key): string => (string) \Illuminate\Support\Str::of($key)->replace('_', ' ')->headline();

    $formatValue = static function (mixed $value): string {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $hasNestedArray = collect($value)->contains(static fn (mixed $item): bool => is_array($item));

            if (! $hasNestedArray) {
                return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
            }

            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $encoded === false ? '[complex data]' : $encoded;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '[complex data]' : $encoded;
    };

    $campaign = is_array($campaignDetails) ? $campaignDetails : [];
    $campaignMeta = (array) data_get($campaign, 'meta', []);
    $campaignSections = (array) data_get($campaign, 'form_schema.sections', []);
    $campaignAnswers = (array) data_get($campaign, 'answers', []);

    $deliverables = is_array($campaignDeliverables) ? $campaignDeliverables : [];
    $productRequirements = is_array($requirementData) ? $requirementData : [];
    $productRequirementModels = $requirements instanceof \Illuminate\Support\Collection ? $requirements : collect();
@endphp

@if(! empty($campaign))
    <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="mb-4 flex items-center gap-2">
            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                <flux:icon.document-text class="h-5 w-5 text-accent" />
            </div>
            <div>
                <flux:heading size="lg">Campaign Details</flux:heading>
                <flux:text class="text-sm text-zinc-500">Schema and answers used for this booking</flux:text>
            </div>
        </div>

        @if(! empty($campaignMeta))
            <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach($campaignMeta as $key => $value)
                    @if(filled($value))
                        <article class="rounded-lg border border-zinc-200 bg-zinc-50/70 p-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                            <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $formatLabel((string) $key) }}</flux:text>
                            <flux:text class="mt-1 text-sm leading-6 wrap-break-word whitespace-pre-wrap">{{ $formatValue($value) }}</flux:text>
                        </article>
                    @endif
                @endforeach
            </div>
        @endif

        @if(! empty($campaignSections))
            <div class="space-y-4">
                @foreach($campaignSections as $section)
                    <article class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm" class="mb-3">{{ data_get($section, 'title', 'Campaign Section') }}</flux:heading>

                        <div class="space-y-3">
                            @foreach((array) data_get($section, 'fields', []) as $field)
                                @php
                                    $fieldName = (string) data_get($field, 'name', '');
                                    $fieldLabel = (string) data_get($field, 'label', $formatLabel($fieldName));
                                    $fieldValue = $fieldName !== '' ? data_get($campaignAnswers, $fieldName) : null;
                                @endphp

                                @if($fieldName !== '')
                                    <div class="rounded-md border border-zinc-200 bg-zinc-50/60 p-3 dark:border-zinc-700 dark:bg-zinc-900/30">
                                        <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $fieldLabel }}</flux:text>
                                        <flux:text class="mt-1 text-sm leading-6 wrap-break-word whitespace-pre-wrap">{{ filled($fieldValue) ? $formatValue($fieldValue) : 'Not provided' }}</flux:text>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endif

@if(! empty($deliverables))
    <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="mb-4 flex items-center gap-2">
            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                <flux:icon.clipboard-document-check class="h-5 w-5 text-accent" />
            </div>
            <div>
                <flux:heading size="lg">Campaign Deliverables</flux:heading>
                <flux:text class="text-sm text-zinc-500">Deliverables snapshot linked to this booking</flux:text>
            </div>
        </div>

        <div class="space-y-3">
            @foreach($deliverables as $deliverable)
                <article class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <flux:text class="font-medium">{{ data_get($deliverable, 'label', 'Deliverable') }}</flux:text>
                        <flux:badge size="sm" color="zinc">Qty: {{ (int) data_get($deliverable, 'qty', 0) }}</flux:badge>
                    </div>

                    @php $deliverableFields = (array) data_get($deliverable, 'fields', []); @endphp
                    @if(! empty($deliverableFields))
                        <div class="space-y-2">
                            @foreach($deliverableFields as $fieldKey => $fieldValue)
                                <div class="rounded-md border border-zinc-200 bg-zinc-50/60 p-3 dark:border-zinc-700 dark:bg-zinc-900/30">
                                    <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $formatLabel((string) $fieldKey) }}</flux:text>
                                    <flux:text class="mt-1 text-sm leading-6 wrap-break-word whitespace-pre-wrap">{{ $formatValue($fieldValue) }}</flux:text>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif

@if(! empty($productRequirements))
    <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="mb-4 flex items-center gap-2">
            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                <flux:icon.clipboard-document-list class="h-5 w-5 text-accent" />
            </div>
            <div>
                <flux:heading size="lg">Product Requirements</flux:heading>
                <flux:text class="text-sm text-zinc-500">Provided by brand</flux:text>
            </div>
        </div>

        <div class="space-y-2">
            @foreach($productRequirements as $key => $value)
                @if($value && $key !== 'guest_brand_profile')
                    @php
                        $requirement = $productRequirementModels->firstWhere('id', (int) $key);
                        $questionLabel = $requirement?->name ?? $formatLabel((string) $key);
                    @endphp
                    <div class="rounded-md border border-zinc-200 bg-zinc-50/60 p-3 dark:border-zinc-700 dark:bg-zinc-900/30">
                        <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $questionLabel }}</flux:text>
                        <flux:text class="mt-1 text-sm leading-6 wrap-break-word whitespace-pre-wrap">{{ $formatValue($value) }}</flux:text>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
@endif
