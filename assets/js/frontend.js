/**
 * Kiyoh Widget Frontend JavaScript
 * Handles sticky widget open/close functionality
 */

(function() {
    'use strict';

    // Wait for DOM ready
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function() {
        var widget = document.getElementById('kiyoh-sticky-widget');

        if (!widget) {
            return;
        }

        var tab = widget.querySelector('.kiyoh-sticky-tab');
        var panel = widget.querySelector('.kiyoh-sticky-panel');
        var closeBtn = widget.querySelector('.kiyoh-sticky-close');

        if (!tab || !panel || !closeBtn) {
            return;
        }

        // Open widget
        function openWidget() {
            widget.classList.add('is-open');
            panel.setAttribute('aria-hidden', 'false');
            tab.setAttribute('aria-expanded', 'true');
            closeBtn.focus();

            // Dispatch custom event
            document.dispatchEvent(new CustomEvent('kiyoh:widget:opened'));
        }

        // Close widget
        function closeWidget() {
            widget.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
            tab.setAttribute('aria-expanded', 'false');
            tab.focus();

            // Dispatch custom event
            document.dispatchEvent(new CustomEvent('kiyoh:widget:closed'));
        }

        // Toggle widget
        function toggleWidget() {
            if (widget.classList.contains('is-open')) {
                closeWidget();
            } else {
                openWidget();
            }
        }

        // Tab click handler
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openWidget();
        });

        // Tab keyboard handler
        tab.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openWidget();
            }
        });

        // Close button handler
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeWidget();
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && widget.classList.contains('is-open')) {
                closeWidget();
            }
        });

        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (widget.classList.contains('is-open')) {
                if (!widget.contains(e.target)) {
                    closeWidget();
                }
            }
        });

        // Touch swipe support
        var touchStartX = 0;
        var touchEndX = 0;
        var touchStartY = 0;
        var touchEndY = 0;

        widget.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        widget.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            handleSwipe();
        }, { passive: true });

        function handleSwipe() {
            var swipeThreshold = 50;
            var deltaX = touchEndX - touchStartX;
            var deltaY = Math.abs(touchEndY - touchStartY);

            // Only handle horizontal swipes
            if (deltaY > Math.abs(deltaX)) {
                return;
            }

            var isLeftPosition = widget.classList.contains('kiyoh-sticky-left');
            var isOpen = widget.classList.contains('is-open');

            if (isOpen) {
                // Swipe to close
                if (isLeftPosition && deltaX < -swipeThreshold) {
                    closeWidget();
                } else if (!isLeftPosition && deltaX > swipeThreshold) {
                    closeWidget();
                }
            } else {
                // Swipe to open
                if (isLeftPosition && deltaX > swipeThreshold) {
                    openWidget();
                } else if (!isLeftPosition && deltaX < -swipeThreshold) {
                    openWidget();
                }
            }
        }

        // Set initial ARIA attributes
        panel.setAttribute('aria-hidden', 'true');
        tab.setAttribute('aria-expanded', 'false');
        tab.setAttribute('role', 'button');
        tab.setAttribute('tabindex', '0');

        // Expose API for external use
        window.KiyohWidget = {
            open: openWidget,
            close: closeWidget,
            toggle: toggleWidget,
            isOpen: function() {
                return widget.classList.contains('is-open');
            }
        };
    });

})();
