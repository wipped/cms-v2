<?php

namespace App\Enums;

enum PageVersionStatus: string
{
    case Saved = 'saved';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';
}
