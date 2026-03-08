<?php

use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;

if (! function_exists('currentWorkspace')) {
    function currentWorkspace(): ?\App\Models\Workspace
    {
        if (app()->bound('current.workspace')) {
            return app('current.workspace');
        }

        if (session('current_workspace_id')) {
            return \App\Models\Workspace::find(session('current_workspace_id'));
        }

        if (Auth::check()) {
            return Auth::user()->currentWorkspace();
        }

        return null;
    }
}

if (! function_exists('isCreatorWorkspace')) {
    function isCreatorWorkspace(): bool
    {
        $workspace = currentWorkspace();

        return $workspace && $workspace->isCreator();
    }
}

if (! function_exists('isBrandWorkspace')) {
    function isBrandWorkspace(): bool
    {
        $workspace = currentWorkspace();

        return $workspace && $workspace->isBrand();
    }
}

if (! function_exists('formatMoney')) {
    function formatMoney(float $amount, ?Workspace $workspace = null, ?string $currency = null): string
    {
        if (! isset($workspace)) {
            $workspace = currentWorkspace();
        }

        if ($currency) {
            return \App\Support\CurrencySupport::formatCurrency($amount, $currency);
        }

        if ($workspace) {
            return $workspace->formatCurrency($amount);
        }

        return \App\Support\CurrencySupport::formatCurrency($amount, 'USD');
    }
}

if (! function_exists('formatWorkspaceDate')) {
    function formatWorkspaceDate(\Carbon\CarbonInterface $date): string
    {
        $workspace = currentWorkspace();

        if ($workspace) {
            return $workspace->formatDate($date);
        }

        return $date->format('M d, Y');
    }
}

if (! function_exists('formatWorkspaceTime')) {
    function formatWorkspaceTime(\Carbon\CarbonInterface $time): string
    {
        $workspace = currentWorkspace();

        if ($workspace) {
            return $workspace->formatTime($time);
        }

        return $time->format('g:i A');
    }
}

if (! function_exists('getRecommendedProvider')) {
    function getRecommendedProvider(string $brandCountry = 'global'): string
    {
        $workspace = currentWorkspace();

        if ($workspace) {
            return $workspace->getRecommendedProvider($brandCountry);
        }

        return 'stripe';
    }
}
