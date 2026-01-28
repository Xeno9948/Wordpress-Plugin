/**
 * Kiyoh Widget Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize color picker
        if ($.fn.wpColorPicker) {
            $('.kiyoh-color-picker').wpColorPicker();
        }

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
            var transparent = $('input[name="kiyoh_widget_options[transparent]"]:checked').val();
            var button = $('input[name="kiyoh_widget_options[button]"]:checked').val();
            var height = $('#height').val() || 222;
            var width = $('#width').val() || 400;

            var params = {
                color: color,
                allowTransparency: transparent === 'on' ? 'true' : 'false',
                button: button === 'on' ? 'true' : 'false',
                lang: language,
                tenantId: tenantId,
                locationId: locationId
            };

            var url = 'https://www.kiyoh.com/retrieve-widget.html?' + $.param(params);

            var iframe = '<iframe ' +
                'frameborder="0" ' +
                'allowtransparency="' + (transparent === 'on' ? 'true' : 'false') + '" ' +
                'src="' + url + '" ' +
                'width="100%" ' +
                'height="' + height + '" ' +
                'style="border:none; border-radius: 12px;" ' +
                'title="Kiyoh Reviews Preview">' +
                '</iframe>';

            $('#kiyoh-preview-container').html(iframe);
        }

        // Debounced preview update
        function schedulePreviewUpdate() {
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(updatePreview, 500);
        }

        // Bind change events for preview
        $('#tenant_id, #location_id, #color, #language, #width, #height').on('input change', schedulePreviewUpdate);
        $('input[name="kiyoh_widget_options[transparent]"], input[name="kiyoh_widget_options[button]"]').on('change', schedulePreviewUpdate);

        // Toggle standard widget options visibility
        function toggleStandardOptions() {
            var enabled = $('input[name="kiyoh_widget_options[standard_enabled]"]:checked').val();
            if (enabled === 'on') {
                $('.kiyoh-standard-options').show();
            } else {
                $('.kiyoh-standard-options').hide();
            }
        }

        $('input[name="kiyoh_widget_options[standard_enabled]"]').on('change', toggleStandardOptions);
        toggleStandardOptions();

        // Toggle sticky widget options visibility
        function toggleStickyOptions() {
            var enabled = $('input[name="kiyoh_widget_options[sticky_enabled]"]:checked').val();
            if (enabled === 'on') {
                $('.kiyoh-sticky-options').show();
            } else {
                $('.kiyoh-sticky-options').hide();
            }
        }

        $('input[name="kiyoh_widget_options[sticky_enabled]"]').on('change', toggleStickyOptions);
        toggleStickyOptions();

        // Copy shortcode to clipboard
        $('.kiyoh-admin-box code').on('click', function() {
            var $this = $(this);
            var text = $this.text();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopyFeedback($this);
                });
            } else {
                // Fallback
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

        // Add cursor pointer to copyable codes
        $('.kiyoh-admin-box code').css('cursor', 'pointer').attr('title', 'Click to copy');

        // Form validation
        $('form').on('submit', function(e) {
            var tenantId = $('#tenant_id').val();
            var locationId = $('#location_id').val();
            var stickyEnabled = $('input[name="kiyoh_widget_options[sticky_enabled]"]:checked').val();
            var standardEnabled = $('input[name="kiyoh_widget_options[standard_enabled]"]:checked').val();

            if ((stickyEnabled === 'on' || standardEnabled === 'on') && (!tenantId || !locationId)) {
                if (!confirm('Widget is enabled but Tenant ID or Location ID is missing. The widget will not display until these are configured. Continue saving?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    });

})(jQuery);
