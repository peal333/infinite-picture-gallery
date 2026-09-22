document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var lightbox = document.getElementById('pealipg-lightbox');
    if (!lightbox) {
        return;
    }

    var image = lightbox.querySelector('.pealipg-lightbox-content img');
    var closeButton = lightbox.querySelector('.pealipg-lightbox-close');
    var triggers = document.querySelectorAll('.pealipg-lightbox-trigger');
    var previouslyFocused = null;

    if (!image || !closeButton || !triggers.length) {
        return;
    }

    function closeLightbox() {
        lightbox.hidden = true;
        document.documentElement.classList.remove('pealipg-lightbox-open');
        image.removeAttribute('src');
        image.alt = '';

        if (previouslyFocused && 'function' === typeof previouslyFocused.focus) {
            previouslyFocused.focus();
        }
        previouslyFocused = null;
    }

    function openLightbox(trigger) {
        var source = trigger.getAttribute('data-pealipg-lightbox-src');
        if (!source) {
            return;
        }

        previouslyFocused = trigger;
        image.src = source;
        image.alt = trigger.getAttribute('data-pealipg-lightbox-alt') || '';
        lightbox.hidden = false;
        document.documentElement.classList.add('pealipg-lightbox-open');
        closeButton.focus();
    }

    Array.prototype.forEach.call(triggers, function (trigger) {
        trigger.addEventListener('click', function () {
            openLightbox(trigger);
        });
    });

    closeButton.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function (event) {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    lightbox.addEventListener('keydown', function (event) {
        if ('Escape' === event.key) {
            event.preventDefault();
            closeLightbox();
            return;
        }

        if ('Tab' !== event.key) {
            return;
        }

        var focusable = lightbox.querySelectorAll('button:not([disabled]), [href], [tabindex]:not([tabindex="-1"])');
        if (!focusable.length) {
            event.preventDefault();
            lightbox.focus();
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
});
