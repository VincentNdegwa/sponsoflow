@props([
    'brief' => [],
    'title' => 'The Ask',
    'description' => 'What the brand is asking for.',
])

@php
    $contentBrief = is_array($brief) ? $brief : [];
    $sections = (array) data_get($contentBrief, '_form_schema.sections', []);

    $fieldLabels = [];
    foreach ($sections as $section) {
        foreach ((array) data_get($section, 'fields', []) as $field) {
            $fieldName = (string) data_get($field, 'name', '');
            if ($fieldName === '') {
                continue;
            }

            $fieldLabels[$fieldName] = (string) data_get($field, 'label', $fieldName);
        }
    }

    if (! empty($sections)) {
        $answers = \App\Support\CampaignBookingPayloadFormatter::extractAnswersFromContentBrief($contentBrief, $sections);
    } else {
        $answers = array_filter($contentBrief, static fn (mixed $value, string $key): bool => $key !== '_form_schema', ARRAY_FILTER_USE_BOTH);
    }

    $formatLabel = static fn (string $key): string => (string) \Illuminate\Support\Str::of($key)->replace('_', ' ')->headline();

    $formatValue = static function (mixed $value): string {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            $flat = [];
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $flat[] = trim((string) $item);
                    continue;
                }

                if (is_array($item)) {
                    $label = data_get($item, 'label');
                    $val = data_get($item, 'value');

                    if (is_scalar($label)) {
                        $flat[] = trim((string) $label);
                    }

                    if (is_scalar($val)) {
                        $flat[] = trim((string) $val);
                    }
                }
            }

            $flat = array_values(array_filter($flat, static fn (string $item): bool => $item !== ''));

            return $flat !== [] ? implode(', ', $flat) : 'Details provided.';
        }

        return 'Details provided.';
    };

    $displayAnswers = [];
    foreach ((array) $answers as $key => $value) {
        $label = $fieldLabels[$key] ?? $formatLabel((string) $key);
        $valueText = $formatValue($value);

        if ($valueText === '') {
            continue;
        }

        $displayAnswers[] = [
            'label' => $label,
            'value' => $valueText,
        ];
    }
@endphp

<section class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
    <div class="mb-4 flex items-center justify-between">
        <flux:heading size="lg">{{ $title }}</flux:heading>
        <flux:text size="sm" class="text-zinc-500">{{ $description }}</flux:text>
    </div>

    @if($displayAnswers === [])
        <div class="border border-dashed border-zinc-300 p-5 text-center text-sm text-zinc-500 dark:border-zinc-700">
            No content brief provided yet.
        </div>
    @else
        <div class="space-y-3">
            @foreach($displayAnswers as $answer)
                <div class="border-l-2 border-zinc-200 pl-4 dark:border-zinc-700">
                    <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        {{ $answer['label'] }}
                    </flux:text>
                    <flux:text class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                        {{ $answer['value'] }}
                    </flux:text>
                </div>
            @endforeach
        </div>
    @endif
</section>
