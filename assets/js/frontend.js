(function ($) {
    'use strict';

    // Configuración global
    const WEM = {
        ajaxUrl: wem_ajax.url,
        nonce: wem_ajax.nonce,

        init: function () {
            this.setupCheckinHandlers();
            this.setupListHandlers();
        },

        setupCheckinHandlers: function () {
            // Los handlers específicos de checkin se manejan en los shortcodes
            // Este archivo es para funcionalidades globales
        },

        setupListHandlers: function () {
            // Handlers globales para listas
            this.setupClickableRows();
        },

        setupClickableRows: function () {
            $(document).on('click', '.wem-clickable-row', function (e) {
                if ($(e.target).is('button') || $(e.target).closest('button').length) {
                    return;
                }

                const href = $(this).data('href');
                if (href && href !== '#') {
                    window.location.href = href;
                }
            });
        },

        // Helper para AJAX requests
        ajaxRequest: function (data, successCallback, errorCallback) {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: data,
                success: successCallback,
                error: errorCallback || function (xhr, status, error) {
                    console.error('WEM AJAX Error:', error);
                    alert('Error de conexión. Por favor intenta nuevamente.');
                }
            });
        },

        // Helper para mostrar mensajes
        showMessage: function (element, message, type) {
            const colors = {
                success: 'green',
                error: 'red',
                warning: 'orange'
            };

            element.html(message)
                .css('color', colors[type] || 'black')
                .show();

            if (type === 'success') {
                setTimeout(() => element.fadeOut(), 3000);
            }
        }
    };

    // Inicializar cuando el documento esté listo
    $(document).ready(function () {
        WEM.init();
    });

})(jQuery);