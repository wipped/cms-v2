<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'page_id',
    'user_id',
    'based_on_version_id',
    'title',
    'meta_title',
    'meta_description',
    'blocks',
])]
class PageAutosave extends Model
{
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PageVersion, $this>
     */
    public function basedOnVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'based_on_version_id');
    }

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'autosaved_at' => 'datetime',
        ];
    }
}
