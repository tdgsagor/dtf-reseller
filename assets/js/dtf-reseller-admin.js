jQuery(document).ready(function ($) {
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page');

    if (page && page.startsWith('dtfreseller')) {
        $('body, html').addClass('dtfreseller-page');
    }

    $('.toggle-password').on('click', function () {
        var targetId = $(this).data('target');
        var $input = $('#' + targetId);
        var isPassword = $input.attr('type') === 'password';

        $input.attr('type', isPassword ? 'text' : 'password');

        $(this).toggleClass('dashicons-visibility dashicons-hidden');
    });
});
