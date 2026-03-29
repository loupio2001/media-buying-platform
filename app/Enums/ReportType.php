<?php

namespace App\Enums;

enum ReportType: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Mid = 'mid';
    case End = 'end';
    case Custom = 'custom';
}