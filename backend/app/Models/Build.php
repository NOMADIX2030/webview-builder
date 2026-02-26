<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Build extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'status',
        'app_type',
        'web_url',
        'app_name',
        'package_id',
        'version_name',
        'version_code',
        'privacy_policy_url',
        'support_url',
        'app_icon_path',
        'splash_image_path',
        'config_json',
        'apk_path',
        'ipa_path',
        'keystore_path',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'version_code' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
