<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public int $reportId)
    {
    }
}