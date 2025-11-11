define(function () {
    'use strict';

    return function () {
            // Remove the URL hash token to prevent refreshes trying to resume the same payment.
            const params = new URLSearchParams(window.location.search);
            params.delete('token');
            window.history.replaceState('', document.title, window.location.pathname + '?' + params.toString());
    };
});
