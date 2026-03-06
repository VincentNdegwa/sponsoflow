<?php

namespace App\Support;

class Toast
{
    public static function success(string $title, string $message): void
    {
        session()->flash('toast', [
            'type' => 'success',
            'title' => $title,
            'message' => $message,
        ]);
    }

    public static function error(string $title, string $message): void
    {
        session()->flash('toast', [
            'type' => 'error',
            'title' => $title,
            'message' => $message,
        ]);
    }

    public static function warning(string $title, string $message): void
    {
        session()->flash('toast', [
            'type' => 'warning',
            'title' => $title,
            'message' => $message,
        ]);
    }

    public static function info(string $title, string $message): void
    {
        session()->flash('toast', [
            'type' => 'info',
            'title' => $title,
            'message' => $message,
        ]);
    }
}