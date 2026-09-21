document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var container = document.getElementById('ipg-gallery-container');
    var sentinel = document.getElementById('ipg-load-more-sentinel');

    if (!container || !sentinel || 'undefined' === typeof infinitePictureGalleryVars) {
        return;
    }

    var loaderText = sentinel.querySelector('.ipg-loader-text');
    var page = 1;
    var isLoading = false;
    var observer = null;

    function setStatus(message, state) {
        if (loaderText) {
            loaderText.textContent = message;
        }
        sentinel.classList.toggle('is-active', 'loading' === state);
        sentinel.classList.toggle('is-complete', 'complete' === state);
        sentinel.classList.toggle('has-error', 'error' === state);
    }

    function enableVideoPreview(scope) {
        var videos = scope.querySelectorAll('.ipg-grid-video:not([data-ipg-preview-ready])');

        videos.forEach(function (video) {
            var link = video.closest('.ipg-grid-img-link');
            video.setAttribute('data-ipg-preview-ready', '1');

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
        var loadButton = sentinel.querySelector('.ipg-retry-button');

        if (observer) {
            observer.disconnect();
        }
        if (loadButton) {
            loadButton.hidden = true;
        }
        setStatus(infinitePictureGalleryVars.end, 'complete');
    }

    function showRetry() {
        if (observer) {
            observer.disconnect();
        }

        setStatus(infinitePictureGalleryVars.error, 'error');

        var existingButton = sentinel.querySelector('.ipg-retry-button');
        if (existingButton) {
            existingButton.textContent = infinitePictureGalleryVars.retry;
            existingButton.hidden = false;
            return;
        }

        var retryButton = document.createElement('button');
        retryButton.type = 'button';
        retryButton.className = 'ipg-retry-button';
        retryButton.textContent = infinitePictureGalleryVars.retry;
        retryButton.addEventListener('click', function () {
            retryButton.hidden = true;
            loadMorePictures();
        });
        sentinel.appendChild(retryButton);
    }

    function loadMorePictures() {
        if (isLoading) {
            return;
        }

        isLoading = true;
        setStatus(infinitePictureGalleryVars.loading, 'loading');

        var nextPage = page + 1;
        var formData = new FormData();
        formData.append('action', infinitePictureGalleryVars.action);
        formData.append('page', nextPage);
        formData.append('nonce', infinitePictureGalleryVars.nonce);

        fetch(infinitePictureGalleryVars.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (response) {
                if (response.success && response.data && response.data.html) {
                    container.insertAdjacentHTML('beforeend', response.data.html);
                    page = nextPage;
                    enableVideoPreview(container);
                    setStatus(infinitePictureGalleryVars.loading, 'idle');
                    isLoading = false;
                    if (observer) {
                        observer.observe(sentinel);
                    } else {
                        var loadButton = sentinel.querySelector('.ipg-retry-button');
                        if (loadButton) {
                            loadButton.textContent = infinitePictureGalleryVars.load_more;
                            loadButton.hidden = false;
                        }
                    }
                    return;
                }

                isLoading = false;
                stopLoading();
            })
            .catch(function () {
                isLoading = false;
                showRetry();
            });
    }

    enableVideoPreview(container);

    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
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
    } else {
        var fallbackButton = document.createElement('button');
        fallbackButton.type = 'button';
        fallbackButton.className = 'ipg-retry-button';
        fallbackButton.textContent = infinitePictureGalleryVars.load_more;
        fallbackButton.addEventListener('click', loadMorePictures);
        sentinel.appendChild(fallbackButton);
        setStatus('', 'idle');
    }
});
