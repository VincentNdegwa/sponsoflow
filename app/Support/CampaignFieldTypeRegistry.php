<?php

namespace App\Support;

class CampaignFieldTypeRegistry
{
    public static function all(): array
    {
        return (array) config('campaign-field-types.types', []);
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::keys());
    }

    public static function selectOptions(): array
    {
        $options = [];

        foreach (self::all() as $key => $definition) {
            $options[] = [
                'value' => $key,
                'label' => (string) data_get($definition, 'label', ucfirst($key)),
                'requires_options' => (bool) data_get($definition, 'requires_options', false),
                'supports_multiple' => (bool) data_get($definition, 'supports_multiple', false),
            ];
        }

        return $options;
    }

    public static function requiresOptions(string $type): bool
    {
        return (bool) data_get(self::all(), $type.'.requires_options', false);
    }

    public static function supportsMultiple(string $type): bool
    {
        return (bool) data_get(self::all(), $type.'.supports_multiple', false);
    }
}
