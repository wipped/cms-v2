<?php

namespace App\Models;

use App\Exceptions\PageFullPathConflictException;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'parent_id',
    'locale',
    'slug',
    'full_path',
    'title',
    'published_version_id',
    'sort_order',
    'is_visible',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            if ($page->isDirty(['slug', 'parent_id', 'locale'])) {
                $page->full_path = $page->calculateFullPath();
            }

            if ($page->isDirty(['slug', 'parent_id', 'locale', 'full_path'])) {
                $page->assertFullPathIsAvailable();

                if ($page->exists) {
                    $page->assertDescendantFullPathsAreAvailable();
                }
            }
        });

        static::saved(function (Page $page): void {
            if ($page->wasChanged(['slug', 'parent_id', 'locale'])) {
                $page->updateDescendantFullPaths();
            }
        });
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<PageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class)->orderByDesc('version_number');
    }

    /**
     * @return BelongsTo<PageVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'published_version_id');
    }

    /**
     * @return HasOne<PageAutosave, $this>
     */
    public function autosave(): HasOne
    {
        return $this->hasOne(PageAutosave::class);
    }

    public function calculateFullPath(): string
    {
        if ($this->parent_id === null && blank($this->slug)) {
            return '';
        }

        if ($this->parent_id === null) {
            return $this->slug;
        }

        if ($this->parent_id === $this->id) {
            throw new \LogicException(
                "Page [{$this->id}] has same parent_id [{$this->parent_id}] as itself."
            );
        }

        $parent = ($this->relationLoaded('parent') && $this->parent !== null)
            ? $this->parent
            : self::query()->withTrashed()->find($this->parent_id);

        if ($parent === null) {
            throw new \LogicException(
                "Page [{$this->id}] has invalid parent_id [{$this->parent_id}]."
            );
        }

        $parentPath = $parent->full_path;

        return $parentPath === ''
            ? $this->slug
            : $parentPath.'/'.$this->slug;
    }

    /**
     * @return Collection<int, Page>
     */
    public function conflictingPagesForPath(?string $fullPath = null): Collection
    {
        $fullPath ??= $this->full_path;

        return self::query()
            ->withTrashed()
            ->where('locale', $this->locale)
            ->where('full_path', $fullPath)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->id))
            ->get();
    }

    public function assertFullPathIsAvailable(?string $fullPath = null): void
    {
        $fullPath ??= $this->full_path;
        $conflictingPages = $this->conflictingPagesForPath($fullPath);

        if ($conflictingPages->isNotEmpty()) {
            throw new PageFullPathConflictException($this, $fullPath, $conflictingPages);
        }
    }

    public function assertDescendantFullPathsAreAvailable(): void
    {
        foreach ($this->descendantFullPathUpdates() as $descendant) {
            $descendant->assertFullPathIsAvailable();
        }
    }

    public function updateDescendantFullPaths(): void
    {
        foreach ($this->descendantFullPathUpdates() as $descendant) {
            $descendant->saveQuietly();
        }
    }

    /**
     * @return Collection<int, Page>
     */
    protected function descendantFullPathUpdates(): Collection
    {
        $pages = collect();

        $this->loadMissing('children');

        foreach ($this->children as $child) {
            $child->setRelation('parent', $this);
            $child->full_path = $child->calculateFullPath();
            $pages->push($child);
            $pages = $pages->merge($child->descendantFullPathUpdates());
        }

        return $pages;
    }

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
