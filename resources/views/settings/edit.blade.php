@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-gear"></i> Settings</h1>

    <div class="row justify-content-center">
        <div class="col-md-8">

            {{-- Spell Library Connection --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-book"></i> Spell Library (Library of Netheril)</h5>
                </div>
                <div class="card-body">

                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="spell_library_base_url" class="form-label">
                                Server Base URL
                                @if($hasDbOverride)
                                    <span class="badge bg-warning text-dark ms-1">DB override active</span>
                                @else
                                    <span class="badge bg-secondary ms-1">using config default</span>
                                @endif
                            </label>
                            <input
                                type="url"
                                name="spell_library_base_url"
                                id="spell_library_base_url"
                                class="form-control @error('spell_library_base_url') is-invalid @enderror"
                                value="{{ old('spell_library_base_url', $effectiveUrl) }}"
                                placeholder="{{ $configDefault }}"
                            >
                            @error('spell_library_base_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Leave empty to revert to the environment default
                                (<code>{{ $configDefault }}</code>).
                            </div>
                        </div>

                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Save Settings
                            </button>
                            <button type="button" id="testConnectionBtn" class="btn btn-outline-info">
                                <i class="bi bi-plug"></i> Test Connection
                            </button>
                            <span id="testConnectionResult" class="ms-2 small" aria-live="polite"></span>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
    document.getElementById('testConnectionBtn').addEventListener('click', function () {
        const resultEl = document.getElementById('testConnectionResult');
        resultEl.textContent = 'Testing…';
        resultEl.className = 'ms-2 small text-muted';

        fetch('{{ route('settings.test-spell-library') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            resultEl.textContent = data.message;
            resultEl.className = 'ms-2 small ' + (data.ok ? 'text-success' : 'text-danger');
        })
        .catch(function () {
            resultEl.textContent = 'Request failed — check console.';
            resultEl.className = 'ms-2 small text-danger';
        });
    });
</script>
@endsection
