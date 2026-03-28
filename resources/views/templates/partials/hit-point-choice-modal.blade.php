<div class="modal fade" id="templateHitPointChoiceModal" tabindex="-1" aria-labelledby="templateHitPointChoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title" id="templateHitPointChoiceModalLabel">
                    <i class="bi bi-heart-pulse"></i> Choose Hit Points
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    <strong data-template-hit-point-template-name></strong> has both base hit points and hit dice.
                </p>
                <p class="text-muted mb-3">How should the new NPC start?</p>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-success text-start" data-template-hit-point-choice="template">
                        <span class="d-block fw-semibold">Use template hit points</span>
                        <small class="text-muted" data-template-hit-point-template-option></small>
                    </button>
                    <button type="button" class="btn btn-outline-warning text-start" data-template-hit-point-choice="roll">
                        <span class="d-block fw-semibold">Roll from hit dice</span>
                        <small class="text-muted" data-template-hit-point-roll-option></small>
                    </button>
                </div>

                <p class="small text-muted mt-3 mb-0">Click outside this box to cancel.</p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('templateHitPointChoiceModal');

    if (!modalElement) {
        return;
    }

    const choiceModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const templateNameElement = modalElement.querySelector('[data-template-hit-point-template-name]');
    const templateOptionElement = modalElement.querySelector('[data-template-hit-point-template-option]');
    const rollOptionElement = modalElement.querySelector('[data-template-hit-point-roll-option]');
    let pendingUrl = null;

    const showChoiceModal = (link) => {
        pendingUrl = link.href;
        templateNameElement.textContent = link.dataset.templateName;
        templateOptionElement.textContent = `${link.dataset.templateHitPoints} HP`;
        rollOptionElement.textContent = link.dataset.templateHitDice;
        choiceModal.show();
    };

    document.querySelectorAll('[data-template-hit-point-link]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (link.dataset.templateHitPointChoiceRequired !== 'true') {
                return;
            }

            event.preventDefault();

            const parentModalElement = link.closest('.modal');

            if (parentModalElement && parentModalElement.classList.contains('show')) {
                const parentModal = bootstrap.Modal.getOrCreateInstance(parentModalElement);

                parentModalElement.addEventListener('hidden.bs.modal', () => showChoiceModal(link), { once: true });
                parentModal.hide();

                return;
            }

            showChoiceModal(link);
        });
    });

    modalElement.querySelectorAll('[data-template-hit-point-choice]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!pendingUrl) {
                return;
            }

            const url = new URL(pendingUrl, window.location.origin);
            url.searchParams.set('template_hit_points', button.dataset.templateHitPointChoice);
            window.location.assign(url.toString());
        });
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        pendingUrl = null;
    });
});
</script>
@endpush
