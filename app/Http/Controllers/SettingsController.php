<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    use LogsPageVisit;

    // GET /api/settings — all settings grouped
    public function index()
    {
        self::logVisit('settings', 'view', 'visited', 'Viewed settings');

        $settings = Setting::all()->groupBy('group')->map(fn($group) =>
            $group->map(fn($s) => [
                'key'         => $s->key,
                'value'       => $this->castValue($s->value, $s->type),
                'type'        => $s->type,
                'label'       => $s->label,
                'description' => $s->description,
                'is_public'   => $s->is_public,
            ])->keyBy('key')
        );

        return response()->json(['success' => true, 'data' => $settings]);
    }

    // GET /api/settings/{group} — single group
    public function group(string $group)
    {
        $settings = Setting::where('group', $group)->get()
            ->map(fn($s) => [
                'key'         => $s->key,
                'value'       => $this->castValue($s->value, $s->type),
                'type'        => $s->type,
                'label'       => $s->label,
                'description' => $s->description,
            ])->keyBy('key');

        return response()->json(['success' => true, 'data' => $settings]);
    }

    // PUT /api/settings/{group} — update a group
    public function update(Request $request, string $group)
    {
        $request->validate(['settings' => 'required|array']);

        $updated = 0;
        foreach ($request->settings as $key => $value) {
            $setting = Setting::where('group', $group)->where('key', $key)->first();
            if ($setting) {
                $setting->update(['value' => is_bool($value) ? ($value ? '1' : '0') : $value]);
                Cache::forget("setting:{$key}");
                $updated++;
            }
        }

        self::logVisit('settings', 'update', 'updated', "Updated {$group} settings ({$updated} keys)");

        return response()->json(['success' => true, 'message' => ucfirst($group) . ' settings saved successfully.']);
    }

    // GET /api/settings/public — public settings (no auth needed)
    public function publicSettings()
    {
        $settings = Setting::where('is_public', true)->get()
            ->mapWithKeys(fn($s) => [$s->key => $this->castValue($s->value, $s->type)]);

        return response()->json(['success' => true, 'data' => $settings]);
    }

    private function castValue(mixed $value, string $type): mixed
    {
        return match($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }
}
