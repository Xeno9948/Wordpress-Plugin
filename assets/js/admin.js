/**
 * Kiyoh Widget Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Live preview update
        var previewTimeout;

        function updatePreview() {
            var tenantId = $('#tenant_id').val();
            var locationId = $('#location_id').val();

            if (!tenantId || !locationId) {
                $('#kiyoh-preview-container').html(
                    '<p class="kiyoh-preview-placeholder">Enter your Tenant ID and Location ID to see a preview.</p>'
                );
                return;
            }

            var color = $('#color').val();
            var language = $('#language').val();
            var border = $('input[name="kiyoh_widget_options[border]"]:checked').val();
            var transparent = $('input[name="kiyoh_widget_options[transparent]"]:checked').val();
            var button = $('input[name="kiyoh_widget_options[button]"]:checked').val();
            var height = $('#height').val() || 222;

            var params = {
                color: color,
                allowTransparency: transparent === 'on' ? 'true' : 'false',
                button: button === 'on' ? 'true' : 'false',
                lang: language,
                tenantId: tenantId,
                locationId: locationId
            };

            var url = 'https://www.kiyoh.com/retrieve-widget.html?' + $.param(params);
            var frameborder = border === 'on' ? '1' : '0';
            var allowTransparency = transparent === 'on' ? 'true' : 'false';

            var iframe = '<iframe ' +
                'frameborder="' + frameborder + '" ' +
                'allowtransparency="' + allowTransparency + '" ' +
                'src="' + url + '" ' +
                'width="100%" ' +
                'height="' + height + '" ' +
                'title="Kiyoh Reviews Preview">' +
                '</iframe>';

            $('#kiyoh-preview-container').html(iframe);
        }

        // Debounced preview update
        function schedulePreviewUpdate() {
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(updatePreview, 500);
        }

        // Bind change events
        $('#tenant_id, #location_id, #color, #language, #width, #height').on('input change', schedulePreviewUpdate);
        $('input[name="kiyoh_widget_options[border]"], input[name="kiyoh_widget_options[transparent]"], input[name="kiyoh_widget_options[button]"]').on('change', schedulePreviewUpdate);

        // Sticky widget toggle visibility
        function toggleStickyOptions() {
            var stickyEnabled = $('input[name="kiyoh_widget_options[sticky_enabled]"]:checked').val();
            var $stickyOptions = $('input[name="kiyoh_widget_options[sticky_position]"], input[name="kiyoh_widget_options[sticky_vertical]"], input[name="kiyoh_widget_options[sticky_glass]"]').closest('tr');
            var $stickySelects = $('#sticky_position, #sticky_vertical').closest('tr');

            if (stickyEnabled === 'on') {
                $stickyOptions.show();
                $stickySelects.show();
            } else {
                $stickyOptions.hide();
                $stickySelects.hide();
            }
        }

        $('input[name="kiyoh_widget_options[sticky_enabled]"]').on('change', toggleStickyOptions);
        toggleStickyOptions(); // Initial state

        // Copy shortcode to clipboard
        $('.kiyoh-admin-box code').on('click', function() {
            var $this = $(this);
            var text = $this.text();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopyFeedback($this);
                });
            } else {
                // Fallback for older browsers
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                showCopyFeedback($this);
            }
        });

        function showCopyFeedback($element) {
            var originalBg = $element.css('background');
            $element.css('background', '#d4edda');
            setTimeout(function() {
                $element.css('background', originalBg);
            }, 500);
        }

        // Form validation
        $('form').on('submit', function(e) {
            var tenantId = $('#tenant_id').val();
            var locationId = $('#location_id').val();
            var stickyEnabled = $('input[name="kiyoh_widget_options[sticky_enabled]"]:checked').val();

            // Warn if sticky is enabled but no IDs
            if (stickyEnabled === 'on' && (!tenantId || !locationId)) {
                if (!confirm('Sticky widget is enabled but Tenant ID or Location ID is missing. The widget will not display until these are configured. Continue saving?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Add cursor pointer to copyable codes
        $('.kiyoh-admin-box code').css('cursor', 'pointer').attr('title', 'Click to copy');
    });

})(jQuery);
