<?php

namespace App\Models;

use App\Support\CurrencySupport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'owner_id',
        'description',
        'custom_domain',
        'is_active',
        'settings',
        'payment_bank_details',
        'country_code',
        'currency',
        'timezone',
        'date_format',
        'time_format',
        'onboarding_completed',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'payment_bank_details' => 'array',
            'onboarding_completed' => 'boolean',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user', 'team_id', 'user_id')
            ->withPivot('role_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function paymentConfigurations(): HasMany
    {
        return $this->hasMany(PaymentConfiguration::class);
    }

    public function activePaymentConfiguration(string $provider = 'stripe'): ?PaymentConfiguration
    {
        return $this->paymentConfigurations()
            ->forProvider($provider)
            ->active()
            ->verified()
            ->first();
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(WorkspaceRating::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function campaignTemplates(): HasMany
    {
        return $this->hasMany(CampaignTemplate::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function deliverableOptions(): HasMany
    {
        return $this->hasMany(DeliverableOption::class);
    }

    public function canReceivePayments(string $provider = 'stripe'): bool
    {
        return $this->activePaymentConfiguration($provider)?->canReceivePayments() ?? false;
    }

    public function isCreator(): bool
    {
        return $this->type === 'creator';
    }

    public function isBrand(): bool
    {
        return $this->type === 'brand';
    }

    public function getRecommendedProvider(string $brandCountry = 'global'): string
    {
        return CurrencySupport::getRecommendedProvider($this->country_code, $brandCountry);
    }

    public function formatCurrency(float $amount): string
    {
        return CurrencySupport::formatCurrency($amount, $this->currency);
    }

    public function getAvailableProviders(): array
    {
        return CurrencySupport::getAvailableProviders($this->country_code);
    }

    public function supportsProvider(string $provider): bool
    {
        return CurrencySupport::isCurrencySupportedByProvider($this->currency, $provider);
    }

    public function getSupportedBanks(?string $provider = null): array
    {
        $provider = $provider ?: $this->getRecommendedProvider();

        if ($provider === 'paystack') {
            $paystackProvider = app(\App\Services\Providers\PaystackPaymentProvider::class);

            return $paystackProvider->getSupportedBanks($this->country_code);
        }

        return []; // Stripe doesn't provide bank list endpoint
    }

    /**
     * Format date using workspace's format
     */
    public function formatDate(\Carbon\CarbonInterface $date): string
    {
        return $date->setTimezone($this->timezone)->format($this->date_format);
    }

    /**
     * Format time using workspace's format
     */
    public function formatTime(\Carbon\CarbonInterface $time): string
    {
        return $time->setTimezone($this->timezone)->format($this->time_format);
    }

    /**
     * Check if onboarding is completed
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed;
    }

    /**
     * Mark onboarding as completed
     */
    public function completeOnboarding(): void
    {
        $this->update([
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ]);
    }

    /**
     * Check if workspace needs onboarding
     */
    public function needsOnboarding(): bool
    {
        return ! $this->hasCompletedOnboarding() && $this->isCreator();
    }

    /**
     * Check if localization is configured
     */
    public function hasLocalizationConfigured(): bool
    {
        return ! empty($this->country_code) && ! empty($this->currency);
    }

    /**
     * Check if payment system is configured
     */
    public function hasPaymentConfigured(): bool
    {
        $recommendedProvider = $this->getRecommendedProvider();

        return $this->activePaymentConfiguration($recommendedProvider) !== null;
    }

    /**
     * Get onboarding completion percentage
     */
    public function getOnboardingProgress(): array
    {
        $steps = [
            'localization' => $this->hasLocalizationConfigured(),
            'payment' => $this->hasPaymentConfigured(),
        ];

        $completed = count(array_filter($steps));
        $total = count($steps);

        return [
            'steps' => $steps,
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = \Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
