{{--
    _casting_profiles_display.blade.php
    Renders structured casting profiles as 5e stat-block prose.
    Included by npcs/show.blade.php and templates/show.blade.php.
    Expects $npc (an Npc instance with castingProfiles.innateEntries eager-loaded).
--}}

@php
    use App\Models\NpcCastingProfile;
    use App\Models\NpcInnateSpellEntry;

    // Format the attack bonus segment, or empty string when absent.
    $attackBonusText = fn(?int $bonus): string => $bonus !== null
        ? ', ' . ($bonus >= 0 ? '+' : '') . $bonus . ' to hit with spell attacks'
        : '';
@endphp

@if($npc->castingProfiles->isNotEmpty())
    {{-- Already ordered by sort_order at the query level (Npc::castingProfiles()) — no
         need to re-sort the collection here. --}}
    @foreach($npc->castingProfiles as $profile)
        @php $abText = $attackBonusText($profile->attack_bonus); @endphp

        {{-- ── Innate Spellcasting ─────────────────────────────────────────── --}}
        @if($profile->isInnate())
            @php
                $innateHeading = $profile->psionics
                    ? 'Innate Spellcasting (Psionics)'
                    : 'Innate Spellcasting';

                $atWillEntries = $profile->innateEntries
                    ->where('usage', NpcInnateSpellEntry::USAGE_AT_WILL)
                    ->values();

                $perDayGroups = $profile->innateEntries
                    ->where('usage', NpcInnateSpellEntry::USAGE_PER_DAY)
                    ->groupBy('uses_per_day')
                    ->sortKeys();
            @endphp

            <h5 class="text-danger mt-4">{{ $innateHeading }}</h5>
            <p>
                <strong><em>{{ $innateHeading }}.</em></strong>
                {{ $npc->name }}'s innate spellcasting ability is {{ $profile->spellcasting_ability }}
                (spell save DC {{ $profile->save_dc }}{{ $abText }}).
                @if($profile->race_or_origin)
                    {{ $npc->name }} can innately cast the following spells
                    ({{ $profile->race_or_origin }}):
                @endif
            </p>

            @if($atWillEntries->isNotEmpty())
                <p class="ms-3">At will:
                    @foreach($atWillEntries as $idx => $entry)
                        @if($idx > 0), @endif
                        @include('npcs.partials._spell_reference', ['libraryId' => $entry->spell_library_id, 'name' => $entry->spell_name])
                        @if(filled($entry->restriction)) ({{ $entry->restriction }})@endif
                    @endforeach
                </p>
            @endif

            @foreach($perDayGroups as $usesPerDay => $entries)
                <p class="ms-3">{{ $usesPerDay }}/day each:
                    @foreach($entries->values() as $idx => $entry)
                        @if($idx > 0), @endif
                        @include('npcs.partials._spell_reference', ['libraryId' => $entry->spell_library_id, 'name' => $entry->spell_name])
                        @if(filled($entry->restriction)) ({{ $entry->restriction }})@endif
                    @endforeach
                </p>
            @endforeach

        {{-- ── Spellcasting ────────────────────────────────────────────────── --}}
        @elseif($profile->isSpellcasting())
            @php
                $slotsLine = $profile->formatted_slots_line;
                $cantrips  = $profile->cantrips ?? [];
                $spells    = $profile->spells_known_or_prepared ?? [];
            @endphp

            <h5 class="text-danger mt-4">Spellcasting</h5>
            <p>
                <strong><em>Spellcasting.</em></strong>
                {{ $npc->name }} is a{{ $profile->caster_level !== null ? ' ' . NpcCastingProfile::ordinalSuffix($profile->caster_level) . '-level' : '' }} spellcaster.
                Its spellcasting ability is {{ $profile->spellcasting_ability }}
                (spell save DC {{ $profile->save_dc }}{{ $abText }}).
                {{ $npc->name }} has the following {{ $profile->source_class }} spells prepared:
            </p>

            @if(!empty($cantrips))
                <p class="ms-3">Cantrips (at will):
                    @foreach($cantrips as $idx => $cantrip)
                        @if($idx > 0), @endif
                        @include('npcs.partials._spell_reference', ['libraryId' => $cantrip['library_id'] ?? null, 'name' => $cantrip['name']])
                    @endforeach
                </p>
            @endif

            @if($slotsLine)
                <p class="ms-3 text-muted small">Spell slots: {{ $slotsLine }}</p>
            @endif

            @if(!empty($spells))
                <p class="ms-3">Spells prepared:
                    @foreach($spells as $idx => $spell)
                        @if($idx > 0), @endif
                        @include('npcs.partials._spell_reference', ['libraryId' => $spell['library_id'] ?? null, 'name' => $spell['name']])
                    @endforeach
                </p>
            @endif

        {{-- ── Pact Magic ──────────────────────────────────────────────────── --}}
        @elseif($profile->isPactMagic())
            @php
                $pactLine = $profile->formatted_pact_magic_line;
                $cantrips = $profile->cantrips ?? [];
                $spells   = $profile->spells_known_or_prepared ?? [];
            @endphp

            <h5 class="text-danger mt-4">Pact Magic</h5>
            <p>
                <strong><em>Pact Magic.</em></strong>
                {{ $npc->name }}'s spellcasting ability is {{ $profile->spellcasting_ability }}
                (spell save DC {{ $profile->save_dc }}{{ $abText }}).
                It regains all expended spell slots when it finishes a short or long rest.
            </p>

            @if($pactLine)
                <p class="ms-3">{{ $pactLine }}</p>
            @endif

            @if(!empty($cantrips))
                <p class="ms-3">Cantrips (at will):
                    @foreach($cantrips as $idx => $cantrip)
                        @if($idx > 0), @endif
                        @include('npcs.partials._spell_reference', ['libraryId' => $cantrip['library_id'] ?? null, 'name' => $cantrip['name']])
                    @endforeach
                </p>
            @endif

            @if(!empty($spells))
                <p class="ms-3">Spells known:
                    @foreach($spells as $idx => $spell)
                        @if($idx > 0), @endif
                        @include('npcs.partials._spell_reference', ['libraryId' => $spell['library_id'] ?? null, 'name' => $spell['name']])
                    @endforeach
                </p>
            @endif
        @endif

        {{-- Source citation (all profile types) --}}
        @if($profile->source)
            <p class="text-muted small">Source: {{ $profile->source }}</p>
        @endif

        {{-- Homebrew badge (all profile types) --}}
        @if($profile->homebrew)
            <span class="badge bg-warning text-dark mb-2">Homebrew</span>
        @endif

    @endforeach

    {{-- One shared spell-detail modal for all clickable spell names on this page --}}
    <div class="modal fade" id="spellDetailModal" tabindex="-1"
         aria-labelledby="spellDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="spellDetailModalLabel">Spell Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted text-center">Loading&#8230;</p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Escape HTML entities to prevent XSS when inserting server data into innerHTML.
        function escapeHtmlSpellDetail(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // Delegate click on any [data-spell-id] button to open the spell detail modal.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-spell-id]');
            if (!btn) return;

            var spellId   = btn.dataset.spellId;
            var spellName = btn.textContent.trim();

            var modal   = document.getElementById('spellDetailModal');
            var titleEl = modal.querySelector('.modal-title');
            var bodyEl  = modal.querySelector('.modal-body');

            titleEl.textContent = spellName;
            bodyEl.innerHTML    = '<p class="text-muted text-center">Loading&#8230;</p>';

            bootstrap.Modal.getOrCreateInstance(modal).show();

            fetch('/api/spell-library/spells/' + encodeURIComponent(spellId))
                .then(function (response) {
                    if (!response.ok) { throw new Error('FETCH_FAILED'); }
                    return response.json();
                })
                .then(function (spell) {
                    var level  = spell.level === 0 ? 'Cantrip' : 'Level ' + escapeHtmlSpellDetail(String(spell.level));
                    var badges = '<span class="badge bg-secondary me-1">' + level + '</span>'
                        + '<span class="badge bg-info text-dark me-1">' + escapeHtmlSpellDetail(spell.school) + '</span>';

                    if (spell.ritual)        { badges += '<span class="badge bg-success me-1">Ritual</span>'; }
                    if (spell.concentration) { badges += '<span class="badge bg-warning text-dark me-1">Concentration</span>'; }

                    var parts = [];
                    if (spell.components) {
                        if (spell.components.verbal)   { parts.push('V'); }
                        if (spell.components.somatic)  { parts.push('S'); }
                        if (spell.components.material) {
                            var m = 'M';
                            if (spell.components.materialDescription) {
                                m += ' (' + escapeHtmlSpellDetail(spell.components.materialDescription) + ')';
                            }
                            parts.push(m);
                        }
                    }

                    var html = '<div class="mb-2">' + badges + '</div>'
                        + '<table class="table table-sm"><tbody>'
                        + '<tr><th scope="row">Casting Time</th><td>' + escapeHtmlSpellDetail(spell.castingTime) + '</td></tr>'
                        + '<tr><th scope="row">Range</th><td>' + escapeHtmlSpellDetail(spell.range) + '</td></tr>'
                        + '<tr><th scope="row">Duration</th><td>' + escapeHtmlSpellDetail(spell.duration) + '</td></tr>'
                        + '<tr><th scope="row">Components</th><td>' + escapeHtmlSpellDetail(parts.join(', ')) + '</td></tr>'
                        + '</tbody></table>'
                        + '<p>' + escapeHtmlSpellDetail(spell.description) + '</p>';

                    if (spell.atHigherLevel) {
                        html += '<p><strong>At Higher Levels.</strong> ' + escapeHtmlSpellDetail(spell.atHigherLevel) + '</p>';
                    }
                    if (spell.page) {
                        html += '<p class="text-muted small">Source: ' + escapeHtmlSpellDetail(spell.page) + '</p>';
                    }
                    if (spell.classes && spell.classes.length) {
                        html += '<p class="text-muted small">Classes: ' + escapeHtmlSpellDetail(spell.classes.join(', ')) + '</p>';
                    }

                    bodyEl.innerHTML = html;
                })
                .catch(function () {
                    bodyEl.innerHTML = '<p><strong>' + escapeHtmlSpellDetail(spellName) + '</strong></p>'
                        + '<p class="text-muted">Details not available &mdash; the spell library may be offline or this spell is not in its catalog.</p>';
                });
        });
    </script>
    @endpush
@endif
