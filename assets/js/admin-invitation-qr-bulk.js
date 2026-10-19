(function () {
    'use strict';

    const panel = document.querySelector('[data-wem-bulk-qr-panel]');
    if (!panel) return;

    const input = panel.querySelector('#wem-bulk-guest-ids');
    const previewBox = panel.querySelector('[data-wem-bulk-preview]');
    const output = panel.querySelector('[data-wem-bulk-output]');
    const buttons = Array.from(panel.querySelectorAll('[data-wem-bulk-action]'));
    const issueButtons = buttons.filter((button) => button.dataset.wemBulkAction !== 'preview');
    let approvedIds = null;
    const objectUrls = [];

    function clearResults() {
        for (const url of objectUrls) URL.revokeObjectURL(url);
        objectUrls.length = 0;
        output.replaceChildren();
    }

    function invalidatePreview() {
        approvedIds = null;
        issueButtons.forEach((button) => { button.disabled = true; });
        previewBox.replaceChildren();
        clearResults();
    }

    input.addEventListener('input', invalidatePreview);
    window.addEventListener('pagehide', invalidatePreview);

    function selectedIds() {
        const parts = input.value.trim().split(/[\s,;]+/).filter(Boolean);
        if (parts.length < 1 || parts.length > 25 ||
            parts.some((id) => !/^[1-9][0-9]*$/.test(id) || !Number.isSafeInteger(Number(id))) ||
            new Set(parts.map(Number)).size !== parts.length) {
            throw new Error('Seleccioná entre 1 y 25 ID válidos, sin duplicados.');
        }
        return parts;
    }

    async function request(action, ids, operation) {
        const body = new URLSearchParams({
            action, nonce: panel.dataset.nonce
        });
        if (operation) body.set('operation', operation);
        ids.forEach((id) => body.append('guest_ids[]', id));
        const response = await fetch(panel.dataset.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            referrerPolicy: 'no-referrer',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        });
        const result = await response.json();
        if (!response.ok || !result.success || !Array.isArray(result.data)) {
            throw new Error('No se pudo completar la solicitud.');
        }
        return result.data;
    }

    function message(parent, value) {
        const paragraph = document.createElement('p');
        paragraph.textContent = value;
        parent.append(paragraph);
    }

    function showIssued(rows) {
        clearResults();
        for (const row of rows) {
            const block = document.createElement('section');
            const id = Number.isInteger(row.guest_id) ? row.guest_id : '—';
            message(block, 'Invitado ' + id);
            if (row.status === 'issued' && typeof row.svg === 'string' && typeof row.url === 'string') {
                const qr = document.createElement('img');
                qr.alt = 'Código QR del invitado ' + id;
                qr.width = 180;
                qr.height = 180;
                const objectUrl = URL.createObjectURL(new Blob([row.svg], { type: 'image/svg+xml' }));
                objectUrls.push(objectUrl);
                qr.src = objectUrl;
                const link = document.createElement('input');
                link.type = 'text';
                link.readOnly = true;
                link.value = row.url;
                link.setAttribute('aria-label', 'URL de invitación del invitado ' + id);
                link.style.width = '100%';
                block.append(qr, link);
                message(block, 'Copiá y guardá este enlace ahora.');
            } else {
                message(block, row.status === 'denied'
                    ? 'Operación no permitida para el estado actual.'
                    : 'Resultado no disponible. No repitas la operación sin verificar el estado.');
            }
            output.append(block);
        }
        message(output, 'No repitas la operación sin verificar el estado de las credenciales.');
    }

    for (const button of buttons) {
        button.addEventListener('click', async function () {
            const operation = button.dataset.wemBulkAction;
            let ids;
            try {
                ids = selectedIds();
            } catch (error) {
                invalidatePreview();
                message(previewBox, error.message);
                return;
            }

            if (operation !== 'preview') {
                if (!approvedIds || approvedIds.join(',') !== ids.join(',')) {
                    invalidatePreview();
                    message(previewBox, 'Revisá la selección antes de continuar. Volvé a previsualizar.');
                    return;
                }
                if (!window.confirm('Revisá la selección antes de continuar. ¿Confirmás la operación sobre ' + ids.length + ' invitados?')) return;
            }

            buttons.forEach((item) => { item.disabled = true; });
            clearResults();
            if (operation === 'preview') previewBox.replaceChildren();
            try {
                if (operation === 'preview') {
                    const rows = await request('wem_invitation_qr_bulk_preview', ids);
                    for (const row of rows) {
                        const labels = {
                            never_issued: 'Nunca emitida: apta para emitir',
                            active_unrecoverable: 'Credencial activa: QR anterior no recuperable',
                            inactive_reissue: 'Sin credencial activa: apta para reemitir',
                            invalid: 'No elegible o estado inválido'
                        };
                        message(previewBox, 'Invitado ' + row.guest_id + ': ' +
                            (labels[row.eligibility] || labels.invalid));
                    }
                    approvedIds = ids.slice();
                    issueButtons.forEach((item) => { item.disabled = false; });
                } else {
                    approvedIds = null;
                    const rows = await request('wem_invitation_qr_bulk', ids, operation);
                    showIssued(rows);
                    message(previewBox, 'La previsualización quedó invalidada. Consultá el estado antes de otra operación.');
                }
            } catch (_error) {
                invalidatePreview();
                message(previewBox, 'No se pudo completar la solicitud. No repitas la operación sin verificar el estado.');
            } finally {
                buttons.find((item) => item.dataset.wemBulkAction === 'preview').disabled = false;
            }
        });
    }
}());
