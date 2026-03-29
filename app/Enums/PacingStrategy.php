<?php

namespace App\Enums;

enum PacingStrategy: string
{
    case Even = 'even';
    case FrontLoaded = 'front_loaded';
    case BackLoaded = 'back_loaded';
    case Custom = 'custom';
}