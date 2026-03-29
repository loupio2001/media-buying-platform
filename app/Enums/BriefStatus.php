<?php

namespace App\Enums;

enum BriefStatus: string
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case RevisionRequested = 'revision_requested';
}