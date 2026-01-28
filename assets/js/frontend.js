/**
 * Kiyoh Widget Frontend JavaScript
 * Handles sticky widget open/close functionality
 */

(function() {
    'use strict';

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

        var mode = widget.getAttribute('data-mode') || 'expandable';
        var closeBtn = widget.querySelector('.kiyoh-sticky-close');

        // Handle badge mode (always visible with dismiss option)
        if (mode === 'badge') {
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    widget.classList.add('is-hidden');

                    // Store dismissal in session storage
                    try {
                        sessionStorage.setItem('kiyoh_widget_dismissed', '1');
                    } catch (err) {
                        // Session storage not available
                    }

                    document.dispatchEvent(new CustomEvent('kiyoh:widget:dismissed'));
                });
            }

            // Check if previously dismissed this session
            try {
                if (sessionStorage.getItem('kiyoh_widget_dismissed') === '1') {
                    widget.classList.add('is-hidden');
                }
            } catch (err) {
                // Session storage not available
            }

            // Expose API
            window.KiyohWidget = {
                show: function() {
                    widget.classList.remove('is-hidden');
                    try {
                        sessionStorage.removeItem('kiyoh_widget_dismissed');
                    } catch (err) {}
                },
                hide: function() {
                    widget.classList.add('is-hidden');
                },
                isVisible: function() {
                    return !widget.classList.contains('is-hidden');
                }
            };

            return;
        }

        // Handle expandable mode
        var tab = widget.querySelector('.kiyoh-sticky-tab');
        var panel = widget.querySelector('.kiyoh-sticky-panel');

        if (!tab || !panel || !closeBtn) {
            return;
        }

        function openWidget() {
            widget.classList.add('is-open');
            panel.setAttribute('aria-hidden', 'false');
            tab.setAttribute('aria-expanded', 'true');
            closeBtn.focus();
            document.dispatchEvent(new CustomEvent('kiyoh:widget:opened'));
        }

        function closeWidget() {
            widget.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
            tab.setAttribute('aria-expanded', 'false');
            tab.focus();
            document.dispatchEvent(new CustomEvent('kiyoh:widget:closed'));
        }

        function toggleWidget() {
            if (widget.classList.contains('is-open')) {
                closeWidget();
            } else {
                openWidget();
            }
        }

        // Tab click
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openWidget();
        });

        // Tab keyboard
        tab.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openWidget();
            }
        });

        // Close button
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeWidget();
        });

        // Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && widget.classList.contains('is-open')) {
                closeWidget();
            }
        });

        // Click outside
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

            if (deltaY > Math.abs(deltaX)) {
                return;
            }

            var isLeftPosition = widget.classList.contains('kiyoh-sticky-left');
            var isOpen = widget.classList.contains('is-open');

            if (isOpen) {
                if (isLeftPosition && deltaX < -swipeThreshold) {
                    closeWidget();
                } else if (!isLeftPosition && deltaX > swipeThreshold) {
                    closeWidget();
                }
            } else {
                if (isLeftPosition && deltaX > swipeThreshold) {
                    openWidget();
                } else if (!isLeftPosition && deltaX < -swipeThreshold) {
                    openWidget();
                }
            }
        }

        // Initial ARIA attributes
        panel.setAttribute('aria-hidden', 'true');
        tab.setAttribute('aria-expanded', 'false');
        tab.setAttribute('role', 'button');
        tab.setAttribute('tabindex', '0');

        // Expose API
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
