<?php

namespace App\Http\Controllers;

use App\Exceptions\BankOfVivaldiRequestException;
use App\Exceptions\BankOfVivaldiUnavailableException;
use App\Models\Npc;
use App\Services\Vivaldi\BankOfVivaldiClient;
use App\Support\Loot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Browser-facing facade over the bank-of-vivaldi service. The NPC loot panel
 * talks to these endpoints; it never calls the Bank directly.
 *
 * NPC-side only for now: attach a vault to an NPC, stock it, view it, detach it.
 * Transferring loot to a player has no home here yet (no PC model).
 */
class LootController extends Controller
{
    public function __construct(private readonly BankOfVivaldiClient $client)
    {
    }

    /** GET /npcs/{npc}/loot */
    public function show(Npc $npc): JsonResponse
    {
        if (! $npc->hasLootVault()) {
            return response()->json(['vault' => null]);
        }

        try {
            $vault = $this->client->getVault($npc->vivaldi_vault_id);
        } catch (BankOfVivaldiUnavailableException) {
            return $this->bankUnavailableResponse();
        }

        if (is_null($vault)) {
            // Vault deleted from the Bank's own UI. Report it as "no vault"; the
            // dangling id is reconciled on the next create() — an explicit write —
            // rather than mutating state from this GET.
            return response()->json(['vault' => null]);
        }

        return response()->json($vault);
    }

    /** POST /npcs/{npc}/loot — create and link a vault for this NPC */
    public function create(Npc $npc): JsonResponse
    {
        try {
            if ($conflict = $this->liveVaultConflict($npc)) {
                return $conflict;
            }

            $vault = $this->client->createVault([
                'characterName' => $npc->name,
                'strengthScore' => max(1, (int) $npc->strength),
                'kind'          => 'npc',
            ]);
        } catch (BankOfVivaldiUnavailableException) {
            return $this->bankUnavailableResponse();
        } catch (BankOfVivaldiRequestException $e) {
            return $this->passthroughUpstreamError($e);
        }

        return $this->persistNewVault($npc, $vault);
    }

    /** POST /npcs/{npc}/loot/items — add one item to this NPC's vault */
    public function addItem(Request $request, Npc $npc): JsonResponse
    {
        if (! $npc->hasLootVault()) {
            return response()->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'This NPC has no loot vault yet.']],
                404
            );
        }

        $payload = $this->itemPayload($this->validateItem($request));

        try {
            $item = $this->client->addItem($npc->vivaldi_vault_id, $payload);
        } catch (BankOfVivaldiUnavailableException) {
            return $this->bankUnavailableResponse();
        } catch (BankOfVivaldiRequestException $e) {
            return $this->passthroughUpstreamError($e);
        }

        return response()->json($item, 201);
    }

    /** DELETE /npcs/{npc}/loot — unlink the vault; the vault itself persists upstream */
    public function detach(Npc $npc): Response|JsonResponse
    {
        if (! $npc->hasLootVault()) {
            return response()->noContent();
        }

        try {
            $this->client->unlinkVault($npc->vivaldi_vault_id, $npc->lootExternalRef());
        } catch (BankOfVivaldiUnavailableException) {
            return $this->bankUnavailableResponse();
        } catch (BankOfVivaldiRequestException $e) {
            // A 404 means the link (or vault) is already gone upstream — that's the
            // end state we want, so fall through and clear the column anyway.
            if ($e->status !== 404) {
                return $this->passthroughUpstreamError($e);
            }
        }

        $npc->update(['vivaldi_vault_id' => null]);

        return response()->noContent();
    }

    /** GET /loot/compendium — browse the shared item catalogue */
    public function compendium(Request $request): JsonResponse
    {
        try {
            return response()->json($this->client->searchCompendium($request->all()));
        } catch (BankOfVivaldiUnavailableException) {
            return $this->bankUnavailableResponse();
        }
    }

    /** GET /loot/health */
    public function health(): JsonResponse
    {
        try {
            $status = $this->client->health() ? 'ok' : 'unreachable';

            return response()->json(['status' => $status]);
        } catch (BankOfVivaldiUnavailableException) {
            return $this->bankUnavailableResponse();
        }
    }

    // --- helpers ---

    /**
     * Guard create() against an NPC that already has a vault. Returns a 409
     * response when the recorded vault still exists upstream; returns null
     * (clearing a dangling id) when it's safe to create a fresh one.
     */
    private function liveVaultConflict(Npc $npc): ?JsonResponse
    {
        if (! $npc->hasLootVault()) {
            return null;
        }

        if (! is_null($this->client->getVault($npc->vivaldi_vault_id))) {
            return response()->json(
                ['error' => ['code' => 'CONFLICT', 'message' => 'This NPC already has a loot vault.']],
                409
            );
        }

        $npc->update(['vivaldi_vault_id' => null]);

        return null;
    }

    /**
     * Record a freshly created vault against the NPC and best-effort cross-link
     * it. A malformed upstream body (no usable id) is reported as a 502.
     */
    private function persistNewVault(Npc $npc, array $vault): JsonResponse
    {
        if (! Str::isUuid($vault['id'] ?? null)) {
            return response()->json(
                ['error' => ['code' => 'BAD_UPSTREAM', 'message' => 'The Bank of Vivaldi returned an unusable vault.']],
                502
            );
        }

        $npc->update(['vivaldi_vault_id' => $vault['id']]);

        // Cross-link is idempotent and non-critical: the vault already exists and
        // is recorded, so a link hiccup shouldn't fail the request.
        try {
            $this->client->linkVault($vault['id'], $npc->lootExternalRef(), $npc->name);
        } catch (BankOfVivaldiUnavailableException | BankOfVivaldiRequestException) {
            // swallow
        }

        return response()->json($vault, 201);
    }

    private function validateItem(Request $request): array
    {
        return $request->validate([
            'source_item_id'      => ['required_without:name', 'nullable', 'uuid'],
            'mode'                => ['nullable', Rule::in(Loot::TRANSFER_MODES)],
            'container_id'        => ['nullable', 'uuid'],
            'name'                => ['required_without:source_item_id', 'nullable', 'string', 'max:200'],
            'category'            => ['required_with:name', 'nullable', Rule::in(Loot::CATEGORIES)],
            'description'         => ['nullable', 'string'],
            'subcategory'         => ['nullable', 'string'],
            'rarity'              => ['nullable', Rule::in(Loot::RARITIES)],
            'weight_hundredths_lb' => ['nullable', 'integer', 'min:0'],
            'base_value_cp'       => ['nullable', 'integer', 'min:0'],
            'quantity'            => ['nullable', 'integer', 'min:0'],
            'is_stackable'        => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Map the validated snake_case request to the Bank's camelCase body, picking
     * the transfer shape when a source item id is given and the inline shape
     * otherwise. Null entries are dropped so upstream defaults apply.
     */
    private function itemPayload(array $data): array
    {
        if (! empty($data['source_item_id'])) {
            return $this->withoutNulls([
                'sourceItemId' => $data['source_item_id'],
                'mode'         => $data['mode'] ?? 'copy',
                'containerId'  => $data['container_id'] ?? null,
            ]);
        }

        return $this->withoutNulls([
            'name'               => $data['name'],
            'category'           => $data['category'],
            'description'        => $data['description'] ?? null,
            'subcategory'        => $data['subcategory'] ?? null,
            'rarity'             => $data['rarity'] ?? null,
            'weightHundredthsLb' => $data['weight_hundredths_lb'] ?? null,
            'baseValueCp'        => $data['base_value_cp'] ?? null,
            'quantity'           => $data['quantity'] ?? null,
            'isStackable'        => $data['is_stackable'] ?? null,
            'containerId'        => $data['container_id'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $values */
    private function withoutNulls(array $values): array
    {
        return array_filter($values, static fn ($value) => ! is_null($value));
    }

    private function bankUnavailableResponse(): JsonResponse
    {
        return response()->json(
            ['error' => ['code' => 'BANK_UNREACHABLE', 'message' => 'The Bank of Vivaldi is currently unavailable.']],
            503
        );
    }

    /**
     * Relay an upstream client error. A validation rejection (INVALID_INPUT)
     * becomes a 422 so the loot panel can render field `details` like any other
     * form error; anything else keeps its upstream status (404, 409, ...).
     */
    private function passthroughUpstreamError(BankOfVivaldiRequestException $exception): JsonResponse
    {
        $code = $exception->errorBody['error']['code'] ?? null;
        $status = ($exception->status === 400 && $code === 'INVALID_INPUT') ? 422 : $exception->status;

        return response()->json($exception->errorBody, $status);
    }
}
