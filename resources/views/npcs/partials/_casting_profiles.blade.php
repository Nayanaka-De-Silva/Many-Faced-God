{{--
    Casting Profiles partial — included from npcs/_form.blade.php.
    Inherits $formValue, $npc, and $formData from the parent scope.
--}}

@php
    use App\Models\NpcCastingProfile;
    use App\Models\NpcInnateSpellEntry;

    $castingProfiles = $formValue('casting_profiles', $npc?->castingProfiles?->toArray() ?? []);
    $castingProfiles = $castingProfiles ?: [];

    // A row removed client-side before submit leaves a gap in the array's keys (JS never
    // renumbers remaining rows). Seeding a "next index" counter from count() instead of the
    // highest existing key can then hand out an index that's still in use, so on the next
    // add + submit two inputs share one name and one silently overwrites the other. Seeding
    // from max(keys)+1 avoids that regardless of any gaps.
    $nextIndex = fn (array $items): int => empty($items) ? 0 : max(array_keys($items)) + 1;
@endphp

<!-- Casting Profiles (Structured) -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-journals"></i> Casting Profiles
        <small class="text-muted ms-2 fw-normal">Structured spellcasting blocks for the statblock</small>
    </div>
    <div class="card-body">
        {{-- Always present, even with zero profile cards, so the controller can tell
             "this form submitted zero casting profiles" apart from "this request never
             knew about the field" (e.g. a non-UI API call). --}}
        <input type="hidden" name="casting_profiles_submitted" value="1">
        <div id="castingProfilesContainer">
            @forelse($castingProfiles as $pi => $profile)
                @php
                    $profileType     = $profile['casting_type'] ?? '';
                    $isInnate        = $profileType === NpcCastingProfile::TYPE_INNATE;
                    $isSpellcasting  = $profileType === NpcCastingProfile::TYPE_SPELLCASTING;
                    $isPactMagic     = $profileType === NpcCastingProfile::TYPE_PACT_MAGIC;
                    $isNonInnate     = $isSpellcasting || $isPactMagic;
                    $innateEntries   = $profile['innate_entries'] ?? [];
                    $cantrips        = $profile['cantrips'] ?? [];
                    $spells          = $profile['spells_known_or_prepared'] ?? [];
                @endphp
                <div class="casting-profile-card card mb-3 border-secondary"
                     data-profile-index="{{ $pi }}"
                     data-innate-count="{{ $nextIndex($innateEntries) }}"
                     data-cantrip-count="{{ $nextIndex($cantrips) }}"
                     data-spell-count="{{ $nextIndex($spells) }}">
                    <div class="card-body">
                        {{-- Card header row --}}
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 text-muted">Casting Profile</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-casting-profile">Remove Profile</button>
                        </div>

                        {{-- Common fields (always visible) --}}
                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Casting Type <span class="text-danger">*</span></label>
                                <select name="casting_profiles[{{ $pi }}][casting_type]" class="form-select casting-type-select">
                                    @foreach(NpcCastingProfile::CASTING_TYPES as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}" {{ $profileType === $typeKey ? 'selected' : '' }}>
                                            {{ $typeLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Spellcasting Ability <span class="text-danger">*</span></label>
                                <select name="casting_profiles[{{ $pi }}][spellcasting_ability]" class="form-select">
                                    @foreach(NpcCastingProfile::ABILITIES as $ability)
                                        <option value="{{ $ability }}" {{ ($profile['spellcasting_ability'] ?? '') === $ability ? 'selected' : '' }}>
                                            {{ $ability }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Save DC <span class="text-danger">*</span></label>
                                <input type="number" name="casting_profiles[{{ $pi }}][save_dc]" class="form-control"
                                    min="1" max="40" value="{{ $profile['save_dc'] ?? '' }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Attack Bonus
                                    <span class="text-danger attack-bonus-required-mark" style="{{ $isSpellcasting ? '' : 'display:none;' }}">*</span>
                                </label>
                                <input type="number" name="casting_profiles[{{ $pi }}][attack_bonus]" class="form-control"
                                    min="-99" max="99" value="{{ $profile['attack_bonus'] ?? '' }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Source <span class="text-danger">*</span></label>
                                <input type="text" name="casting_profiles[{{ $pi }}][source]" class="form-control"
                                    placeholder="e.g., Monster Manual, Archmage" value="{{ $profile['source'] ?? '' }}">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-auto">
                                <div class="form-check">
                                    <input type="hidden" name="casting_profiles[{{ $pi }}][homebrew]" value="0">
                                    <input type="checkbox" name="casting_profiles[{{ $pi }}][homebrew]"
                                        id="cp_homebrew_{{ $pi }}" class="form-check-input" value="1"
                                        {{ !empty($profile['homebrew']) ? 'checked' : '' }}>
                                    <label for="cp_homebrew_{{ $pi }}" class="form-check-label">Homebrew</label>
                                </div>
                            </div>
                            <div class="col-auto psionics-field" style="{{ $isPactMagic ? 'display:none;' : '' }}">
                                <div class="form-check">
                                    <input type="hidden" name="casting_profiles[{{ $pi }}][psionics]" value="0">
                                    <input type="checkbox" name="casting_profiles[{{ $pi }}][psionics]"
                                        id="cp_psionics_{{ $pi }}" class="form-check-input" value="1"
                                        {{ !empty($profile['psionics']) ? 'checked' : '' }}>
                                    <label for="cp_psionics_{{ $pi }}" class="form-check-label">Psionics</label>
                                    <div class="form-text small text-muted">Not applicable to Pact Magic.</div>
                                </div>
                            </div>
                        </div>

                        {{-- Innate-only section --}}
                        <div class="innate-fields" style="{{ $isInnate ? '' : 'display:none;' }}">
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Race / Origin</label>
                                    <input type="text" name="casting_profiles[{{ $pi }}][race_or_origin]" class="form-control"
                                        placeholder="e.g., Drow Magic" value="{{ $profile['race_or_origin'] ?? '' }}">
                                </div>
                            </div>
                            <label class="form-label fw-semibold">Innate Spell Entries</label>
                            <div class="innate-entries-container mb-2">
                                @forelse($innateEntries as $ei => $entry)
                                    @php $entryUsage = $entry['usage'] ?? ''; @endphp
                                    <div class="innate-entry-row border rounded p-2 mb-2">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-3">
                                                <input type="text"
                                                    name="casting_profiles[{{ $pi }}][innate_entries][{{ $ei }}][spell_name]"
                                                    class="form-control form-control-sm" placeholder="Spell Name"
                                                    value="{{ $entry['spell_name'] ?? '' }}">
                                                <input type="hidden"
                                                    name="casting_profiles[{{ $pi }}][innate_entries][{{ $ei }}][spell_library_id]"
                                                    value="{{ $entry['spell_library_id'] ?? '' }}">
                                            </div>
                                            <div class="col-auto">
                                                <button type="button" class="btn btn-sm btn-outline-secondary open-spell-picker"
                                                    title="Search Spell Library">&#128269;</button>
                                            </div>
                                            <div class="col-md-2">
                                                <select name="casting_profiles[{{ $pi }}][innate_entries][{{ $ei }}][usage]"
                                                    class="form-select form-select-sm usage-select">
                                                    <option value="{{ NpcInnateSpellEntry::USAGE_AT_WILL }}"
                                                        {{ $entryUsage === NpcInnateSpellEntry::USAGE_AT_WILL ? 'selected' : '' }}>
                                                        At Will
                                                    </option>
                                                    <option value="{{ NpcInnateSpellEntry::USAGE_PER_DAY }}"
                                                        {{ $entryUsage === NpcInnateSpellEntry::USAGE_PER_DAY ? 'selected' : '' }}>
                                                        Per Day
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-md-1 uses-per-day-col"
                                                style="{{ $entryUsage === NpcInnateSpellEntry::USAGE_PER_DAY ? '' : 'display:none;' }}">
                                                <input type="number"
                                                    name="casting_profiles[{{ $pi }}][innate_entries][{{ $ei }}][uses_per_day]"
                                                    class="form-control form-control-sm" placeholder="N" min="1"
                                                    value="{{ $entry['uses_per_day'] ?? '' }}">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="text"
                                                    name="casting_profiles[{{ $pi }}][innate_entries][{{ $ei }}][restriction]"
                                                    class="form-control form-control-sm" placeholder="Restriction (opt.)"
                                                    value="{{ $entry['restriction'] ?? '' }}">
                                            </div>
                                            <div class="col-md-1">
                                                <input type="number"
                                                    name="casting_profiles[{{ $pi }}][innate_entries][{{ $ei }}][cast_level]"
                                                    class="form-control form-control-sm" placeholder="Lvl" min="1" max="9"
                                                    value="{{ $entry['cast_level'] ?? '' }}">
                                            </div>
                                            <div class="col-auto">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-innate-entry">&#215;</button>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                @endforelse
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary add-innate-entry mb-3">+ Add Innate Spell</button>
                        </div>

                        {{-- Spellcasting-only fields: source_class + slot grid --}}
                        <div class="spellcasting-only-fields" style="{{ $isSpellcasting ? '' : 'display:none;' }}">
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Source Class <span class="text-danger">*</span></label>
                                    <input type="text" name="casting_profiles[{{ $pi }}][source_class]" class="form-control"
                                        placeholder="e.g., Wizard" value="{{ $profile['source_class'] ?? '' }}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Spell Slots</label>
                                <div class="row g-2">
                                    @foreach(['1st','2nd','3rd','4th','5th','6th','7th','8th','9th'] as $levelOrd => $levelLabel)
                                        @php $slotNum = $levelOrd + 1; @endphp
                                        <div class="col">
                                            <label class="form-label small text-center d-block">{{ $levelLabel }}</label>
                                            <input type="number"
                                                name="casting_profiles[{{ $pi }}][slots][{{ $slotNum }}]"
                                                class="form-control form-control-sm text-center" min="0"
                                                value="{{ $profile['slots'][$slotNum] ?? '' }}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- PactMagic-only fields: slot_level + slot_count --}}
                        <div class="pact-magic-only-fields" style="{{ $isPactMagic ? '' : 'display:none;' }}">
                            <div class="row g-2 mb-3">
                                <div class="col-md-2">
                                    <label class="form-label">Slot Level <span class="text-danger">*</span></label>
                                    <select name="casting_profiles[{{ $pi }}][slot_level]" class="form-select">
                                        <option value="">--</option>
                                        @foreach(range(1, 9) as $sl)
                                            <option value="{{ $sl }}"
                                                {{ ($profile['slot_level'] ?? '') == $sl ? 'selected' : '' }}>
                                                {{ $sl }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Slot Count <span class="text-danger">*</span></label>
                                    <input type="number" name="casting_profiles[{{ $pi }}][slot_count]"
                                        class="form-control" min="0" max="99" value="{{ $profile['slot_count'] ?? '' }}">
                                </div>
                            </div>
                        </div>

                        {{-- Non-innate fields: caster_level + cantrips + spells (Spellcasting + PactMagic) --}}
                        <div class="non-innate-fields" style="{{ $isNonInnate ? '' : 'display:none;' }}">
                            <div class="row g-2 mb-3">
                                <div class="col-md-2">
                                    <label class="form-label">Caster Level <span class="text-danger">*</span></label>
                                    <input type="number" name="casting_profiles[{{ $pi }}][caster_level]"
                                        class="form-control" min="1" max="30" value="{{ $profile['caster_level'] ?? '' }}">
                                </div>
                            </div>

                            {{-- Cantrips --}}
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cantrips</label>
                                <div class="cantrips-container mb-2">
                                    @forelse($cantrips as $ci => $cantrip)
                                        <div class="spell-entry-row d-flex gap-2 mb-2 align-items-center">
                                            <input type="text"
                                                name="casting_profiles[{{ $pi }}][cantrips][{{ $ci }}][name]"
                                                class="form-control" placeholder="Cantrip Name"
                                                value="{{ $cantrip['name'] ?? '' }}">
                                            <input type="hidden"
                                                name="casting_profiles[{{ $pi }}][cantrips][{{ $ci }}][library_id]"
                                                value="{{ $cantrip['library_id'] ?? '' }}">
                                            <button type="button" class="btn btn-outline-secondary open-spell-picker flex-shrink-0"
                                                title="Search Spell Library">&#128269;</button>
                                            <button type="button" class="btn btn-outline-danger remove-spell-entry flex-shrink-0">&#215;</button>
                                        </div>
                                    @empty
                                    @endforelse
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary add-cantrip">+ Add Cantrip</button>
                            </div>

                            {{-- Spells Known / Prepared --}}
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Spells Known / Prepared</label>
                                <div class="spells-container mb-2">
                                    @forelse($spells as $si => $spell)
                                        <div class="spell-entry-row d-flex gap-2 mb-2 align-items-center">
                                            <input type="text"
                                                name="casting_profiles[{{ $pi }}][spells_known_or_prepared][{{ $si }}][name]"
                                                class="form-control" placeholder="Spell Name"
                                                value="{{ $spell['name'] ?? '' }}">
                                            <input type="hidden"
                                                name="casting_profiles[{{ $pi }}][spells_known_or_prepared][{{ $si }}][library_id]"
                                                value="{{ $spell['library_id'] ?? '' }}">
                                            <button type="button" class="btn btn-outline-secondary open-spell-picker flex-shrink-0"
                                                title="Search Spell Library">&#128269;</button>
                                            <button type="button" class="btn btn-outline-danger remove-spell-entry flex-shrink-0">&#215;</button>
                                        </div>
                                    @empty
                                    @endforelse
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary add-spell">+ Add Spell</button>
                            </div>
                        </div>

                    </div>{{-- /.card-body --}}
                </div>{{-- /.casting-profile-card --}}
            @empty
            @endforelse
        </div>{{-- /#castingProfilesContainer --}}

        <button type="button" class="btn btn-sm btn-outline-success" id="addCastingProfile">+ Add Casting Profile</button>
    </div>
</div>

{{-- Shared spell picker modal — one instance reused by every "Search Library" button on the page --}}
<div class="modal fade" id="spellPickerModal" tabindex="-1" aria-labelledby="spellPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title" id="spellPickerModalLabel">
                    <i class="bi bi-search"></i> Search Spell Library
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Click a result to fill the spell name. You can also close this and type manually.</p>
                <input type="text" id="spellSearchInput" class="form-control mb-3" placeholder="Type to search spells...">
                <div id="spellSearchResults" style="max-height: 350px; overflow-y: auto;">
                    <p class="text-muted text-center">Type to search for spells.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Track the next index for newly appended profile cards. Seeded from the highest existing
    // index (not count()) so a gap left by removing a non-last card can't be reused and collide
    // with a card that's still present.
    let castingProfileIndex = {{ $nextIndex($castingProfiles) }};

    // ── Field visibility toggle ──────────────────────────────────────────────

    function toggleCastingProfileFields(card) {
        const type = card.querySelector('.casting-type-select').value;
        card.querySelector('.innate-fields').style.display        = type === 'Innate'       ? '' : 'none';
        card.querySelector('.spellcasting-only-fields').style.display = type === 'Spellcasting' ? '' : 'none';
        card.querySelector('.pact-magic-only-fields').style.display   = type === 'PactMagic'    ? '' : 'none';
        card.querySelector('.non-innate-fields').style.display    = (type === 'Spellcasting' || type === 'PactMagic') ? '' : 'none';
        // Psionics doesn't apply to Pact Magic; the server clears it silently, so hide the
        // control rather than let a user set something that's discarded without a trace.
        card.querySelector('.psionics-field').style.display = type === 'PactMagic' ? 'none' : '';
        // Attack Bonus is only a required field for Spellcasting profiles.
        card.querySelector('.attack-bonus-required-mark').style.display = type === 'Spellcasting' ? '' : 'none';
    }

    // Run on page load for any server-rendered profile cards.
    document.querySelectorAll('.casting-profile-card').forEach(toggleCastingProfileFields);

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('casting-type-select')) {
            toggleCastingProfileFields(e.target.closest('.casting-profile-card'));
        }
        if (e.target.classList.contains('usage-select')) {
            const perDayCol = e.target.closest('.innate-entry-row').querySelector('.uses-per-day-col');
            perDayCol.style.display = e.target.value === 'PerDay' ? '' : 'none';
        }
    });

    // ── HTML generators ──────────────────────────────────────────────────────

    const slotLabels = ['1st','2nd','3rd','4th','5th','6th','7th','8th','9th'];

    function slotGridHtml(pi) {
        return slotLabels.map(function (label, i) {
            return '<div class="col">'
                + '<label class="form-label small text-center d-block">' + label + '</label>'
                + '<input type="number" name="casting_profiles[' + pi + '][slots][' + (i + 1) + ']"'
                + ' class="form-control form-control-sm text-center" min="0">'
                + '</div>';
        }).join('');
    }

    function slotLevelOptionsHtml() {
        return '<option value="">--</option>'
            + [1,2,3,4,5,6,7,8,9].map(function (n) { return '<option value="' + n + '">' + n + '</option>'; }).join('');
    }

    function innateEntryRowHtml(pi, ei) {
        return '<div class="innate-entry-row border rounded p-2 mb-2">'
            + '<div class="row g-2 align-items-center">'
            + '<div class="col-md-3">'
            + '<input type="text" name="casting_profiles[' + pi + '][innate_entries][' + ei + '][spell_name]" class="form-control form-control-sm" placeholder="Spell Name">'
            + '<input type="hidden" name="casting_profiles[' + pi + '][innate_entries][' + ei + '][spell_library_id]" value="">'
            + '</div>'
            + '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-secondary open-spell-picker" title="Search Spell Library">&#128269;</button></div>'
            + '<div class="col-md-2"><select name="casting_profiles[' + pi + '][innate_entries][' + ei + '][usage]" class="form-select form-select-sm usage-select">'
            + '<option value="AtWill">At Will</option><option value="PerDay">Per Day</option></select></div>'
            + '<div class="col-md-1 uses-per-day-col" style="display:none;">'
            + '<input type="number" name="casting_profiles[' + pi + '][innate_entries][' + ei + '][uses_per_day]" class="form-control form-control-sm" placeholder="N" min="1">'
            + '</div>'
            + '<div class="col-md-2"><input type="text" name="casting_profiles[' + pi + '][innate_entries][' + ei + '][restriction]" class="form-control form-control-sm" placeholder="Restriction (opt.)"></div>'
            + '<div class="col-md-1"><input type="number" name="casting_profiles[' + pi + '][innate_entries][' + ei + '][cast_level]" class="form-control form-control-sm" placeholder="Lvl" min="1" max="9"></div>'
            + '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger remove-innate-entry">&#215;</button></div>'
            + '</div></div>';
    }

    function spellEntryRowHtml(pi, index, listKey) {
        return '<div class="spell-entry-row d-flex gap-2 mb-2 align-items-center">'
            + '<input type="text" name="casting_profiles[' + pi + '][' + listKey + '][' + index + '][name]" class="form-control" placeholder="Spell Name">'
            + '<input type="hidden" name="casting_profiles[' + pi + '][' + listKey + '][' + index + '][library_id]" value="">'
            + '<button type="button" class="btn btn-outline-secondary open-spell-picker flex-shrink-0" title="Search Spell Library">&#128269;</button>'
            + '<button type="button" class="btn btn-outline-danger remove-spell-entry flex-shrink-0">&#215;</button>'
            + '</div>';
    }

    function cardHeaderHtml() {
        return '<div class="d-flex justify-content-between align-items-center mb-3">'
            + '<h6 class="mb-0 text-muted">Casting Profile</h6>'
            + '<button type="button" class="btn btn-sm btn-outline-danger remove-casting-profile">Remove Profile</button>'
            + '</div>';
    }

    function commonFieldsHtml(pi) {
        return '<div class="row g-2 mb-3">'
            + '<div class="col-md-3"><label class="form-label">Casting Type <span class="text-danger">*</span></label>'
            + '<select name="casting_profiles[' + pi + '][casting_type]" class="form-select casting-type-select">'
            + '<option value="Innate">Innate</option>'
            + '<option value="Spellcasting">Spellcasting</option>'
            + '<option value="PactMagic">Pact Magic</option>'
            + '</select></div>'
            + '<div class="col-md-2"><label class="form-label">Spellcasting Ability <span class="text-danger">*</span></label>'
            + '<select name="casting_profiles[' + pi + '][spellcasting_ability]" class="form-select">'
            + '<option value="Intelligence">Intelligence</option>'
            + '<option value="Wisdom">Wisdom</option>'
            + '<option value="Charisma">Charisma</option>'
            + '</select></div>'
            + '<div class="col-md-2"><label class="form-label">Save DC <span class="text-danger">*</span></label>'
            + '<input type="number" name="casting_profiles[' + pi + '][save_dc]" class="form-control" min="1" max="40"></div>'
            + '<div class="col-md-2"><label class="form-label">Attack Bonus '
            + '<span class="text-danger attack-bonus-required-mark" style="display:none;">*</span></label>'
            + '<input type="number" name="casting_profiles[' + pi + '][attack_bonus]" class="form-control" min="-99" max="99"></div>'
            + '<div class="col-md-3"><label class="form-label">Source <span class="text-danger">*</span></label>'
            + '<input type="text" name="casting_profiles[' + pi + '][source]" class="form-control" placeholder="e.g., Monster Manual, Archmage"></div>'
            + '</div>';
    }

    function checkboxesHtml(pi) {
        return '<div class="row g-2 mb-3">'
            + '<div class="col-auto"><div class="form-check">'
            + '<input type="hidden" name="casting_profiles[' + pi + '][homebrew]" value="0">'
            + '<input type="checkbox" name="casting_profiles[' + pi + '][homebrew]" class="form-check-input" value="1">'
            + '<label class="form-check-label">Homebrew</label></div></div>'
            + '<div class="col-auto psionics-field"><div class="form-check">'
            + '<input type="hidden" name="casting_profiles[' + pi + '][psionics]" value="0">'
            + '<input type="checkbox" name="casting_profiles[' + pi + '][psionics]" class="form-check-input" value="1">'
            + '<label class="form-check-label">Psionics</label>'
            + '<div class="form-text small text-muted">Not applicable to Pact Magic.</div></div></div>'
            + '</div>';
    }

    function innateSectionHtml(pi) {
        return '<div class="innate-fields">'
            + '<div class="row g-2 mb-3"><div class="col-md-4">'
            + '<label class="form-label">Race / Origin</label>'
            + '<input type="text" name="casting_profiles[' + pi + '][race_or_origin]" class="form-control" placeholder="e.g., Drow Magic">'
            + '</div></div>'
            + '<label class="form-label fw-semibold">Innate Spell Entries</label>'
            + '<div class="innate-entries-container mb-2"></div>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary add-innate-entry mb-3">+ Add Innate Spell</button>'
            + '</div>';
    }

    function spellcastingOnlySectionHtml(pi) {
        return '<div class="spellcasting-only-fields" style="display:none;">'
            + '<div class="row g-2 mb-3"><div class="col-md-4">'
            + '<label class="form-label">Source Class <span class="text-danger">*</span></label>'
            + '<input type="text" name="casting_profiles[' + pi + '][source_class]" class="form-control" placeholder="e.g., Wizard">'
            + '</div></div>'
            + '<div class="mb-3"><label class="form-label fw-semibold">Spell Slots</label>'
            + '<div class="row g-2">' + slotGridHtml(pi) + '</div></div>'
            + '</div>';
    }

    function pactMagicOnlySectionHtml(pi) {
        return '<div class="pact-magic-only-fields" style="display:none;">'
            + '<div class="row g-2 mb-3">'
            + '<div class="col-md-2"><label class="form-label">Slot Level <span class="text-danger">*</span></label>'
            + '<select name="casting_profiles[' + pi + '][slot_level]" class="form-select">' + slotLevelOptionsHtml() + '</select></div>'
            + '<div class="col-md-2"><label class="form-label">Slot Count <span class="text-danger">*</span></label>'
            + '<input type="number" name="casting_profiles[' + pi + '][slot_count]" class="form-control" min="0" max="99"></div>'
            + '</div></div>';
    }

    function nonInnateSectionHtml(pi) {
        return '<div class="non-innate-fields" style="display:none;">'
            + '<div class="row g-2 mb-3"><div class="col-md-2">'
            + '<label class="form-label">Caster Level <span class="text-danger">*</span></label>'
            + '<input type="number" name="casting_profiles[' + pi + '][caster_level]" class="form-control" min="1" max="30">'
            + '</div></div>'
            + '<div class="mb-3"><label class="form-label fw-semibold">Cantrips</label>'
            + '<div class="cantrips-container mb-2"></div>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary add-cantrip">+ Add Cantrip</button></div>'
            + '<div class="mb-3"><label class="form-label fw-semibold">Spells Known / Prepared</label>'
            + '<div class="spells-container mb-2"></div>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary add-spell">+ Add Spell</button></div>'
            + '</div>';
    }

    function castingProfileCardHtml(pi) {
        return '<div class="casting-profile-card card mb-3 border-secondary"'
            + ' data-profile-index="' + pi + '" data-innate-count="0" data-cantrip-count="0" data-spell-count="0">'
            + '<div class="card-body">'
            + cardHeaderHtml()
            + commonFieldsHtml(pi)
            + checkboxesHtml(pi)
            + innateSectionHtml(pi) // visible by default since Innate is the first option
            + spellcastingOnlySectionHtml(pi)
            + pactMagicOnlySectionHtml(pi)
            + nonInnateSectionHtml(pi)
            + '</div></div>';
    }

    // ── Add new profile card ─────────────────────────────────────────────────

    document.getElementById('addCastingProfile').addEventListener('click', function () {
        const container = document.getElementById('castingProfilesContainer');
        container.insertAdjacentHTML('beforeend', castingProfileCardHtml(castingProfileIndex));
        toggleCastingProfileFields(container.lastElementChild);
        castingProfileIndex++;
    });

    // ── Event delegation for all repeater buttons ────────────────────────────

    document.addEventListener('click', function (e) {
        // Remove entire profile card
        if (e.target.classList.contains('remove-casting-profile')) {
            e.target.closest('.casting-profile-card').remove();
            return;
        }

        // Remove innate entry row
        if (e.target.classList.contains('remove-innate-entry')) {
            e.target.closest('.innate-entry-row').remove();
            return;
        }

        // Remove cantrip/spell row
        if (e.target.classList.contains('remove-spell-entry')) {
            e.target.closest('.spell-entry-row').remove();
            return;
        }

        // Add innate entry
        if (e.target.classList.contains('add-innate-entry')) {
            const card = e.target.closest('.casting-profile-card');
            const pi   = parseInt(card.dataset.profileIndex);
            const ei   = parseInt(card.dataset.innateCount || '0');
            card.querySelector('.innate-entries-container').insertAdjacentHTML('beforeend', innateEntryRowHtml(pi, ei));
            card.dataset.innateCount = ei + 1;
            return;
        }

        // Add cantrip
        if (e.target.classList.contains('add-cantrip')) {
            const card = e.target.closest('.casting-profile-card');
            const pi   = parseInt(card.dataset.profileIndex);
            const ci   = parseInt(card.dataset.cantripCount || '0');
            card.querySelector('.cantrips-container').insertAdjacentHTML('beforeend', spellEntryRowHtml(pi, ci, 'cantrips'));
            card.dataset.cantripCount = ci + 1;
            return;
        }

        // Add spell
        if (e.target.classList.contains('add-spell')) {
            const card = e.target.closest('.casting-profile-card');
            const pi   = parseInt(card.dataset.profileIndex);
            const si   = parseInt(card.dataset.spellCount || '0');
            card.querySelector('.spells-container').insertAdjacentHTML('beforeend', spellEntryRowHtml(pi, si, 'spells_known_or_prepared'));
            card.dataset.spellCount = si + 1;
            return;
        }

        // Open spell picker
        if (e.target.classList.contains('open-spell-picker')) {
            const row = e.target.closest('.innate-entry-row, .spell-entry-row');
            if (!row) return;
            window.currentSpellTarget = {
                nameInput:      row.querySelector('input[type="text"]'),
                libraryIdInput: row.querySelector('input[type="hidden"]')
            };
            document.getElementById('spellSearchInput').value = '';
            document.getElementById('spellSearchResults').innerHTML =
                '<p class="text-muted text-center">Type to search for spells.</p>';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('spellPickerModal')).show();
        }
    });

    // ── Spell picker — debounced search ──────────────────────────────────────

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    var spellSearchTimer = null;

    document.getElementById('spellSearchInput').addEventListener('input', function () {
        clearTimeout(spellSearchTimer);
        var q = this.value.trim();
        if (!q) {
            document.getElementById('spellSearchResults').innerHTML =
                '<p class="text-muted text-center">Type to search for spells.</p>';
            return;
        }
        spellSearchTimer = setTimeout(function () {
            document.getElementById('spellSearchResults').innerHTML =
                '<p class="text-muted text-center">Searching&#8230;</p>';
            fetch('/api/spell-library/spells?search=' + encodeURIComponent(q) + '&pageSize=20')
                .then(function (response) {
                    if (!response.ok) { throw new Error('LIBRARY_UNREACHABLE'); }
                    return response.json();
                })
                .then(function (json) {
                    var spells = json.data || [];
                    if (!spells.length) {
                        document.getElementById('spellSearchResults').innerHTML =
                            '<p class="text-muted text-center">No spells found.</p>';
                        return;
                    }
                    var items = spells.map(function (spell) {
                        return '<button type="button"'
                            + ' class="list-group-item list-group-item-action spell-pick-result"'
                            + ' data-spell-id="' + escapeHtml(spell.id) + '"'
                            + ' data-spell-name="' + escapeHtml(spell.name) + '">'
                            + '<strong>' + escapeHtml(spell.name) + '</strong>'
                            + ' <span class="text-muted small">Level ' + escapeHtml(spell.level)
                            + ' ' + escapeHtml(spell.school) + '</span>'
                            + '</button>';
                    }).join('');
                    document.getElementById('spellSearchResults').innerHTML =
                        '<div class="list-group">' + items + '</div>';
                })
                .catch(function () {
                    document.getElementById('spellSearchResults').innerHTML =
                        '<div class="alert alert-warning mb-0">'
                        + 'Spell library unreachable &mdash; type the spell name manually.'
                        + '</div>';
                });
        }, 300);
    });

    // Fill name + library_id when a spell result is clicked, then close modal.
    document.getElementById('spellPickerModal').addEventListener('click', function (e) {
        var btn = e.target.closest('.spell-pick-result');
        if (!btn) return;
        if (window.currentSpellTarget) {
            window.currentSpellTarget.nameInput.value      = btn.dataset.spellName;
            window.currentSpellTarget.libraryIdInput.value = btn.dataset.spellId;
        }
        bootstrap.Modal.getInstance(document.getElementById('spellPickerModal')).hide();
    });
</script>
@endpush
