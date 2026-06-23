<?php

namespace App\Models;

use App\Enums\PageVersionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'page_id',
    'version_number',
    'label',
    'title',
    'meta_title',
    'meta_description',
    'blocks',
    'status',
    'scheduled_publish_at',
    'published_at',
    'created_by',
])]
class PageVersion extends Model
{
    protected static function booted(): void
    {
        static::creating(function (PageVersion $version): void {
            if ($version->version_number === null) {
                $maxVersion = self::query()
                    ->where('page_id', $version->page_id)
                    ->max('version_number');

                $version->version_number = ((int) $maxVersion) + 1;
            }
        });
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'status' => PageVersionStatus::class,
            'scheduled_publish_at' => 'datetime',
            'published_at' => 'datetime',
            'version_number' => 'integer',
        ];
    }
}
