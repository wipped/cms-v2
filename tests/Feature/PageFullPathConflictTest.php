<?php

use App\Exceptions\PageFullPathConflictException;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents saving a page when its full path already exists in the same locale', function () {
    Page::factory()->create([
        'slug' => 'about',
        'full_path' => 'about',
        'title' => 'About us',
    ]);

    expect(fn () => Page::factory()->create([
        'slug' => 'about',
        'full_path' => 'about',
        'title' => 'About page',
    ]))->toThrow(PageFullPathConflictException::class, 'conflicts with');
});

it('allows the same full path in a different locale', function () {
    Page::factory()->create([
        'locale' => 'nl',
        'slug' => 'about',
        'full_path' => 'about',
    ]);

    $page = Page::factory()->create([
        'locale' => 'en',
        'slug' => 'about',
        'full_path' => 'about',
    ]);

    expect($page)->toBeInstanceOf(Page::class);
});

it('prevents promoting a child when its new root path conflicts', function () {
    Page::factory()->create([
        'slug' => 'webdesign',
        'full_path' => 'webdesign',
        'title' => 'Existing webdesign',
    ]);

    $parent = Page::factory()->create([
        'slug' => 'diensten',
        'full_path' => 'diensten',
    ]);

    $child = Page::factory()->create([
        'parent_id' => $parent->id,
        'slug' => 'webdesign',
        'full_path' => 'diensten/webdesign',
        'title' => 'Webdesign child',
    ]);

    expect(fn () => $child->update(['parent_id' => null]))
        ->toThrow(PageFullPathConflictException::class, '#');

    expect($child->fresh())
        ->parent_id->toBe($parent->id)
        ->full_path->toBe('diensten/webdesign');
});

it('prevents moving a parent when a descendant path would conflict', function () {
    Page::factory()->create([
        'slug' => 'webdesign',
        'full_path' => 'target/segment/webdesign',
        'title' => 'Existing page',
    ]);

    $parent = Page::factory()->create([
        'slug' => 'segment',
        'full_path' => 'segment',
    ]);

    Page::factory()->create([
        'parent_id' => $parent->id,
        'slug' => 'webdesign',
        'full_path' => 'segment/webdesign',
    ]);

    $target = Page::factory()->create([
        'slug' => 'target',
        'full_path' => 'target',
    ]);

    expect(fn () => $parent->update(['parent_id' => $target->id]))
        ->toThrow(PageFullPathConflictException::class, 'conflicts with');

    expect($parent->fresh())
        ->parent_id->toBeNull()
        ->full_path->toBe('segment');
});

it('updates descendant full paths when a move succeeds', function () {
    $parent = Page::factory()->create([
        'slug' => 'diensten',
        'full_path' => 'diensten',
    ]);

    $child = Page::factory()->create([
        'parent_id' => $parent->id,
        'slug' => 'webdesign',
        'full_path' => 'diensten/webdesign',
    ]);

    $newParent = Page::factory()->create([
        'slug' => 'aanbod',
        'full_path' => 'aanbod',
    ]);

    $parent->update(['parent_id' => $newParent->id]);

    expect($parent->fresh())
        ->parent_id->toBe($newParent->id)
        ->full_path->toBe('aanbod/diensten');

    expect($child->fresh())
        ->full_path->toBe('aanbod/diensten/webdesign');
});

it('lists conflicting pages on the exception', function () {
    $existing = Page::factory()->create([
        'slug' => 'about',
        'full_path' => 'about',
        'title' => 'Existing about',
    ]);

    try {
        Page::factory()->create([
            'slug' => 'about',
            'full_path' => 'about',
            'title' => 'New about',
        ]);
    } catch (PageFullPathConflictException $exception) {
        expect($exception->fullPath)->toBe('about')
            ->and($exception->conflictingPages)->toHaveCount(1)
            ->and($exception->conflictingPages->first()->is($existing))->toBeTrue()
            ->and($exception->getMessage())->toContain('Existing about');

        return;
    }

    expect(false)->toBeTrue('Expected PageFullPathConflictException to be thrown.');
});
