/**
 * Kiyoh Widget Frontend JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $stickyWidget = $('#kiyoh-sticky-widget');

        if (!$stickyWidget.length) {
            return;
        }

        var $toggle = $stickyWidget.find('.kiyoh-sticky-toggle');
        var $content = $stickyWidget.find('.kiyoh-sticky-content');
        var $close = $stickyWidget.find('.kiyoh-sticky-close');

        // Open widget
        $toggle.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openWidget();
        });

        // Close widget
        $close.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeWidget();
        });

        // Close on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $stickyWidget.hasClass('kiyoh-sticky-open')) {
                closeWidget();
            }
        });

        // Close when clicking outside
        $(document).on('click', function(e) {
            if ($stickyWidget.hasClass('kiyoh-sticky-open')) {
                if (!$(e.target).closest('#kiyoh-sticky-widget').length) {
                    closeWidget();
                }
            }
        });

        function openWidget() {
            $stickyWidget.addClass('kiyoh-sticky-open');
            $content.attr('aria-hidden', 'false');
            $close.focus();

            // Trigger custom event
            $(document).trigger('kiyoh:widget:opened');
        }

        function closeWidget() {
            $stickyWidget.removeClass('kiyoh-sticky-open');
            $content.attr('aria-hidden', 'true');
            $toggle.focus();

            // Trigger custom event
            $(document).trigger('kiyoh:widget:closed');
        }

        // Touch support for mobile
        var touchStartX = 0;
        var touchEndX = 0;

        $stickyWidget.on('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        });

        $stickyWidget.on('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });

        function handleSwipe() {
            var swipeThreshold = 50;
            var isLeftPosition = $stickyWidget.hasClass('kiyoh-sticky-left');

            if ($stickyWidget.hasClass('kiyoh-sticky-open')) {
                // Swipe to close
                if (isLeftPosition && touchEndX < touchStartX - swipeThreshold) {
                    closeWidget();
                } else if (!isLeftPosition && touchEndX > touchStartX + swipeThreshold) {
                    closeWidget();
                }
            } else {
                // Swipe to open
                if (isLeftPosition && touchEndX > touchStartX + swipeThreshold) {
                    openWidget();
                } else if (!isLeftPosition && touchEndX < touchStartX - swipeThreshold) {
                    openWidget();
                }
            }
        }

        // Lazy load iframe on first open
        var iframeLoaded = false;
        var $iframe = $content.find('iframe');
        var iframeSrc = $iframe.attr('src');

        // If we want lazy loading, uncomment this:
        // $iframe.removeAttr('src');
        //
        // $toggle.one('click', function() {
        //     if (!iframeLoaded) {
        //         $iframe.attr('src', iframeSrc);
        //         iframeLoaded = true;
        //     }
        // });

        // Accessibility: Set initial aria states
        $content.attr('aria-hidden', 'true');
        $toggle.attr({
            'role': 'button',
            'aria-expanded': 'false',
            'aria-controls': 'kiyoh-sticky-content',
            'tabindex': '0'
        });

        // Keyboard support for toggle
        $toggle.on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openWidget();
            }
        });

        // Update aria-expanded when state changes
        $(document).on('kiyoh:widget:opened', function() {
            $toggle.attr('aria-expanded', 'true');
        });

        $(document).on('kiyoh:widget:closed', function() {
            $toggle.attr('aria-expanded', 'false');
        });

        // Expose API for external use
        window.KiyohWidget = {
            open: openWidget,
            close: closeWidget,
            toggle: function() {
                if ($stickyWidget.hasClass('kiyoh-sticky-open')) {
                    closeWidget();
                } else {
                    openWidget();
                }
            },
            isOpen: function() {
                return $stickyWidget.hasClass('kiyoh-sticky-open');
            }
        };
    });

})(jQuery);
