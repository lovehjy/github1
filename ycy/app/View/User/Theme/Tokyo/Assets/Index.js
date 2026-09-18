//站点货币符号：读 Helper 注入的 CURRENCY（部分旧主题不加载 ready.js，探测兜底 ¥）
function acgCurrencySymbol() {
    var currency = typeof getVar === 'function' ? getVar('CURRENCY') : null;
    return (currency && currency.symbol) || '¥';
}

!function () {
    const $page = $('.tokyo-page');

    if ($page.length === 0) {
        return;
    }

    const $tree = $('#tokyo-category-tree');
    const $itemList = $('.item-list');
    const $currentName = $('.tokyo-current-name');
    const $noticePopup = $('#tokyo-notice-popup');
    const $tableWrap = $('.tokyo-table-wrap');
    const $tableLoadingText = $('.tokyo-table-loading-text');
    const $mobileFilterTrigger = $('.tokyo-mobile-filter-trigger');
    const $mobileSidebarClose = $('.tokyo-mobile-sidebar-close');
    const $mobileSidebarBackdrop = $('.tokyo-mobile-sidebar-backdrop');
    const defaultCategoryId = String($page.data('defaultCategory') || '').trim();
    const isUserLoggedIn = Number($page.data('userLoggedIn')) === 1;
    const noticeStorageKey = 'tokyo_notice_ack_v1';
    const noticeSuppressMs = 60 * 60 * 1000;

    let categoryTree = [];
    let activeCategoryId = '';
    let isSearchMode = false;
    let hasLoadedCommodity = false;
    let commodityRequestToken = 0;
    let categoryMap = new Map();
    let parentMap = new Map();
    const expandedIds = new Set();

    function escapeHtml(value) {
        const safe = value === null || value === undefined ? '' : value;
        return String(safe).replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[char]));
    }

    function rawHtml(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function formatPrice(value) {
        if (typeof format !== 'undefined' && typeof format.amountRemoveTrailingZeros === 'function') {
            return format.amountRemoveTrailingZeros(value);
        }
        const numeric = Number(value);
        if (Number.isNaN(numeric)) {
            return value;
        }
        return numeric % 1 === 0 ? numeric.toString() : numeric.toFixed(2);
    }

    function toPriceNumber(value) {
        const numeric = Number(value);
        return Number.isFinite(numeric) ? numeric : null;
    }

    function isMobileViewport() {
        return window.matchMedia('(max-width: 767px)').matches;
    }

    function request(url, data = {}) {
        return new Promise((resolve, reject) => {
            const search = new URLSearchParams();

            Object.entries(data).forEach(([key, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    search.append(key, value);
                }
            });

            $.get({
                url: `${url}${search.toString() ? `?${search.toString()}` : ''}`,
                success: res => {
                    if (res.code !== 200) {
                        reject(res);
                        return;
                    }
                    resolve(res.data);
                },
                error: reject
            });
        });
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
        return hashString($noticePopup.html());
    }

    function shouldSuppressNotice() {
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
                    ${$noticePopup.html()}
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

    function setTableMessage(message) {
        hasLoadedCommodity = false;
        $itemList.html(`
            <tr class="tokyo-empty-row">
                <td colspan="${_show_sold == 1 ? 5 : 4}">${escapeHtml(message)}</td> 
            </tr>
        `);
    }

    function setCatalogLoading(loading, text = i18n('正在加载商品列表...')) {
        if ($tableWrap.length === 0) {
            return;
        }

        if (loading) {
            const stableHeight = $tableWrap.outerHeight();

            if (stableHeight > 0) {
                $tableWrap.css('--tokyo-table-stable-height', `${stableHeight}px`);
            }

            $tableLoadingText.text(text);
            $tableWrap.addClass('is-loading');
            return;
        }

        $tableWrap.removeClass('is-loading');
        $tableWrap.css('--tokyo-table-stable-height', '');
    }

    function getErrorMessage(error, fallback) {
        if (error && error.msg) {
            return error.msg;
        }

        return fallback;
    }

    function walkCategories(nodes, trail = [], parentId = null) {
        nodes.forEach(node => {
            const id = String(node.id);
            const path = trail.concat(node.name);
            const mapped = {
                ...node,
                id,
                __path: path
            };

            categoryMap.set(id, mapped);
            parentMap.set(id, parentId);

            if (Array.isArray(node.children) && node.children.length > 0) {
                walkCategories(node.children, path, id);
            }
        });
    }

    function ensureExpandedParents(id) {
        let current = parentMap.get(String(id));

        while (current) {
            expandedIds.add(String(current));
            current = parentMap.get(String(current));
        }
    }

    function buildTree(nodes, depth = 0) {
        return nodes.map(node => {
            const id = String(node.id);
            const hasChildren = Array.isArray(node.children) && node.children.length > 0;
            const isExpanded = expandedIds.has(id);
            const isActive = !isSearchMode && activeCategoryId === id && !hasChildren;
            const icon = escapeHtml(node.icon || '/favicon.ico');
            const count = Number(node.commodity_count || 0);

            return `
                <div class="tokyo-category-node" data-node-id="${escapeHtml(id)}">
                    <div class="tokyo-category-row" style="--tokyo-depth:${depth};">
                        <button type="button" class="tokyo-category-link ${isActive ? 'is-active' : ''}" data-id="${escapeHtml(id)}" data-has-children="${hasChildren ? 1 : 0}">
                            <span class="tokyo-category-icon" style="background-image:url('${icon}')"></span>
                            <span class="tokyo-category-copy">
                                <span class="tokyo-category-name">${rawHtml(node.name)}</span>
                                <span class="tokyo-category-side">
                                    ${hasChildren ? `
                                        <span class="tokyo-category-toggle ${isExpanded ? 'is-expanded' : ''}" data-id="${escapeHtml(id)}" aria-hidden="true">
                                            <i class="fa-duotone fa-regular fa-angle-right"></i>
                                        </span>
                                    ` : `
                                        <span class="tokyo-category-count">${count}</span>
                                    `}
                                </span>
                            </span>
                        </button>
                    </div>
                    ${hasChildren && isExpanded ? buildTree(node.children, depth + 1) : ''}
                </div>
            `;
        }).join('');
    }

    function renderTree() {
        const treeScrollTop = $tree.scrollTop();

        if (categoryTree.length === 0) {
            $tree.html('<div class="tokyo-tree-feedback">' + i18n('当前没有可展示的分类。') + '</div>');
            return;
        }

        $tree.html(buildTree(categoryTree));
        $tree.scrollTop(treeScrollTop);
    }

    function mergeCommodityLists(lists) {
        const map = new Map();

        lists.forEach(list => {
            list.forEach(item => {
                map.set(String(item.id), item);
            });
        });

        return Array.from(map.values());
    }

    function renderCommodityList(items) {
        if (!Array.isArray(items) || items.length === 0) {
            setTableMessage(i18n('当前分类下没有商品。'));
            return;
        }

        const html = items.map(item => {
            const stockState = item.stock_state !== undefined && item.stock_state !== null ? item.stock_state : item.stock;
            const soldOut = Number(stockState) <= 0 || String(item.stock) === '0' || String(item.stock) === i18n('已售罄');
            const href = soldOut ? 'javascript:void(0);' : `/item/${item.id}`;
            const priceValue = toPriceNumber(item.price);
            const userPriceValue = toPriceNumber(item.user_price);
            const showLoginPrice = !isUserLoggedIn
                && priceValue !== null
                && userPriceValue !== null
                && userPriceValue < priceValue;
            const mobileMetaTags = `
                <span class="tokyo-pill tokyo-pill-mobile-meta tokyo-pill-mobile-price tokyo-pill-mobile-only">${acgCurrencySymbol()}${escapeHtml(formatPrice(item.price))}</span>
                 ${showLoginPrice ? `<span class="tokyo-pill tokyo-pill-mobile-member tokyo-pill-mobile-only">${i18n('登录后 {price}').replace('{price}', acgCurrencySymbol() + escapeHtml(formatPrice(item.user_price)))}</span>` : ''}
                <span class="tokyo-pill tokyo-pill-mobile-meta tokyo-pill-mobile-only">${i18n('库存')} ${escapeHtml(item.stock)}</span>
               ${_show_sold == 1 ? `<span class="tokyo-pill tokyo-pill-mobile-meta tokyo-pill-mobile-only">${i18n('销量')} ${escapeHtml(item.order_sold)}</span>` : ``} 
            `;
            const action = soldOut
                ? '<span class="tokyo-commodity-action-disabled"><i class="fa-duotone fa-regular fa-ban"></i><span>' + i18n('售罄') + '</span></span>'
                : `<a class="tokyo-commodity-action-link" href="${href}"><i class="fa-duotone fa-regular fa-bag-shopping"></i><span>${i18n('购买')}</span></a>`;

            return `
                <tr class="tokyo-commodity-row ${soldOut ? 'is-soldout' : ''}">
                    <td class="tokyo-commodity-main">
                        <a class="tokyo-commodity-anchor" href="${href}">
                            <span class="tokyo-commodity-thumb" style="background-image:url('${escapeHtml(item.cover || '/favicon.ico')}')"></span>
                            <span class="tokyo-commodity-copy">
                                <span class="tokyo-commodity-name">${rawHtml(item.name)}</span>
                                <span class="tokyo-commodity-tags">
                                    ${renderCommodityTags(item)}
                                    ${mobileMetaTags}
                                    <span class="tokyo-pill tokyo-pill-success">${item.delivery_way === 0 ? i18n('自动发货') : i18n('在线发货')}</span>
                                    ${Number(item.recommend) === 1 ? '<span class="tokyo-pill tokyo-pill-primary">' + i18n('推荐') + '</span>' : ''}
                                </span>
                            </span>
                        </a>
                    </td>
                    <td class="tokyo-commodity-price" data-label="${i18n('价格')}">
                        <span class="tokyo-commodity-price-stack">
                            <span class="tokyo-commodity-price-main">${acgCurrencySymbol()}${escapeHtml(formatPrice(item.price))}</span>
                            ${showLoginPrice ? `<span class="tokyo-commodity-price-note">${i18n('登录后 {price}').replace('{price}', acgCurrencySymbol() + escapeHtml(formatPrice(item.user_price)))}</span>` : ''}
                        </span>
                    </td>
                    <td class="tokyo-commodity-muted" data-label="${i18n('库存')}">${escapeHtml(item.stock)}</td>
                    ${_show_sold == 1 ? `<td class="tokyo-commodity-muted" data-label="${i18n('销量')}">${escapeHtml(item.order_sold)}</td>` : ``} 
                    <td class="tokyo-commodity-action" data-label="${i18n('操作')}">${action}</td>
                </tr>
            `;
        }).join('');

        hasLoadedCommodity = true;
        $itemList.html(html);
    }

    //商品标签（#807）：管理员在后台配置的彩色标签，排在系统徽章之前
    function renderCommodityTags(item) {
        const tags = Array.isArray(item && item.tags) ? item.tags : [];
        if (!tags.length) return '';
        return tags.map(t => {
            const text = String((t && t.text) || '').trim();
            if (!text) return '';
            const color = String((t && t.color) || 'red');
            return `<span class="tokyo-tag tokyo-tag--${escapeHtml(color)}">${escapeHtml(text)}</span>`;
        }).join('');
    }

    function updateCatalogHeading(title) {
        $currentName.html(title);
    }

    function pushCategoryHistory(id) {
        if (!id) {
            return;
        }

        history.pushState(null, '', `/cat/${id}`);
    }

    function loadCategory(id, pushHistory = false) {
        const node = categoryMap.get(String(id));

        if (!node) {
            return;
        }

        const requestToken = ++commodityRequestToken;
        const shouldKeepCurrentList = hasLoadedCommodity;

        activeCategoryId = String(id);
        isSearchMode = false;

        renderTree();
        updateCatalogHeading(node.name);
        setCatalogLoading(true, i18n('正在加载商品列表...'));

        if (!shouldKeepCurrentList) {
            setTableMessage(i18n('正在加载商品列表...'));
        }

        request('/user/api/index/commodity', {categoryId: activeCategoryId})
            .then(result => {
                if (requestToken !== commodityRequestToken) {
                    return;
                }

                renderCommodityList(result);
                updateCatalogHeading(node.name);
                setCatalogLoading(false);

                if (pushHistory) {
                    pushCategoryHistory(activeCategoryId);
                }
            })
            .catch(error => {
                if (requestToken !== commodityRequestToken) {
                    return;
                }

                const msg = getErrorMessage(error, i18n('商品加载失败，请稍后重试。'));
                if (!shouldKeepCurrentList) {
                    setTableMessage(msg);
                }
                updateCatalogHeading(node.name);
                setCatalogLoading(false);
                if (typeof message !== 'undefined') {
                    message.error(msg);
                }
            });
    }

    function toggleCategory(id) {
        if (expandedIds.has(id)) {
            expandedIds.delete(id);
        } else {
            expandedIds.add(id);
        }

        renderTree();
    }

    function getFirstLeafId(nodes) {
        for (const node of nodes) {
            const hasChildren = Array.isArray(node.children) && node.children.length > 0;

            if (!hasChildren) {
                return String(node.id);
            }

            const childLeafId = getFirstLeafId(node.children);
            if (childLeafId) {
                return childLeafId;
            }
        }

        return '';
    }

    function searchCommodity(keywords) {
        const value = String(keywords || '').trim();

        if (value === '') {
            if (typeof message !== 'undefined') {
                message.error(i18n('请输入要搜索的商品关键词'));
            }
            return;
        }

        const requestToken = ++commodityRequestToken;
        const shouldKeepCurrentList = hasLoadedCommodity;

        isSearchMode = true;
        renderTree();
        updateCatalogHeading(i18n('搜索结果'));
        setCatalogLoading(true, i18n('正在搜索商品...'));

        if (!shouldKeepCurrentList) {
            setTableMessage(i18n('正在搜索商品...'));
        }

        request('/user/api/index/commodity', {keywords: value})
            .then(result => {
                if (requestToken !== commodityRequestToken) {
                    return;
                }

                renderCommodityList(result);
                updateCatalogHeading(i18n('搜索结果'));
                setCatalogLoading(false);
                history.pushState(null, '', '/');
            })
            .catch(error => {
                if (requestToken !== commodityRequestToken) {
                    return;
                }

                const msg = getErrorMessage(error, i18n('搜索失败，请稍后再试。'));
                if (!shouldKeepCurrentList) {
                    setTableMessage(msg);
                }
                updateCatalogHeading(i18n('搜索结果'));
                setCatalogLoading(false);
                if (typeof message !== 'undefined') {
                    message.error(msg);
                }
            });
    }

    function getFirstCategoryId(nodes) {
        if (!Array.isArray(nodes) || nodes.length === 0) {
            return '';
        }

        return String(nodes[0].id);
    }

    function bindEvents() {
        $mobileFilterTrigger.off('click.tokyoIndex').on('click.tokyoIndex', function () {
            $('body').addClass('tokyo-mobile-drawer-open');
        });

        $mobileSidebarClose.off('click.tokyoIndex').on('click.tokyoIndex', function () {
            $('body').removeClass('tokyo-mobile-drawer-open');
        });

        $mobileSidebarBackdrop.off('click.tokyoIndex').on('click.tokyoIndex', function () {
            $('body').removeClass('tokyo-mobile-drawer-open');
        });

        $(window).off('resize.tokyoIndex').on('resize.tokyoIndex', function () {
            if (!isMobileViewport()) {
                $('body').removeClass('tokyo-mobile-drawer-open');
            }
        });

        $tree.off('click.tokyoIndex', '.tokyo-category-toggle').on('click.tokyoIndex', '.tokyo-category-toggle', function (event) {
            event.preventDefault();
            event.stopPropagation();
            this.blur();
            const id = String($(this).data('id'));
            toggleCategory(id);
        });

        $tree.off('click.tokyoIndex', '.tokyo-category-link').on('click.tokyoIndex', '.tokyo-category-link', function (event) {
            event.preventDefault();
            this.blur();
            const id = String($(this).data('id'));
            const hasChildren = Number($(this).data('hasChildren')) === 1;

            if (hasChildren) {
                toggleCategory(id);
                return;
            }

            loadCategory(id, true);

            if (isMobileViewport()) {
                $('body').removeClass('tokyo-mobile-drawer-open');
            }
        });

        $(document).off('click.tokyoIndex', '.item-search-trigger').on('click.tokyoIndex', '.item-search-trigger', function () {
            const keywords = $(this).siblings('.tokyo-searchbox').find('.item-search-input').val();
            searchCommodity(keywords);
        });

        $(document).off('keypress.tokyoIndex', '.item-search-input').on('keypress.tokyoIndex', '.item-search-input', function (event) {
            if (event.which === 13) {
                event.preventDefault();
                searchCommodity($(this).val());
            }
        });
    }

    function showNoticePopup() {
        if ($noticePopup.length === 0 || typeof layer === 'undefined' || shouldSuppressNotice()) {
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

    function initExpandedState(nodes) {
        expandedIds.clear();
    }

    function init() {
        bindEvents();
        setTableMessage(i18n('正在加载分类...'));
        showNoticePopup();

        request('/user/api/index/data')
            .then(data => {
                categoryTree = Array.isArray(data) ? data : [];
                categoryMap = new Map();
                parentMap = new Map();
                initExpandedState(categoryTree);
                walkCategories(categoryTree);

                const firstLeafId = getFirstLeafId(categoryTree);
                let initialCategoryId = defaultCategoryId && categoryMap.has(defaultCategoryId)
                    ? defaultCategoryId
                    : firstLeafId;

                const initialNode = categoryMap.get(String(initialCategoryId));
                if (initialNode && Array.isArray(initialNode.children) && initialNode.children.length > 0) {
                    initialCategoryId = getFirstLeafId(initialNode.children);
                }

                if (!initialCategoryId) {
                    renderTree();
                    setTableMessage(i18n('当前没有可展示的商品分类。'));
                    updateCatalogHeading(i18n('商品目录'));
                    return;
                }

                ensureExpandedParents(initialCategoryId);
                renderTree();
                loadCategory(initialCategoryId, false);
            })
            .catch(error => {
                $tree.html('<div class="tokyo-tree-feedback">' + i18n('分类加载失败，请刷新页面后重试。') + '</div>');
                setTableMessage(getErrorMessage(error, i18n('分类加载失败，请稍后重试。')));
                updateCatalogHeading(i18n('商品目录'));
            });
    }

    init();
}();
