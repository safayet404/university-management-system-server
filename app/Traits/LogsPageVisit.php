<?php

namespace App\Traits;

use App\Models\PageVisit;
use Illuminate\Support\Facades\Request;

trait LogsPageVisit
{
    /**
     * Log a page visit / action
     */
    public static function logVisit(
        string $module,
        string $section,
        string $action,
        string $description = '',
        array $oldValues = [],
        array $newValues = [],
        $modelType = null,
        $modelId = null
    ): void {
        $ua      = Request::userAgent() ?? '';
        $browser = self::parseBrowser($ua);
        $device  = self::parseDevice($ua);

        PageVisit::create([
            'user_id'     => auth()->id(),
            'module'      => $module,
            'section'     => $section,
            'action'      => $action,
            'url'         => Request::fullUrl(),
            'method'      => Request::method(),
            'description' => $description,
            'old_values'  => $oldValues ?: null,
            'new_values'  => $newValues ?: null,
            'model_type'  => $modelType,
            'model_id'    => $modelId,
            'ip_address'  => Request::ip(),
            'user_agent'  => $ua,
            'browser'     => $browser,
            'device'      => $device,
        ]);
    }

    private static function parseBrowser(string $ua): string
    {
        if (str_contains($ua, 'Chrome'))  return 'Chrome';
        if (str_contains($ua, 'Firefox')) return 'Firefox';
        if (str_contains($ua, 'Safari'))  return 'Safari';
        if (str_contains($ua, 'Edge'))    return 'Edge';
        if (str_contains($ua, 'Opera'))   return 'Opera';
        return 'Unknown';
    }

    private static function parseDevice(string $ua): string
    {
        if (str_contains($ua, 'Mobile'))  return 'Mobile';
        if (str_contains($ua, 'Tablet'))  return 'Tablet';
        return 'Desktop';
    }
}
