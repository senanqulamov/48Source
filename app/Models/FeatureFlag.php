<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    public const DEFAULTS = [
        'split_awards' => ['Split awards: award one request item by item to several suppliers', true],
        'supplier_registration' => ['Suppliers can register themselves', true],
        'email_notifications' => ['Send every notification by email as well (see the email outbox)', true],
    ];

    /** Database defaults, mirrored so a fresh model has them before it is reloaded. */
    protected $attributes = ['enabled' => false];

    protected $fillable = ['key', 'label', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public static function isOn(string $key): bool
    {
        $row = self::where('key', $key)->first();

        return $row ? $row->enabled : (self::DEFAULTS[$key][1] ?? false);
    }
}
