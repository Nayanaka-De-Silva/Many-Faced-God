{{--
    Renders one spell name: clickable (opens the library spell-detail modal) when a
    library id is present, or plain italic text for a freeform/offline name. Shared by
    every spell list in _casting_profiles_display.blade.php so the markup can't drift
    between the At-Will/Per-Day/cantrip/prepared/known lists.

    Expects: $libraryId (nullable string), $name (string).
--}}
@if(filled($libraryId))
    <button type="button"
        class="btn btn-link p-0 align-baseline spell-detail-link"
        data-spell-id="{{ $libraryId }}">{{ $name }}</button>
@else
    <em>{{ $name }}</em>
@endif
