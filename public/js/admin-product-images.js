(function () {
    'use strict';

    var debounceTimer;

    function resetPondLayout(root) {
        if (!root || !root.querySelector('.filepond--item')) {
            return;
        }

        root.style.setProperty('height', 'auto', 'important');
        root.style.setProperty('overflow', 'hidden', 'important');

        var scroller = root.querySelector('.filepond--list-scroller');
        if (scroller) {
            scroller.style.setProperty('position', 'relative', 'important');
            scroller.style.setProperty('transform', 'none', 'important');
            scroller.style.setProperty('height', 'auto', 'important');
            scroller.style.setProperty('margin', '0', 'important');
        }

        var panel = root.querySelector(':scope > .filepond--panel-root');
        if (panel) {
            panel.style.setProperty('position', 'relative', 'important');
            panel.style.setProperty('transform', 'none', 'important');
        }

        root.querySelectorAll('.filepond--item').forEach(function (item) {
            item.style.setProperty('position', 'relative', 'important');
            item.style.setProperty('transform', 'none', 'important');
        });
    }

    function fixAll() {
        document.querySelectorAll('.gs-product-images-field .filepond--root, .gs-product-images-upload .filepond--root')
            .forEach(resetPondLayout);
    }

    function scheduleFix() {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(fixAll, 30);
    }

    function boot() {
        fixAll();

        document.querySelectorAll('.gs-product-images-field, .gs-product-images-upload').forEach(function (container) {
            new MutationObserver(scheduleFix).observe(container, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class'],
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', scheduleFix);

    document.addEventListener('livewire:init', function () {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') {
            return;
        }

        window.Livewire.hook('morph.updated', function (_ref) {
            var el = _ref.el;

            if (el && el.closest && el.closest('.gs-product-images-field, .gs-product-images-upload')) {
                scheduleFix();
            }
        });
    });
})();
