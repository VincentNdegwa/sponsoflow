<?php

use App\Support\CurrencySupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Localization settings')] class extends Component {
    public string $country_code = '';
    public string $currency = '';
    public string $timezone = '';
    public string $date_format = '';
    public string $time_format = '';
    
    public function mount(): void
    {
        $workspace = $this->getCurrentWorkspace();
        
        if ($workspace) {
            $this->country_code = $workspace->country_code ?? 'US';
            $this->currency = $workspace->currency ?? 'USD';
            $this->timezone = $workspace->timezone ?? 'UTC';
            $this->date_format = $workspace->date_format ?? 'M d, Y';
            $this->time_format = $workspace->time_format ?? 'g:i A';
        }
    }

    public function save(): void
    {
        $this->validate([
            'timezone' => 'required|string',
            'date_format' => 'required|string',
            'time_format' => 'required|string',
        ]);

        try {
            $workspace = $this->getCurrentWorkspace();
            
            if (!$workspace) {
                throw new \Exception('No workspace found.');
            }

            $workspace->update([
                'timezone' => $this->timezone,
                'date_format' => $this->date_format,
                'time_format' => $this->time_format,
            ]);

            session()->flash('status', 'Localization settings updated successfully!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update settings: ' . $e->getMessage());
        }
    }



    #[Computed]
    public function timezones(): array
    {
        return [
            'UTC' => 'UTC - Coordinated Universal Time',
            'America/New_York' => 'EST/EDT - Eastern Time (US)',
            'America/Chicago' => 'CST/CDT - Central Time (US)',
            'America/Denver' => 'MST/MDT - Mountain Time (US)',
            'America/Los_Angeles' => 'PST/PDT - Pacific Time (US)',
            'America/Toronto' => 'EST/EDT - Eastern Time (Canada)',
            'Europe/London' => 'GMT/BST - London Time',
            'Europe/Paris' => 'CET/CEST - Central European Time',
            'Europe/Berlin' => 'CET/CEST - Central European Time',
            'Africa/Lagos' => 'WAT - West Africa Time (Nigeria)',
            'Africa/Accra' => 'GMT - Ghana Mean Time',
            'Africa/Johannesburg' => 'SAST - South Africa Standard Time',
            'Africa/Nairobi' => 'EAT - East Africa Time (Kenya)',
        ];
    }

    #[Computed]
    public function dateFormats(): array
    {
        $now = now();
        return [
            'M d, Y' => $now->format('M d, Y') . ' (Jan 15, 2026)',
            'd/m/Y' => $now->format('d/m/Y') . ' (15/01/2026)',
            'Y-m-d' => $now->format('Y-m-d') . ' (2026-01-15)',
            'F jS, Y' => $now->format('F jS, Y') . ' (January 15th, 2026)',
            'd M Y' => $now->format('d M Y') . ' (15 Jan 2026)',
        ];
    }

    #[Computed]
    public function timeFormats(): array
    {
        $now = now()->setTime(14, 30);
        return [
            'g:i A' => $now->format('g:i A') . ' (2:30 PM)',
            'G:i' => $now->format('G:i') . ' (14:30)',
            'h:i A' => $now->format('h:i A') . ' (02:30 PM)',
            'H:i' => $now->format('H:i') . ' (14:30)',
        ];
    }

    private function getCurrentWorkspace()
    {
        return currentWorkspace();
    }


}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Localization settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Localization')" :subheading="__('Configure your date and time formats')">
        
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:select wire:model="timezone" label="Timezone" placeholder="Select timezone">
                    @foreach($this->timezones as $tz => $label)
                        <flux:select.option value="{{ $tz }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:select wire:model="date_format" label="Date Format" placeholder="Select date format">
                    @foreach($this->dateFormats as $format => $example)
                        <flux:select.option value="{{ $format }}">{{ $example }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:select wire:model="time_format" label="Time Format" placeholder="Select time format">
                    @foreach($this->timeFormats as $format => $example)
                        <flux:select.option value="{{ $format }}">{{ $example }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">
                    Save Settings
                </flux:button>
            </div>
        </form>

        @if (session('status'))
            <div class="mt-4 p-4 bg-primary-50 border border-primary-200 rounded-md">
                <flux:text class="text-primary-800">{{ session('status') }}</flux:text>
            </div>
        @endif

        @if (session('error'))
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                <flux:text class="text-red-800">{{ session('error') }}</flux:text>
            </div>
        @endif
    </x-pages::settings.layout>
</section>