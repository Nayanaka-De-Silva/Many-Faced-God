<?php

namespace App\Http\Controllers;

use App\Exceptions\BankOfVivaldiUnavailableException;
use App\Exceptions\SpellLibraryUnavailableException;
use App\Models\AppSetting;
use App\Services\SpellLibrary\SpellLibraryClient;
use App\Services\Vivaldi\BankOfVivaldiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SpellLibraryClient $client,
        private readonly BankOfVivaldiClient $bankClient,
    ) {
    }

    public function edit(): View
    {
        $configDefault = config('services.netheril.base_url');
        $dbOverride    = AppSetting::get('spell_library.base_url');

        $vivaldiConfigDefault = config('services.vivaldi.base_url');
        $vivaldiDbOverride    = AppSetting::get('vivaldi.base_url');

        return view('settings.edit', [
            'effectiveUrl'  => $dbOverride ?? $configDefault,
            'configDefault' => $configDefault,
            'hasDbOverride' => !is_null($dbOverride),

            'vivaldiEffectiveUrl'  => $vivaldiDbOverride ?? $vivaldiConfigDefault,
            'vivaldiConfigDefault' => $vivaldiConfigDefault,
            'vivaldiHasDbOverride' => !is_null($vivaldiDbOverride),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'spell_library_base_url' => ['nullable', 'url'],
            'vivaldi_base_url'       => ['nullable', 'url:http,https'],
        ]);

        // Each settings card posts its own field. Only touch a key that this
        // submission actually carried, so saving one card can't wipe the other's
        // override. A present-but-empty value clears that override.
        if ($request->has('spell_library_base_url')) {
            AppSetting::set('spell_library.base_url', $request->input('spell_library_base_url') ?: null);
        }

        if ($request->has('vivaldi_base_url')) {
            AppSetting::set('vivaldi.base_url', $request->input('vivaldi_base_url') ?: null);
        }

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

    public function testVivaldiConnection(): JsonResponse
    {
        try {
            $ok      = $this->bankClient->health();
            $message = $ok ? 'Connection successful.' : 'Service is reachable but not healthy.';
            return response()->json(['ok' => $ok, 'message' => $message]);
        } catch (BankOfVivaldiUnavailableException) {
            return response()->json(['ok' => false, 'message' => 'Cannot reach the Bank of Vivaldi.']);
        }
    }
}
