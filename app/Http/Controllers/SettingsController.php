<?php

namespace App\Http\Controllers;

use App\Exceptions\SpellLibraryUnavailableException;
use App\Models\AppSetting;
use App\Services\SpellLibrary\SpellLibraryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly SpellLibraryClient $client)
    {
    }

    public function edit(): View
    {
        $configDefault = config('services.netheril.base_url');
        $dbOverride    = AppSetting::get('spell_library.base_url');

        return view('settings.edit', [
            'effectiveUrl'  => $dbOverride ?? $configDefault,
            'configDefault' => $configDefault,
            'hasDbOverride' => !is_null($dbOverride),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'spell_library_base_url' => ['nullable', 'url'],
        ]);

        $url = $request->input('spell_library_base_url') ?: null;
        AppSetting::set('spell_library.base_url', $url);

        return redirect()->route('settings.edit')->with('success', 'Settings saved.');
    }

    public function testConnection(): JsonResponse
    {
        try {
            $ok      = $this->client->health();
            $message = $ok ? 'Connection successful.' : 'Service is reachable but not healthy.';
            return response()->json(['ok' => $ok, 'message' => $message]);
        } catch (SpellLibraryUnavailableException) {
            return response()->json(['ok' => false, 'message' => 'Cannot reach the spell library.']);
        }
    }
}
