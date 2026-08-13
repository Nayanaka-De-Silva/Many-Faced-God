{{--
    _casting_profiles_display.blade.php
    Renders structured casting profiles as 5e stat-block prose.
    Included by npcs/show.blade.php and templates/show.blade.php.
    Expects $npc (an Npc instance with castingProfiles.innateEntries eager-loaded).
--}}

@php
    use App\Models\NpcCastingProfile;

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

                // Grouping and ordering live on the model so this block and
                // $profile->formatted_innate_lines share one rule instead of two.
                // Label rendering (spell name + restriction) is still separate here,
                // since this block needs live entry models for the clickable spell
                // links, not the accessor's pre-formatted strings.
                $atWillEntries = $profile->innateAtWillSpells();
                $perDayGroups  = $profile->innatePerDaySpellGroups();
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
                $cantrips          = $profile->cantrips ?? [];
                $slotsWithSpells   = $profile->slots_with_spells;
                $spellsWithoutLevel = $profile->spells_without_level;
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

            @foreach($slotsWithSpells as $group)
                <p class="ms-3">{{ $group['ordinal'] }} ({{ $group['slots'] }} {{ $group['slots'] === 1 ? 'slot' : 'slots' }}):
                    @if(!empty($group['spells']))
                        @foreach($group['spells'] as $idx => $spell)
                            @if($idx > 0), @endif
                            @include('npcs.partials._spell_reference', ['libraryId' => $spell['library_id'] ?? null, 'name' => $spell['name']])
                        @endforeach
                    @else
                        &mdash;
                    @endif
                </p>
            @endforeach

            @if(!empty($spellsWithoutLevel))
                <p class="ms-3">Spells prepared:
                    @foreach($spellsWithoutLevel as $idx => $spell)
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

    @once
    @push('styles')
    <style>
        /* The modal used to inherit .npc-card .card-body's 0.9rem; body level would reset it to
           1rem, so pin it here to keep spell details at the statblock's text scale. */
        #spellDetailModal .modal-content { font-size: 0.9rem; }
    </style>
    @endpush
    @endonce

    {{-- One shared spell-detail modal for all clickable spell names on this page. Pushed to the
         body-level 'modals' stack: rendered inline it would sit inside .npc-card, whose :hover
         transform becomes the containing block for position:fixed and makes the modal flicker. --}}
    @once
    @push('modals')
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
    @endpush
    @endonce

    @once
    @push('scripts')
    <script>
    (function () {
        // Escape HTML entities to prevent XSS when inserting server data into innerHTML.
        function escapeHtmlSpellDetail(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // Escape a rendered field, standing in for anything the payload didn't carry. The
        // library's exact field names aren't pinned by any fixture, so a renamed key should
        // leave a gap in the table rather than print "undefined" at the reader.
        function spellField(value) {
            return (value === null || value === undefined || value === '')
                ? '&mdash;'
                : escapeHtmlSpellDetail(value);
        }

        // The proxy passes the library's payload through verbatim, so a response envelope
        // ({"data": {...}}) is still an object and would render as an empty stat table. Require
        // at least one recognisable spell field before treating the response as a spell.
        function isRenderableSpell(spell) {
            return !!spell
                && typeof spell === 'object'
                && (typeof spell.school === 'string' || typeof spell.level === 'number');
        }

        // Tracks the in-flight spell lookup so a superseded response never paints the modal.
        var pendingSpellRequest = null;

        // Delegate click on any [data-spell-id] button to open the spell detail modal.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-spell-id]');
            if (!btn) return;

            var spellId   = btn.dataset.spellId;
            var spellName = btn.textContent.trim();

            // The modal lives in the layout's 'modals' stack, so it can go missing independently
            // of this handler. Bail out rather than throwing on every spell click.
            var modal = document.getElementById('spellDetailModal');
            if (!modal) {
                // Otherwise every spell link is a silent no-op — no modal, no message, nothing.
                console.error('Spell detail modal is missing; the layout may not render the modals stack.');
                return;
            }

            var titleEl = modal.querySelector('.modal-title');
            var bodyEl  = modal.querySelector('.modal-body');

            titleEl.textContent = spellName;
            bodyEl.innerHTML    = '<p class="text-muted text-center">Loading&#8230;</p>';

            bootstrap.Modal.getOrCreateInstance(modal).show();

            // Cancel any earlier lookup so responses can't land out of order.
            if (pendingSpellRequest) { pendingSpellRequest.abort(); }
            var controller = new AbortController();
            pendingSpellRequest = controller;

            fetch('/api/spell-library/spells/' + encodeURIComponent(spellId), { signal: controller.signal })
                .then(function (response) {
                    if (!response.ok) {
                        // Carry the status so the log can tell a missing spell (404) apart from
                        // a library outage (503).
                        var httpError = new Error('FETCH_FAILED: ' + response.status);
                        httpError.status = response.status;
                        throw httpError;
                    }
                    return response.json();
                })
                .then(function (spell) {
                    if (controller !== pendingSpellRequest) return; // a newer click already won
                    if (!isRenderableSpell(spell)) { throw new Error('MALFORMED_PAYLOAD'); }

                    var level = typeof spell.level === 'number'
                        ? (spell.level === 0 ? 'Cantrip' : 'Level ' + escapeHtmlSpellDetail(String(spell.level)))
                        : 'Level unknown';

                    var badges = '<span class="badge bg-secondary me-1">' + level + '</span>'
                        + '<span class="badge bg-info text-dark me-1">' + spellField(spell.school) + '</span>';

                    if (spell.ritual)        { badges += '<span class="badge bg-success me-1">Ritual</span>'; }
                    if (spell.concentration) { badges += '<span class="badge bg-warning text-dark me-1">Concentration</span>'; }

                    var parts = [];
                    if (spell.components) {
                        if (spell.components.verbal)   { parts.push('V'); }
                        if (spell.components.somatic)  { parts.push('S'); }
                        if (spell.components.material) {
                            var m = 'M';
                            if (spell.components.materialDescription) {
                                // Raw here — the whole joined string is escaped once below.
                                m += ' (' + spell.components.materialDescription + ')';
                            }
                            parts.push(m);
                        }
                    }

                    var html = '<div class="mb-2">' + badges + '</div>'
                        + '<table class="table table-sm"><tbody>'
                        + '<tr><th scope="row">Casting Time</th><td>' + spellField(spell.castingTime) + '</td></tr>'
                        + '<tr><th scope="row">Range</th><td>' + spellField(spell.range) + '</td></tr>'
                        + '<tr><th scope="row">Duration</th><td>' + spellField(spell.duration) + '</td></tr>'
                        + '<tr><th scope="row">Components</th><td>' + spellField(parts.join(', ')) + '</td></tr>'
                        + '</tbody></table>'
                        + '<p>' + spellField(spell.description) + '</p>';

                    if (spell.atHigherLevel) {
                        html += '<p><strong>At Higher Levels.</strong> ' + escapeHtmlSpellDetail(spell.atHigherLevel) + '</p>';
                    }
                    if (spell.page) {
                        html += '<p class="text-muted small">Source: ' + escapeHtmlSpellDetail(spell.page) + '</p>';
                    }
                    // Array check, not truthiness: a comma-joined string would throw on .join and
                    // cost the whole modal its details over one optional line.
                    if (Array.isArray(spell.classes) && spell.classes.length) {
                        html += '<p class="text-muted small">Classes: ' + escapeHtmlSpellDetail(spell.classes.join(', ')) + '</p>';
                    }

                    bodyEl.innerHTML = html;

                    // Released only once rendering succeeded — clearing it earlier would make the
                    // stale-response guard in .catch swallow any throw from the code above.
                    pendingSpellRequest = null;
                })
                .catch(function (error) {
                    // Aborted and stale requests were replaced by a newer click — stay quiet.
                    if (error && error.name === 'AbortError') return;
                    if (controller !== pendingSpellRequest) return;
                    pendingSpellRequest = null;

                    // The reader sees one outage message either way, so leave a trace: a bad
                    // payload shape lands here too and is otherwise indistinguishable from a
                    // library that is genuinely down. A 404 just means the id is not in the
                    // catalog, which is expected enough not to warrant an error-level log.
                    if (error && error.status === 404) {
                        console.warn('Spell ' + spellId + ' is not in the library catalog.');
                    } else {
                        console.error('Spell detail lookup failed for ' + spellId + ':', error);
                    }

                    bodyEl.innerHTML = '<p><strong>' + escapeHtmlSpellDetail(spellName) + '</strong></p>'
                        + '<p class="text-muted">Details not available &mdash; the spell library may be offline or this spell is not in its catalog.</p>';
                });
        });
    })();
    </script>
    @endpush
    @endonce
@endif
