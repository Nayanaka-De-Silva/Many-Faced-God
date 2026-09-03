{{--
    NPC loot panel — a browser-side view over this NPC's Bank of Vivaldi vault.
    All calls go through LootController (see routes/web.php "Loot"); the panel
    never talks to the Bank directly and stays silent-but-present when it's down.
--}}
<div
    id="loot-panel"
    data-show-url="{{ route('npcs.loot.show', $npc) }}"
    data-create-url="{{ route('npcs.loot.create', $npc) }}"
    data-items-url="{{ route('npcs.loot.items.store', $npc) }}"
    data-detach-url="{{ route('npcs.loot.detach', $npc) }}"
    data-compendium-url="{{ route('loot.compendium') }}"
    data-categories='@json(\App\Support\Loot::CATEGORIES)'
>
    <h5 class="text-danger mt-4">Loot</h5>
    <div id="loot-body" class="small text-muted">Loading loot…</div>
</div>

@push('scripts')
<script>
(function () {
    const panel = document.getElementById('loot-panel');
    if (!panel) return;

    const body = document.getElementById('loot-body');
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const urls = panel.dataset;
    const categories = JSON.parse(panel.dataset.categories);

    const cpToGp = (copper) => (Number(copper || 0) / 100).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' gp';
    const hundredthsToLb = (hundredths) => (Number(hundredths || 0) / 100).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' lb';
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[char]));

    function request(url, options) {
        return fetch(url, Object.assign({
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        }, options)).then(async (response) => {
            const payload = response.status === 204 ? null : await response.json().catch(() => null);
            return { status: response.status, ok: response.ok, payload };
        });
    }

    function showUnavailable() {
        body.innerHTML = '<div class="text-muted fst-italic">The Bank of Vivaldi is unavailable — loot can\'t be shown right now.</div>';
    }

    function errorText(result) {
        if (result.status === 503) return 'The Bank of Vivaldi is unavailable — try again shortly.';
        const error = result.payload && result.payload.error;
        if (error && Array.isArray(error.details) && error.details.length) {
            return error.details.map((detail) => detail.field + ': ' + detail.message).join('; ');
        }
        return (error && error.message) || 'Something went wrong.';
    }

    function feedback(message, kind) {
        const target = document.getElementById('loot-feedback');
        if (target) {
            target.textContent = message;
            target.className = 'small mt-2 text-' + (kind || 'muted');
        }
    }

    function flattenItems(nodes, depth, rows) {
        (nodes || []).forEach((node) => {
            rows.push({ item: node.item, depth: depth });
            flattenItems(node.children, depth + 1, rows);
        });
        return rows;
    }

    function itemTableHtml(rows, summary) {
        if (rows.length === 0) return '<p class="text-muted">This vault is empty.</p>';

        const bodyRows = rows.map((row) => {
            const indent = row.depth ? ' style="padding-left:' + (row.depth * 1.25) + 'rem"' : '';
            const rarity = (row.item.rarity && row.item.rarity !== 'mundane')
                ? ' <span class="badge bg-secondary">' + escapeHtml(row.item.rarity) + '</span>' : '';
            return '<tr>'
                + '<td' + indent + '>' + escapeHtml(row.item.name) + rarity + '</td>'
                + '<td class="text-end">' + escapeHtml(row.item.quantity ?? 1) + '</td>'
                + '<td class="text-end">' + hundredthsToLb(row.item.weightHundredthsLb) + '</td>'
                + '<td class="text-end">' + cpToGp(row.item.baseValueCp) + '</td>'
                + '</tr>';
        }).join('');

        return '<div class="table-responsive"><table class="table table-sm align-middle">'
            + '<thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Weight</th><th class="text-end">Value</th></tr></thead>'
            + '<tbody>' + bodyRows + '</tbody>'
            + '<tfoot><tr class="fw-bold"><td>Total</td><td></td>'
            + '<td class="text-end">' + hundredthsToLb(summary.totalItemWeightHundredths) + '</td>'
            + '<td class="text-end">' + cpToGp(summary.totalItemValueCp) + '</td>'
            + '</tr></tfoot></table></div>';
    }

    function renderVault(data) {
        const rows = flattenItems(data.rootItems, 0, []);

        body.innerHTML = itemTableHtml(rows, data.summary || {})
            + '<div class="d-flex gap-2 flex-wrap mb-3">'
            + '<button type="button" class="btn btn-sm btn-outline-primary" id="loot-add-toggle">Add loot</button>'
            + '<button type="button" class="btn btn-sm btn-outline-danger" id="loot-detach">Detach vault</button>'
            + '</div>'
            + '<div id="loot-add" class="border rounded p-3 mb-2" hidden></div>'
            + '<div id="loot-feedback" class="small mt-2" aria-live="polite"></div>';

        document.getElementById('loot-detach').addEventListener('click', detachVault);
        document.getElementById('loot-add-toggle').addEventListener('click', () => {
            const addForm = document.getElementById('loot-add');
            addForm.hidden = !addForm.hidden;
            if (!addForm.hidden && !addForm.dataset.built) buildAddForm(addForm);
        });
    }

    function renderNoVault() {
        body.innerHTML = '<p class="text-muted">No loot vault attached.</p>'
            + '<button type="button" class="btn btn-sm btn-outline-primary" id="loot-create">Create loot vault</button>'
            + '<div id="loot-feedback" class="small mt-2" aria-live="polite"></div>';

        document.getElementById('loot-create').addEventListener('click', (event) => {
            event.target.disabled = true;
            request(urls.createUrl, { method: 'POST' }).then((result) => {
                if (result.status === 201) { load(); return; }
                event.target.disabled = false;
                feedback(errorText(result), 'danger');
            });
        });
    }

    function buildAddForm(container) {
        container.dataset.built = '1';
        const options = categories.map((category) =>
            '<option value="' + escapeHtml(category) + '">' + escapeHtml(category) + '</option>').join('');

        container.innerHTML = ''
            + '<div class="mb-3">'
            + '  <label class="form-label small fw-bold">From the compendium</label>'
            + '  <div class="input-group input-group-sm">'
            + '    <input type="text" class="form-control" id="loot-search" placeholder="Search items…">'
            + '    <button class="btn btn-outline-secondary" type="button" id="loot-search-btn">Search</button>'
            + '  </div>'
            + '  <div id="loot-search-results" class="list-group list-group-flush mt-2"></div>'
            + '</div>'
            + '<hr>'
            + '<div class="row g-2">'
            + '  <div class="col-12"><label class="form-label small fw-bold">Or define an item</label></div>'
            + '  <div class="col-sm-5"><input type="text" class="form-control form-control-sm" id="loot-name" placeholder="Name"></div>'
            + '  <div class="col-sm-3"><select class="form-select form-select-sm" id="loot-category">' + options + '</select></div>'
            + '  <div class="col-sm-2"><input type="number" min="0" class="form-control form-control-sm" id="loot-qty" placeholder="Qty" value="1"></div>'
            + '  <div class="col-sm-3"><input type="number" min="0" step="0.01" class="form-control form-control-sm" id="loot-weight" placeholder="Weight (lb)"></div>'
            + '  <div class="col-sm-3"><input type="number" min="0" step="0.01" class="form-control form-control-sm" id="loot-value" placeholder="Value (gp)"></div>'
            + '  <div class="col-sm-3"><button type="button" class="btn btn-sm btn-primary w-100" id="loot-inline-add">Add item</button></div>'
            + '</div>';

        document.getElementById('loot-search-btn').addEventListener('click', runSearch);
        document.getElementById('loot-search').addEventListener('keydown', (event) => {
            if (event.key === 'Enter') { event.preventDefault(); runSearch(); }
        });
        document.getElementById('loot-inline-add').addEventListener('click', addInlineItem);
    }

    function runSearch() {
        const term = document.getElementById('loot-search').value.trim();
        const results = document.getElementById('loot-search-results');
        results.innerHTML = '<span class="small text-muted">Searching…</span>';

        request(urls.compendiumUrl + '?pageSize=15&q=' + encodeURIComponent(term), { method: 'GET' })
            .then((result) => {
                if (!result.ok) {
                    results.innerHTML = '<span class="small text-danger">' + escapeHtml(errorText(result)) + '</span>';
                    return;
                }
                const entries = (result.payload && result.payload.data) || [];
                if (!entries.length) { results.innerHTML = '<span class="small text-muted">No matches.</span>'; return; }

                results.innerHTML = entries.map((entry) => {
                    const item = entry.item || {};
                    return '<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1" data-item-id="' + escapeHtml(item.id) + '">'
                        + '<span>' + escapeHtml(item.name) + ' <span class="text-muted small">' + escapeHtml(item.category || '') + '</span></span>'
                        + '<span class="text-muted small">' + cpToGp(item.baseValueCp) + '</span>'
                        + '</button>';
                }).join('');

                results.querySelectorAll('[data-item-id]').forEach((entryButton) => {
                    entryButton.addEventListener('click', () =>
                        addItem({ source_item_id: entryButton.dataset.itemId, mode: 'copy' }, entryButton));
                });
            });
    }

    function addInlineItem() {
        const toHundredths = (raw) => (raw === '' || raw == null) ? null : Math.round(parseFloat(raw) * 100);
        const quantity = document.getElementById('loot-qty').value;
        addItem({
            name: document.getElementById('loot-name').value.trim(),
            category: document.getElementById('loot-category').value,
            quantity: quantity === '' ? null : parseInt(quantity, 10),
            weight_hundredths_lb: toHundredths(document.getElementById('loot-weight').value),
            base_value_cp: toHundredths(document.getElementById('loot-value').value),
        }, document.getElementById('loot-inline-add'));
    }

    function addItem(payload, trigger) {
        if (trigger) trigger.disabled = true;
        request(urls.itemsUrl, { method: 'POST', body: JSON.stringify(payload) }).then((result) => {
            if (trigger) trigger.disabled = false;
            if (result.status === 201) { load(); return; }
            feedback(errorText(result), 'danger');
        });
    }

    function detachVault() {
        if (!confirm('Detach this loot vault? The vault and its contents stay in the Bank of Vivaldi.')) return;
        request(urls.detachUrl, { method: 'DELETE' }).then((result) => {
            if (result.status === 204) { load(); return; }
            feedback(errorText(result), 'danger');
        });
    }

    function load() {
        request(urls.showUrl, { method: 'GET' }).then((result) => {
            if (result.status === 503) { showUnavailable(); return; }
            if (!result.ok) {
                body.innerHTML = '<div class="text-danger">' + escapeHtml(errorText(result)) + '</div>';
                return;
            }
            if (!result.payload || result.payload.vault === null) { renderNoVault(); return; }
            renderVault(result.payload);
        }).catch(showUnavailable);
    }

    load();
})();
</script>
@endpush
