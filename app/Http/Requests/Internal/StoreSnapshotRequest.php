<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

class StoreSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::snapshotRules();
    }

    public static function snapshotRules(string $prefix = ''): array
    {
        return [
            "{$prefix}ad_id" => 'required|exists:ads,id',
            "{$prefix}snapshot_date" => 'required|date',
            "{$prefix}granularity" => 'required|in:daily,cumulative',
            "{$prefix}impressions" => 'nullable|integer|min:0',
            "{$prefix}reach" => 'nullable|integer|min:0',
            "{$prefix}frequency" => 'nullable|numeric|min:0',
            "{$prefix}clicks" => 'nullable|integer|min:0',
            "{$prefix}link_clicks" => 'nullable|integer|min:0',
            "{$prefix}landing_page_views" => 'nullable|integer|min:0',
            "{$prefix}ctr" => 'nullable|numeric|min:0',
            "{$prefix}spend" => 'nullable|numeric|min:0',
            "{$prefix}cpm" => 'nullable|numeric|min:0',
            "{$prefix}cpc" => 'nullable|numeric|min:0',
            "{$prefix}conversions" => 'nullable|integer|min:0',
            "{$prefix}cpa" => 'nullable|numeric|min:0',
            "{$prefix}leads" => 'nullable|integer|min:0',
            "{$prefix}cpl" => 'nullable|numeric|min:0',
            "{$prefix}video_views" => 'nullable|integer|min:0',
            "{$prefix}video_completions" => 'nullable|integer|min:0',
            "{$prefix}vtr" => 'nullable|numeric|min:0',
            "{$prefix}engagement" => 'nullable|integer|min:0',
            "{$prefix}engagement_rate" => 'nullable|numeric|min:0',
            "{$prefix}thumb_stop_rate" => 'nullable|numeric|min:0',
            "{$prefix}custom_metrics" => 'nullable|array',
            "{$prefix}raw_response" => 'nullable|array',
            "{$prefix}source" => 'nullable|in:api,manual',
        ];
    }
}
