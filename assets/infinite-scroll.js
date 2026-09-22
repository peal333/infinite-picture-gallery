document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var container = document.getElementById('pealipg-gallery-container');
    var sentinel = document.getElementById('pealipg-load-more-sentinel');

    if (!container || !sentinel || 'undefined' === typeof pealipgVars || 'function' !== typeof window.fetch || 'function' !== typeof window.FormData) {
        return;
    }

    document.documentElement.classList.add('pealipg-js');

    var loaderText = sentinel.querySelector('.pealipg-loader-text');
    var loadButton = sentinel.querySelector('.pealipg-load-more-button');
    var page = parseInt(sentinel.getAttribute('data-pealipg-current-page'), 10) || 1;
    var maxPages = parseInt(sentinel.getAttribute('data-pealipg-max-pages'), 10) || page;
    var isLoading = false;
    var observer = null;
    var statusTimer = null;
    var loadedIds = {};

    Array.prototype.forEach.call(container.querySelectorAll('[data-pealipg-picture-id]'), function (item) {
        loadedIds[item.getAttribute('data-pealipg-picture-id')] = true;
    });

    function setStatus(message, state) {
        if (statusTimer) {
            window.clearTimeout(statusTimer);
            statusTimer = null;
        }

        if (loaderText) {
            loaderText.textContent = message;
        }

        sentinel.classList.toggle('is-active', 'loading' === state);
        sentinel.classList.toggle('is-complete', 'complete' === state);
        sentinel.classList.toggle('has-error', 'error' === state);
        sentinel.classList.toggle('has-update', 'update' === state);

        if ('update' === state) {
            statusTimer = window.setTimeout(function () {
                sentinel.classList.remove('has-update');
            }, 2500);
        }
    }

    function enableVideoPreview(scope) {
        var videos = scope.querySelectorAll('.pealipg-grid-video:not([data-pealipg-preview-ready])');
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        Array.prototype.forEach.call(videos, function (video) {
            var link = video.closest ? video.closest('.pealipg-grid-img-link') : null;
            video.setAttribute('data-pealipg-preview-ready', '1');

            if (reduceMotion) {
                return;
            }

            function playVideo() {
                var promise = video.play();
                if (promise && 'function' === typeof promise.catch) {
                    promise.catch(function () {});
                }
            }

            function pauseVideo() {
                video.pause();
            }

            video.addEventListener('mouseenter', playVideo);
            video.addEventListener('mouseleave', pauseVideo);

            if (link) {
                link.addEventListener('focus', playVideo);
                link.addEventListener('blur', pauseVideo);
            }
        });
    }

    function stopLoading() {
        if (observer) {
            observer.disconnect();
        }
        if (loadButton) {
            loadButton.hidden = true;
        }
        setStatus(pealipgVars.end, 'complete');
    }

    function showFallback() {
        if (observer) {
            observer.disconnect();
        }

        setStatus(pealipgVars.error, 'error');
        if (loadButton) {
            loadButton.textContent = pealipgVars.continue;
            loadButton.hidden = false;
            loadButton.setAttribute('data-pealipg-fallback', '1');
        }
    }

    function appendCards(html) {
        var holder = document.createElement('div');
        var appended = 0;
        holder.innerHTML = html;

        Array.prototype.forEach.call(holder.querySelectorAll('[data-pealipg-picture-id]'), function (item) {
            var pictureId = item.getAttribute('data-pealipg-picture-id');
            if (!pictureId || loadedIds[pictureId]) {
                return;
            }

            loadedIds[pictureId] = true;
            container.appendChild(item);
            appended += 1;
        });

        return appended;
    }

    function loadMorePictures() {
        if (isLoading || page >= maxPages) {
            return;
        }

        isLoading = true;
        if (observer) {
            observer.unobserve(sentinel);
        }
        if (loadButton) {
            loadButton.setAttribute('aria-disabled', 'true');
        }
        setStatus(pealipgVars.loading, 'loading');

        var nextPage = page + 1;
        var formData = new FormData();
        var controller = 'function' === typeof window.AbortController ? new window.AbortController() : null;
        var timeoutId = null;

        formData.append('action', pealipgVars.action);
        formData.append('pealipg_page', nextPage);
        formData.append('pealipg_nonce', pealipgVars.nonce);

        var requestOptions = {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        };

        if (controller) {
            requestOptions.signal = controller.signal;
            timeoutId = window.setTimeout(function () {
                controller.abort();
            }, 15000);
        }

        fetch(pealipgVars.ajax_url, requestOptions)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (response) {
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }

                if (!response || !response.success || !response.data) {
                    throw new Error('Invalid response');
                }

                var data = response.data;
                var appended = data.html ? appendCards(data.html) : 0;
                page = parseInt(data.page, 10) || nextPage;
                maxPages = parseInt(data.max_pages, 10) || maxPages;
                sentinel.setAttribute('data-pealipg-current-page', page);
                sentinel.setAttribute('data-pealipg-max-pages', maxPages);
                enableVideoPreview(container);
                isLoading = false;

                if (loadButton) {
                    loadButton.removeAttribute('aria-disabled');
                    loadButton.removeAttribute('data-pealipg-fallback');
                    loadButton.textContent = pealipgVars.load_more;
                }

                if (!data.has_more || page >= maxPages) {
                    stopLoading();
                    return;
                }

                if (loadButton && data.next_url) {
                    loadButton.href = data.next_url;
                }

                setStatus(pealipgVars.loaded.replace('%d', appended), 'update');
                if (observer) {
                    observer.observe(sentinel);
                }
            })
            .catch(function () {
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }
                isLoading = false;
                if (loadButton) {
                    loadButton.removeAttribute('aria-disabled');
                }
                showFallback();
            });
    }

    enableVideoPreview(container);

    if (loadButton) {
        loadButton.addEventListener('click', function (event) {
            if (loadButton.hasAttribute('data-pealipg-fallback')) {
                return;
            }
            event.preventDefault();
            loadMorePictures();
        });
    }

    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(function (entries) {
            Array.prototype.forEach.call(entries, function (entry) {
                if (entry.isIntersecting && !isLoading) {
                    loadMorePictures();
                }
            });
        }, {
            root: null,
            rootMargin: '240px',
            threshold: 0.05
        });

        observer.observe(sentinel);
    }
});
