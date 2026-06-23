<?php

namespace App\Exceptions;

use App\Models\Page;
use Illuminate\Support\Collection;
use RuntimeException;

class PageFullPathConflictException extends RuntimeException
{
    /**
     * @param  Collection<int, Page>  $conflictingPages
     */
    public function __construct(
        public readonly Page $page,
        public readonly string $fullPath,
        public readonly Collection $conflictingPages,
    ) {
        parent::__construct($this->buildMessage());
    }

    private function buildMessage(): string
    {
        $conflicts = $this->conflictingPages
            ->map(fn (Page $page): string => sprintf(
                '#%d "%s" (%s)',
                $page->id,
                $page->title,
                $page->full_path,
            ))
            ->implode(', ');

        return sprintf(
            'The path "%s" for page #%d conflicts with: %s.',
            $this->fullPath,
            $this->page->id ?? 0,
            $conflicts,
        );
    }
}
