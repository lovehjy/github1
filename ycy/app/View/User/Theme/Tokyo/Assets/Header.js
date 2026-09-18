!function () {
    const noticeStorageKey = 'tokyo_notice_ack_v1';
    const noticeSuppressMs = 60 * 60 * 1000;
    const pjaxContainerSelector = '#pjax-container';
    const pjaxLinkSelector = '.tokyo-nav-link[href], .tokyo-commodity-anchor[href], .tokyo-commodity-action-link[href]';
    let lastPjaxUrl = '';

    function getNoticePopup() {
        return $('#tokyo-notice-popup');
    }

    function hashString(value) {
        const input = String(value || '');
        let hash = 0;

        for (let index = 0; index < input.length; index += 1) {
            hash = ((hash << 5) - hash) + input.charCodeAt(index);
            hash |= 0;
        }

        return String(hash);
    }

    function getNoticeSignature() {
        return hashString(getNoticePopup().html());
    }

    function shouldSuppressNotice() {
        const $noticePopup = getNoticePopup();

        if ($noticePopup.length === 0) {
            return true;
        }

        try {
            const stored = JSON.parse(localStorage.getItem(noticeStorageKey) || 'null');

            return Boolean(
                stored
                && stored.signature === getNoticeSignature()
                && Number(stored.expiresAt) > Date.now()
            );
        } catch (error) {
            return false;
        }
    }

    function rememberNoticeRead() {
        try {
            localStorage.setItem(noticeStorageKey, JSON.stringify({
                signature: getNoticeSignature(),
                expiresAt: Date.now() + noticeSuppressMs
            }));
        } catch (error) {
        }
    }

    function buildNoticeContent() {
        return `
            <div class="tokyo-notice-modal">
                <div class="tokyo-notice-modal-head">
                    <span class="tokyo-notice-kicker">Notice</span>
                    <h3 class="tokyo-notice-title">
                        <i class="fa-duotone fa-regular fa-megaphone"></i>
                        <span>${i18n('公告')}</span>
                    </h3>
                </div>
                <div class="tokyo-notice-popup-inner">
                    ${getNoticePopup().html()}
                </div>
                <div class="tokyo-notice-actions">
                    <button type="button" class="tokyo-button tokyo-button-light tokyo-notice-cancel">
                        <i class="fa-duotone fa-regular fa-xmark"></i>
                        <span>${i18n('取消')}</span>
                    </button>
                    <button type="button" class="tokyo-button tokyo-button-dark tokyo-notice-confirm">
                        <i class="fa-duotone fa-regular fa-check"></i>
                        <span>${i18n('我已阅读')}</span>
                    </button>
                </div>
                <p class="tokyo-notice-tip">${i18n('点击“我已阅读”后，1 小时内不再弹出。')}</p>
            </div>
        `;
    }

    function openNoticePopup(force = false) {
        if (typeof layer === 'undefined' || getNoticePopup().length === 0) {
            return;
        }

        if (!force && shouldSuppressNotice()) {
            return;
        }

        layer.open({
            type: 1,
            title: false,
            closeBtn: 0,
            shadeClose: true,
            area: window.innerWidth <= 767 ? ['calc(100% - 24px)', 'auto'] : ['760px', 'auto'],
            skin: 'tokyo-layer-popup',
            content: buildNoticeContent(),
            success: function (layero, index) {
                const $layer = $(layero);

                $layer.find('.tokyo-notice-confirm').on('click', function () {
                    rememberNoticeRead();
                    layer.close(index);
                });

                $layer.find('.tokyo-notice-cancel').on('click', function () {
                    layer.close(index);
                });
            }
        });
    }

    function isPlainLeftClick(event) {
        return !(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.which > 1);
    }

    function isPjaxEligible(link) {
        const href = String(link.getAttribute('href') || '').trim();

        if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
            return false;
        }

        if (link.hasAttribute('download') || link.getAttribute('target') === '_blank') {
            return false;
        }

        try {
            const url = new URL(href, window.location.href);

            if (url.origin !== window.location.origin) {
                return false;
            }

            return /^\/($|item\/|cat\/|user\/index\/query)/.test(url.pathname.replace(/^\//, '') ? `/${url.pathname.replace(/^\//, '')}` : url.pathname);
        } catch (error) {
            return false;
        }
    }

    function collapseMobileNav() {
        $('body').removeClass('tokyo-mobile-drawer-open');
        $('#tokyoNavMenu').removeClass('show');
        $('.tokyo-nav-toggle').addClass('collapsed').attr('aria-expanded', 'false');
    }

    function syncActiveNav(pathname) {
        const currentPath = String(pathname || window.location.pathname || '/');
        const isQueryPage = currentPath.indexOf('/user/index/query') === 0;

        $('.tokyo-nav-link[href]').removeClass('is-active').each(function () {
            const href = String($(this).attr('href') || '').trim();

            if (isQueryPage && href === '/user/index/query') {
                $(this).addClass('is-active');
                return;
            }

            if (!isQueryPage && href === '/') {
                $(this).addClass('is-active');
            }
        });
    }

    function cleanupSharedHandlers() {
        $(document).off('click', '.pay-list .pay');
    }

    function startPjaxLoading() {
        $(pjaxContainerSelector).addClass('is-pjax-loading');
    }

    function finishPjaxLoading() {
        $(pjaxContainerSelector).removeClass('is-pjax-loading');
    }

    function initPjax() {
        if (typeof $ === 'undefined' || typeof $.pjax !== 'function') {
            return;
        }

        $(document).off('click.tokyoPjax', pjaxLinkSelector).on('click.tokyoPjax', pjaxLinkSelector, function (event) {
            if (!isPlainLeftClick(event) || !isPjaxEligible(this)) {
                return;
            }

            $.pjax.click(event, {
                container: pjaxContainerSelector,
                fragment: pjaxContainerSelector,
                timeout: 10000,
                scrollTo: false
            });
        });

        $(document).off('pjax:start.tokyoPjax').on('pjax:start.tokyoPjax', function (event, xhr, options) {
            lastPjaxUrl = options && options.url ? options.url : '';
            cleanupSharedHandlers();
            collapseMobileNav();
            startPjaxLoading();
        });

        $(document).off('pjax:end.tokyoPjax').on('pjax:end.tokyoPjax', function () {
            finishPjaxLoading();
            syncActiveNav(window.location.pathname);

            if (typeof window.scrollTo === 'function') {
                window.scrollTo({top: 0, behavior: 'auto'});
            }
        });

        $(document).off('pjax:error.tokyoPjax').on('pjax:error.tokyoPjax', function (event, xhr, textStatus, errorThrown, options) {
            finishPjaxLoading();
            event.preventDefault();
            window.location.href = options && options.url
                ? options.url
                : (lastPjaxUrl || window.location.href);
        });
    }

    window.showTokyoNoticePopup = openNoticePopup;

    $(document).off('click.tokyoNotice', '.tokyo-notice-trigger').on('click.tokyoNotice', '.tokyo-notice-trigger', function (event) {
        event.preventDefault();
        this.blur();
        openNoticePopup(true);
    });

    syncActiveNav(window.location.pathname);
    initPjax();
}();
