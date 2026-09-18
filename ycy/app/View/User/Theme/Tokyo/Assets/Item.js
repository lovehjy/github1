!function () {
    const $coverCard = $('.tokyo-item-cover-card');
    const $coverTrigger = $('.tokyo-item-cover-trigger');
    const $coverImage = $('.item-cover');
    const $formWrap = $('.tokyo-item-form-wrap');

    if ($coverCard.length === 0 || $coverImage.length === 0 || $formWrap.length === 0) {
        return;
    }

    function isDesktopViewport() {
        return window.matchMedia('(min-width: 992px)').matches;
    }

    function syncCoverHeight() {
        if (!isDesktopViewport()) {
            $coverCard.css('--tokyo-item-cover-height', '');
            return;
        }

        const formHeight = Math.ceil($formWrap.outerHeight() || 0);

        if (formHeight <= 0) {
            return;
        }

        $coverCard.css('--tokyo-item-cover-height', `${Math.max(360, formHeight)}px`);
    }

    function scheduleSync() {
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(syncCoverHeight);
            return;
        }

        syncCoverHeight();
    }

    function openImagePreview() {
        const imageSrc = String($coverTrigger.data('image') || $coverImage.attr('src') || '').trim();
        const imageTitle = String($coverTrigger.data('title') || $coverImage.attr('alt') || i18n('商品图片')).trim();

        if (!imageSrc || typeof layer === 'undefined') {
            return;
        }

        const safeTitle = $('<div>').text(imageTitle).html();
        const safeSrc = $('<div>').text(imageSrc).html();

        layer.open({
            type: 1,
            title: false,
            closeBtn: 1,
            shadeClose: true,
            area: window.innerWidth <= 767 ? ['calc(100% - 24px)', 'auto'] : ['auto', 'auto'],
            shade: [0.42, 'rgba(18, 22, 28, 0.92)'],
            skin: 'tokyo-image-layer',
            content: `
                <div class="tokyo-image-preview">
                    <img src="${safeSrc}" alt="${safeTitle}">
                </div>
            `
        });
    }

    function bindEvents() {
        $coverTrigger.off('click.tokyoItem').on('click.tokyoItem', function (event) {
            event.preventDefault();
            openImagePreview();
        });

        $coverTrigger.off('keydown.tokyoItem').on('keydown.tokyoItem', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            openImagePreview();
        });

        $(window).off('resize.tokyoItem orientationchange.tokyoItem').on('resize.tokyoItem orientationchange.tokyoItem', scheduleSync);
        $coverImage.off('load.tokyoItem').on('load.tokyoItem', scheduleSync);
    }

    function observeLayout() {
        if (window.__tokyoItemResizeObserver && typeof window.__tokyoItemResizeObserver.disconnect === 'function') {
            window.__tokyoItemResizeObserver.disconnect();
        }

        if (typeof ResizeObserver !== 'function') {
            window.setTimeout(scheduleSync, 160);
            window.setTimeout(scheduleSync, 420);
            window.setTimeout(scheduleSync, 900);
            return;
        }

        const observer = new ResizeObserver(() => {
            scheduleSync();
        });

        observer.observe($formWrap.get(0));
        window.__tokyoItemResizeObserver = observer;
    }

    bindEvents();
    observeLayout();
    scheduleSync();
    window.setTimeout(scheduleSync, 220);
    window.setTimeout(scheduleSync, 640);
}();
