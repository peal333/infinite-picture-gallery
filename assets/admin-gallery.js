(function ($) {
    'use strict';

    function initGalleryEditor() {
        var frame;
        var $galleryIdsInput = $('#pealipg_gallery_ids');
        var $preview = $('#pealipg-gallery-preview');
        var $emptyState = $('#pealipg-gallery-empty');
        var $count = $('#pealipg-gallery-count');

        if (!$galleryIdsInput.length || !$preview.length) {
            return;
        }

        function getIds() {
            var value = $galleryIdsInput.val();
            return value ? value.split(',').filter(Boolean) : [];
        }

        function updateGalleryState() {
            var ids = [];

            $preview.find('.pealipg-media-item').each(function () {
                ids.push(String($(this).attr('data-pealipg-id')));
            });

            $galleryIdsInput.val(ids.join(','));
            $count.text(ids.length);
            $emptyState.prop('hidden', ids.length > 0);
        }

        function createMediaItem(attachment) {
            var $item = $('<li>', {
                'class': 'pealipg-media-item',
                'data-pealipg-id': attachment.id
            });
            var $handle = $('<span>', {
                'class': 'pealipg-drag-handle dashicons dashicons-menu',
                role: 'button',
                tabindex: '0',
                'aria-label': pealipgAdmin.reorderLabel
            });
            var $mediaWrap = $('<div>', {'class': 'pealipg-media-preview'});
            var $remove = $('<button>', {
                type: 'button',
                'class': 'pealipg-remove-image',
                'aria-label': pealipgAdmin.removeLabel
            }).append($('<span>', {'aria-hidden': 'true'}).html('&times;'));

            if ('video' === attachment.type) {
                $('<video>', {
                    src: attachment.url + '#t=0.5',
                    muted: true,
                    preload: 'metadata',
                    'aria-hidden': 'true'
                }).appendTo($mediaWrap);

                $('<span>', {
                    'class': 'dashicons dashicons-controls-play pealipg-video-indicator',
                    'aria-hidden': 'true'
                }).appendTo($mediaWrap);
            } else {
                var imageUrl = attachment.url;
                if (attachment.sizes && attachment.sizes.thumbnail) {
                    imageUrl = attachment.sizes.thumbnail.url;
                }

                $('<img>', {
                    src: imageUrl,
                    alt: ''
                }).appendTo($mediaWrap);
            }

            return $item.append($handle, $mediaWrap, $remove);
        }

        $preview.sortable({
            items: '.pealipg-media-item',
            handle: '.pealipg-drag-handle',
            cursor: 'move',
            tolerance: 'pointer',
            update: updateGalleryState
        });

        $('#pealipg-add-gallery-images').on('click', function (event) {
            event.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: pealipgAdmin.mediaFrameTitle,
                button: {text: pealipgAdmin.mediaFrameButton},
                library: {type: ['image', 'video']},
                multiple: true
            });

            frame.on('select', function () {
                var currentIds = getIds();

                frame.state().get('selection').each(function (model) {
                    var attachment = model.toJSON();
                    var id = String(attachment.id);

                    if (-1 === currentIds.indexOf(id)) {
                        currentIds.push(id);
                        $preview.append(createMediaItem(attachment));
                    }
                });

                updateGalleryState();
            });

            frame.open();
        });

        $preview.on('click', '.pealipg-remove-image', function () {
            $(this).closest('.pealipg-media-item').remove();
            updateGalleryState();
        });

        $preview.on('keydown', '.pealipg-drag-handle', function (event) {
            var $item = $(this).closest('.pealipg-media-item');
            var moved = false;

            if ('ArrowLeft' === event.key || 'ArrowUp' === event.key) {
                var $previous = $item.prev('.pealipg-media-item');
                if ($previous.length) {
                    $item.insertBefore($previous);
                    moved = true;
                }
            } else if ('ArrowRight' === event.key || 'ArrowDown' === event.key) {
                var $next = $item.next('.pealipg-media-item');
                if ($next.length) {
                    $item.insertAfter($next);
                    moved = true;
                }
            }

            if (moved) {
                event.preventDefault();
                updateGalleryState();
                $item.find('.pealipg-drag-handle').trigger('focus');
            }
        });

        updateGalleryState();
    }

    function normalizeStatePath(path) {
        return path.join('').replace(/[^a-z0-9]/gi, '').toLowerCase();
    }

    function getAioseoDesiredValue(path, imageUrl) {
        var normalized = normalizeStatePath(path);

        if (/(ogimagetype|facebookimagetype|facebookimagesource|opengraphimagetype|opengraphimagesource)$/.test(normalized)) {
            return {matched: true, value: 'custom_image'};
        }

        if (/(ogimagecustomurl|ogimageurl|facebookimagecustomurl|facebookimageurl|opengraphimagecustomurl|opengraphimageurl)$/.test(normalized)) {
            return {matched: true, value: imageUrl};
        }

        if (/(twitteruseog|twitterusefacebook|twitterusefacebookdata)$/.test(normalized)) {
            return {matched: true, value: false};
        }

        if (/(twitterimagetype|twitterimagesource)$/.test(normalized)) {
            return {matched: true, value: 'custom_image'};
        }

        if (/(twitterimagecustomurl|twitterimageurl)$/.test(normalized)) {
            return {matched: true, value: imageUrl};
        }

        return {matched: false, value: null};
    }

    function countAioseoStateTargets(object, path, seen, depth) {
        if (!object || 'object' !== typeof object || depth > 7 || -1 !== seen.indexOf(object)) {
            return 0;
        }

        seen.push(object);
        var count = 0;

        Object.keys(object).forEach(function (key) {
            var nextPath = path.concat([key]);
            if (getAioseoDesiredValue(nextPath, '').matched) {
                count += 1;
                return;
            }

            var value = object[key];
            if (value && 'object' === typeof value) {
                count += countAioseoStateTargets(value, nextPath, seen, depth + 1);
            }
        });

        return count;
    }

    function patchAioseoState(object, path, seen, depth, imageUrl) {
        if (!object || 'object' !== typeof object || depth > 7 || -1 !== seen.indexOf(object)) {
            return 0;
        }

        seen.push(object);
        var changed = 0;

        Object.keys(object).forEach(function (key) {
            var nextPath = path.concat([key]);
            var desired = getAioseoDesiredValue(nextPath, imageUrl);

            if (desired.matched) {
                try {
                    object[key] = desired.value;
                    changed += 1;
                } catch (error) {
                    // Continue looking for a writable copy of the AIOSEO editor state.
                }
                return;
            }

            var value = object[key];
            if (value && 'object' === typeof value) {
                changed += patchAioseoState(value, nextPath, seen, depth + 1, imageUrl);
            }
        });

        return changed;
    }

    function getPiniaFromVueApp(app) {
        if (!app) {
            return null;
        }

        if (app.config && app.config.globalProperties && app.config.globalProperties.$pinia) {
            return app.config.globalProperties.$pinia;
        }

        var provides = app._context && app._context.provides ? app._context.provides : null;
        if (!provides) {
            return null;
        }

        var keys = Object.getOwnPropertyNames(provides);
        if ('function' === typeof Object.getOwnPropertySymbols) {
            keys = keys.concat(Object.getOwnPropertySymbols(provides));
        }

        for (var i = 0; i < keys.length; i += 1) {
            var candidate = provides[keys[i]];
            if (candidate && candidate._s && 'function' === typeof candidate._s.forEach) {
                return candidate;
            }
        }

        return null;
    }

    function syncAioseoPiniaState(imageUrl) {
        var apps = [];
        var appRoots = document.querySelectorAll('[id*="aioseo"], [class*="aioseo"]');

        Array.prototype.forEach.call(appRoots, function (element) {
            if (element.__vue_app__ && -1 === apps.indexOf(element.__vue_app__)) {
                apps.push(element.__vue_app__);
            }
        });

        if (!apps.length) {
            Array.prototype.forEach.call(document.querySelectorAll('body *'), function (element) {
                if (element.__vue_app__ && -1 === apps.indexOf(element.__vue_app__)) {
                    apps.push(element.__vue_app__);
                }
            });
        }

        var changed = 0;

        apps.forEach(function (app) {
            var pinia = getPiniaFromVueApp(app);
            if (!pinia || !pinia._s || 'function' !== typeof pinia._s.forEach) {
                return;
            }

            pinia._s.forEach(function (store) {
                if (!store || !store.$state) {
                    return;
                }

                var storeId = String(store.$id || '').toLowerCase();
                var targetCount = countAioseoStateTargets(store.$state, [], [], 0);
                var looksLikePostStore = /post|editor|meta/.test(storeId);

                if (!looksLikePostStore && targetCount < 4) {
                    return;
                }

                if ('function' === typeof store.$patch) {
                    store.$patch(function (state) {
                        changed += patchAioseoState(state, [], [], 0, imageUrl);
                    });
                } else {
                    changed += patchAioseoState(store.$state, [], [], 0, imageUrl);
                }
            });
        });

        return changed;
    }

    function setNativeFieldValue(element, value) {
        var tagName = element.tagName ? element.tagName.toLowerCase() : '';
        var prototype = 'select' === tagName ? window.HTMLSelectElement.prototype : window.HTMLInputElement.prototype;
        if ('textarea' === tagName) {
            prototype = window.HTMLTextAreaElement.prototype;
        }

        var descriptor = Object.getOwnPropertyDescriptor(prototype, 'value');
        if (descriptor && descriptor.set) {
            descriptor.set.call(element, value);
        } else {
            element.value = value;
        }

        element.dispatchEvent(new Event('input', {bubbles: true}));
        element.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function syncAioseoDomFields(imageUrl) {
        var changed = 0;
        var fields = document.querySelectorAll('input, select, textarea');

        Array.prototype.forEach.call(fields, function (field) {
            var descriptor = [
                field.id || '',
                field.name || '',
                field.getAttribute('data-field') || '',
                field.getAttribute('data-key') || '',
                field.getAttribute('aria-label') || ''
            ].join(' ').replace(/[^a-z0-9]/gi, '').toLowerCase();

            if (!descriptor || -1 === descriptor.indexOf('aioseo')) {
                return;
            }

            if (/(ogimagetype|facebookimagetype|facebookimagesource|twitterimagetype|twitterimagesource)/.test(descriptor)) {
                if (field.tagName && 'select' === field.tagName.toLowerCase()) {
                    var hasCustomOption = Array.prototype.some.call(field.options || [], function (option) {
                        return 'custom_image' === option.value;
                    });
                    if (hasCustomOption) {
                        setNativeFieldValue(field, 'custom_image');
                        changed += 1;
                    }
                }
                return;
            }

            if (/(ogimagecustomurl|facebookimagecustomurl|twitterimagecustomurl)/.test(descriptor)) {
                setNativeFieldValue(field, imageUrl);
                changed += 1;
                return;
            }

            if (/(twitteruseog|twitterusefacebook)/.test(descriptor) && 'checkbox' === field.type && field.checked) {
                field.click();
                changed += 1;
            }
        });

        return changed;
    }

    function syncAioseoEditorUi(imageUrl) {
        if (!imageUrl) {
            return;
        }

        // This mirrors the working Social Media Card Generator integration:
        // update AIOSEO's live Vue/Pinia state, with native fields as a fallback.
        syncAioseoPiniaState(imageUrl);
        syncAioseoDomFields(imageUrl);
    }

    function getAttachmentImageUrl(attachmentId, callback) {
        if (!window.wp || !wp.media || 'function' !== typeof wp.media.attachment) {
            return;
        }

        var attachment = wp.media.attachment(attachmentId);
        var imageUrl = attachment.get('url');

        if (imageUrl) {
            callback(imageUrl);
            return;
        }

        var request = attachment.fetch();
        if (request && 'function' === typeof request.done) {
            request.done(function () {
                imageUrl = attachment.get('url');
                if (imageUrl) {
                    callback(imageUrl);
                }
            });
        }
    }

    function initAioseoFeaturedImageSync() {
        if (!pealipgAdmin.aioseoEnabled || !pealipgAdmin.aioseoSupported) {
            return;
        }

        var lastAttachmentId = 0;

        function syncAttachment(attachmentId) {
            attachmentId = parseInt(attachmentId, 10);
            if (!attachmentId || attachmentId === lastAttachmentId) {
                return;
            }

            lastAttachmentId = attachmentId;
            getAttachmentImageUrl(attachmentId, function (imageUrl) {
                syncAioseoEditorUi(imageUrl);
                window.setTimeout(function () { syncAioseoEditorUi(imageUrl); }, 250);
                window.setTimeout(function () { syncAioseoEditorUi(imageUrl); }, 900);
            });
        }

        function syncCurrentFeaturedImage() {
            var attachmentId = parseInt($('#_thumbnail_id').val(), 10);
            if (attachmentId) {
                syncAttachment(attachmentId);
            } else {
                lastAttachmentId = 0;
            }
        }

        function wrapFeaturedImageSetter() {
            if (!window.wp || !wp.media || !wp.media.featuredImage || 'function' !== typeof wp.media.featuredImage.set) {
                return false;
            }

            var originalSet = wp.media.featuredImage.set;
            if (originalSet.pealipgWrapped) {
                return true;
            }

            var wrappedSet = function (attachmentId) {
                var result = originalSet.apply(this, arguments);
                if (parseInt(attachmentId, 10) > 0) {
                    syncAttachment(attachmentId);
                } else {
                    lastAttachmentId = 0;
                }
                return result;
            };

            wrappedSet.pealipgWrapped = true;
            wp.media.featuredImage.set = wrappedSet;
            return true;
        }

        wrapFeaturedImageSetter();
        window.setTimeout(wrapFeaturedImageSetter, 250);
        window.setTimeout(wrapFeaturedImageSetter, 1000);

        var featuredImageBox = document.getElementById('postimagediv');
        if (featuredImageBox && 'function' === typeof window.MutationObserver) {
            new MutationObserver(syncCurrentFeaturedImage).observe(featuredImageBox, {
                childList: true,
                subtree: true
            });
        }

        window.setTimeout(syncCurrentFeaturedImage, 600);
    }

    $(function () {
        initGalleryEditor();
        initAioseoFeaturedImageSync();
    });
}(jQuery));
