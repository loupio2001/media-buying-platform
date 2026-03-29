<?php

namespace App\Enums;

enum CampaignObjective: string
{
    case Awareness = 'awareness';
    case Reach = 'reach';
    case Traffic = 'traffic';
    case Leads = 'leads';
    case Conversions = 'conversions';
    case Engagement = 'engagement';
    case AppInstalls = 'app_installs';
    case VideoViews = 'video_views';
}