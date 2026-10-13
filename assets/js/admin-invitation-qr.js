(function () {
    'use strict';

    const panel = document.getElementById('wem-invitation-qr-panel');
    if (!panel) return;

    const output = panel.querySelector('[data-wem-qr-output]');
    const buttons = Array.from(panel.querySelectorAll('[data-wem-qr-action]'));
    const guestId = panel.dataset.guestId;
    const nonce = panel.dataset.nonce;
    const endpoint = panel.dataset.ajaxUrl;
    let activeObjectUrl = null;

    function clearPresentation() {
        if (activeObjectUrl) {
            URL.revokeObjectURL(activeObjectUrl);
            activeObjectUrl = null;
        }
        output.replaceChildren();
    }

    window.addEventListener('pagehide', clearPresentation);

    for (const button of buttons) {
        button.addEventListener('click', async function () {
            const operation = button.dataset.wemQrAction;
            if (!['issue', 'rotate', 'reissue'].includes(operation)) return;
            if (!window.confirm('This action creates a new invitation credential. Continue?')) return;

            clearPresentation();
            buttons.forEach((item) => { item.disabled = true; });
            output.textContent = 'Generating invitation QR…';

            try {
                const body = new URLSearchParams({
                    action: 'wem_invitation_qr_' + operation,
                    guest_id: guestId,
                    nonce: nonce
                });
                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    referrerPolicy: 'no-referrer',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: body.toString()
                });
                const payload = await response.json();
                if (!response.ok || !payload.success ||
                    typeof payload.data?.svg !== 'string' ||
                    typeof payload.data?.url !== 'string') {
                    throw new Error('QR presentation is unavailable.');
                }

                clearPresentation();
                const qr = document.createElement('img');
                qr.alt = 'Invitation QR code';
                qr.width = 220;
                qr.height = 220;
                activeObjectUrl = URL.createObjectURL(
                    new Blob([payload.data.svg], { type: 'image/svg+xml' })
                );
                qr.src = activeObjectUrl;

                const label = document.createElement('p');
                label.textContent = 'Invitation URL (copy and save securely):';
                const url = document.createElement('input');
                url.type = 'text';
                url.readOnly = true;
                url.value = payload.data.url;
                url.setAttribute('aria-label', 'Invitation URL');
                url.style.width = '100%';
                const hint = document.createElement('p');
                hint.textContent = 'Copy the URL now. It cannot be recovered from the stored credential.';
                output.append(qr, label, url, hint);
            } catch (_error) {
                clearPresentation();
                output.textContent = 'QR presentation failed. A credential may already have been issued. Do not retry without checking its status.';
            } finally {
                buttons.forEach((item) => { item.disabled = false; });
            }
        });
    }
}());
