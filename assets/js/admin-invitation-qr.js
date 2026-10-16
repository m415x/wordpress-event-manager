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
            if (!window.confirm('Esta acción crea una nueva credencial de invitación. ¿Querés continuar?')) return;

            clearPresentation();
            buttons.forEach((item) => { item.disabled = true; });
            output.textContent = 'Generando QR de invitación…';

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
                if (response.status === 409 && payload?.data?.code === 'invitation_qr_already_issued') {
                    clearPresentation();
                    output.textContent = 'Ya existe una credencial de invitación. No se puede recuperar el QR ni la URL anteriores. Usá Rotar QR solo si querés invalidar la credencial activa.';
                    return;
                }
                if (response.status === 409 && payload?.data?.code === 'invitation_qr_active_reissue_conflict') {
                    clearPresentation();
                    output.textContent = 'No se puede reemitir mientras exista una credencial activa. Revocá expresamente la credencial antes de reemitir. No se realizaron cambios.';
                    return;
                }
                if (!response.ok || !payload.success ||
                    typeof payload.data?.svg !== 'string' ||
                    typeof payload.data?.url !== 'string') {
                    throw new Error('No se puede mostrar el QR.');
                }

                clearPresentation();
                const qr = document.createElement('img');
                qr.alt = 'Código QR de invitación';
                qr.width = 220;
                qr.height = 220;
                activeObjectUrl = URL.createObjectURL(
                    new Blob([payload.data.svg], { type: 'image/svg+xml' })
                );
                qr.src = activeObjectUrl;

                const label = document.createElement('p');
                label.textContent = 'URL de invitación (copiala y guardala de forma segura):';
                const url = document.createElement('input');
                url.type = 'text';
                url.readOnly = true;
                url.value = payload.data.url;
                url.setAttribute('aria-label', 'URL de invitación');
                url.style.width = '100%';
                const hint = document.createElement('p');
                hint.textContent = 'Copiá la URL ahora. No se puede recuperar a partir de la credencial almacenada.';
                output.append(qr, label, url, hint);
            } catch (_error) {
                clearPresentation();
                output.textContent = 'No se pudo mostrar el QR. Es posible que la credencial ya se haya emitido. No vuelvas a intentar la operación sin verificar su estado.';
            } finally {
                buttons.forEach((item) => { item.disabled = false; });
            }
        });
    }
}());
