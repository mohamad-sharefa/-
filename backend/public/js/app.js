// Small client for the store pages: every page talks to the JSON API under /api/v1.
// The JWT from login/register is kept in localStorage and sent as a Bearer token.
window.Store = (() => {
    const API = '/api/v1';
    const TOKEN_KEY = 'store_token';
    const USER_KEY = 'store_user';
    const CART_KEY = 'store_cart';

    const getToken = () => localStorage.getItem(TOKEN_KEY);
    const getUser = () => JSON.parse(localStorage.getItem(USER_KEY) || 'null');

    function saveSession(token, user) {
        localStorage.setItem(TOKEN_KEY, token);
        localStorage.setItem(USER_KEY, JSON.stringify(user));
    }

    function clearSession() {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
        localStorage.removeItem(CART_KEY);
    }

    async function api(path, { method = 'GET', body } = {}) {
        const headers = { Accept: 'application/json', 'Accept-Language': 'ar' };
        const isForm = body instanceof FormData;
        if (body && !isForm) headers['Content-Type'] = 'application/json';
        const token = getToken();
        if (token) headers.Authorization = 'Bearer ' + token;

        const response = await fetch(API + path, {
            method,
            headers,
            body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
        });
        const data = response.status === 204 ? null : await response.json().catch(() => null);
        return { response, data };
    }

    // Builds an element; text always goes in as textContent, so API data can't inject HTML
    function el(tag, attrs = {}, children = []) {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(attrs)) {
            if (key === 'text') node.textContent = value;
            else if (key === 'class') node.className = value;
            else node.setAttribute(key, value);
        }
        node.append(...children);
        return node;
    }

    const formatPrice = (price) => '$' + price;
    const stockText = (stock) => (stock > 0 ? 'متوفر' : 'نفد من المخزون');

    function imageBox(product, className) {
        const box = el('div', { class: className });
        if (product.image_url) {
            box.append(el('img', { src: product.image_url, alt: product.name, loading: 'lazy' }));
        } else {
            box.innerHTML = '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"></path></svg>';
        }
        return box;
    }

    /* ---------- Forms ---------- */

    function showAlert(message) {
        const alert = document.getElementById('form-alert');
        alert.textContent = message;
        alert.hidden = !message;
    }

    function showFieldErrors(form, errors = {}) {
        form.querySelectorAll('[data-error-for]').forEach((node) => {
            const field = node.dataset.errorFor;
            const messages = errors[field] || [];
            node.textContent = messages[0] || '';
            form.elements[field]?.classList.toggle('is-invalid', messages.length > 0);
        });
    }

    // Submits a form; `onSuccess` receives the response data.
    // `messages` maps an HTTP status to the text shown above the form.
    function handleSubmit(form, path, { asFormData = false, messages = {}, onSuccess }) {
        const button = form.querySelector('button[type="submit"]');

        // Clear a field's error as soon as the user starts fixing it
        form.addEventListener('input', (event) => {
            const name = event.target.name;
            const error = name && form.querySelector('[data-error-for="' + name + '"]');
            if (error) error.textContent = '';
            event.target.classList.remove('is-invalid');
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            showAlert('');
            showFieldErrors(form);
            button.disabled = true;

            try {
                const formData = new FormData(form);
                const body = asFormData ? formData : Object.fromEntries(formData);
                const { response, data } = await api(path, { method: 'POST', body });

                if (response.ok) return onSuccess(data);

                if (response.status === 422) {
                    showFieldErrors(form, data.errors);
                } else if (response.status === 429) {
                    const seconds = response.headers.get('Retry-After');
                    showAlert('محاولات كثيرة. حاول مرة أخرى بعد ' + seconds + ' ثانية.');
                } else {
                    showAlert(messages[response.status] || 'حدث خطأ غير متوقع. حاول مرة أخرى.');
                }
            } catch {
                showAlert('تعذّر الاتصال بالخادم. تحقق من اتصالك وحاول مرة أخرى.');
            } finally {
                button.disabled = false;
            }
        });
    }

    // Login and register: store the session, then go to the catalogue
    function bindForm(formId, path, messages = {}) {
        handleSubmit(document.getElementById(formId), path, {
            messages,
            onSuccess: (data) => {
                saveSession(data.data.token.access_token, data.data.user);
                window.location.href = '/products';
            },
        });
    }

    function redirectIfLoggedIn() {
        if (getToken()) window.location.replace('/products');
    }

    /* ---------- Account ---------- */

    async function loadAccount() {
        if (!getToken()) return window.location.replace('/login');

        const { response, data } = await api('/auth/me');
        if (!response.ok) {
            clearSession();
            return window.location.replace('/login');
        }

        const user = data.data;
        localStorage.setItem(USER_KEY, JSON.stringify(user));
        const roleLabels = { customer: 'زبون', admin: 'مسؤول' };
        const values = {
            name: user.name,
            email: user.email,
            role: roleLabels[user.role] || user.role,
            created_at: new Date(user.created_at).toLocaleDateString('ar-SY', { dateStyle: 'long' }),
        };
        document.querySelectorAll('[data-user]').forEach((node) => {
            node.textContent = values[node.dataset.user];
        });
        document.querySelector('[data-user="role"]').classList.toggle('badge--admin', user.role === 'admin');
    }

    async function logout() {
        await api('/auth/logout', { method: 'POST' }).catch(() => {});
        clearSession();
        window.location.replace('/login');
    }

    /* ---------- Products ---------- */

    // Category and page live in the URL (?category_id=2&page=3) so a filtered view can be shared
    async function loadProducts() {
        const params = new URLSearchParams(window.location.search);
        const categoryId = params.get('category_id') || '';
        const page = params.get('page') || '1';

        const [categories, products] = await Promise.all([
            api('/categories'),
            api('/products?' + new URLSearchParams({ ...(categoryId && { category_id: categoryId }), page, per_page: 12 })),
        ]);

        renderCategoryFilter(categories.data?.data || [], categoryId);

        if (!products.response.ok) {
            // e.g. an unknown category in the URL: fall back to all products
            return window.location.replace('/products');
        }
        renderProductGrid(products.data.data, products.data.meta.total);
        renderPagination(products.data.meta, (page) => linkFor(categoryId, page));
    }

    function linkFor(categoryId, page) {
        const query = new URLSearchParams({ ...(categoryId && { category_id: categoryId }), ...(page > 1 && { page }) });
        return '/products' + (query.toString() ? '?' + query : '');
    }

    function renderCategoryFilter(categories, activeId) {
        const container = document.getElementById('category-filter');
        const items = [{ id: '', name: 'الكل' }, ...categories];
        container.replaceChildren(...items.map((category) => {
            const isActive = String(category.id) === String(activeId);
            return el('a', {
                href: linkFor(category.id, 1),
                class: 'chip' + (isActive ? ' is-active' : ''),
                ...(isActive && { 'aria-current': 'true' }),
                text: category.name,
            });
        }));
    }

    function renderProductGrid(products, total) {
        document.getElementById('products-count').textContent = total + ' منتجات';
        document.getElementById('products-empty').hidden = products.length > 0;

        document.getElementById('product-grid').replaceChildren(...products.map((product) => el(
            'a',
            { href: '/products/' + product.id, class: 'product-card' },
            [
                imageBox(product, 'product-card__media'),
                el('div', { class: 'product-card__body' }, [
                    el('span', { class: 'product-card__category', text: product.category.name }),
                    el('span', { class: 'product-card__name', text: product.name }),
                    el('div', { class: 'product-card__footer' }, [
                        el('span', { class: 'product-card__price', dir: 'ltr', text: formatPrice(product.price) }),
                        el('span', { class: 'stock' + (product.stock > 0 ? '' : ' stock--out'), text: stockText(product.stock) }),
                    ]),
                ]),
            ],
        )));
    }

    // `makeLink(page)` returns the URL of a page
    function renderPagination(meta, makeLink) {
        const nav = document.getElementById('pagination');
        if (meta.last_page <= 1) return nav.replaceChildren();

        const link = (label, page, { current = false, disabled = false } = {}) => {
            if (disabled) return el('span', { class: 'page-link is-disabled', 'aria-disabled': 'true', text: label });
            return el('a', {
                href: makeLink(page),
                class: 'page-link' + (current ? ' is-current' : ''),
                ...(current && { 'aria-current': 'page' }),
                text: label,
            });
        };

        const pages = Array.from({ length: meta.last_page }, (_, i) => i + 1)
            .map((page) => link(String(page), page, { current: page === meta.current_page }));

        nav.replaceChildren(
            link('السابق', meta.current_page - 1, { disabled: meta.current_page === 1 }),
            ...pages,
            link('التالي', meta.current_page + 1, { disabled: meta.current_page === meta.last_page }),
        );
    }

    async function loadProduct(id) {
        const { response, data } = await api('/products/' + id);
        if (!response.ok) {
            document.getElementById('product-missing').hidden = false;
            return;
        }

        const product = data.data;
        document.title = product.name + ' – المتجر الإلكتروني';
        const field = (name) => document.querySelector('[data-product="' + name + '"]');

        field('image').replaceWith(imageBox(product, 'product-detail__media'));
        field('category').textContent = product.category.name;
        field('name').textContent = product.name;
        field('price').textContent = formatPrice(product.price);
        field('stock').textContent = product.stock > 0
            ? 'متوفر — ' + product.stock + ' قطعة في المخزون'
            : 'نفد من المخزون';
        field('stock').classList.toggle('stock--out', product.stock === 0);
        field('description').textContent = product.description || 'لا يوجد وصف.';
        document.getElementById('product-detail').hidden = false;

        if (product.stock > 0) {
            const form = document.getElementById('add-to-cart');
            form.elements.quantity.max = product.stock;
            form.hidden = false;
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                addToCart(product, Math.max(1, parseInt(form.elements.quantity.value, 10) || 1));
                document.getElementById('added-notice').hidden = false;
            });
        }
    }

    /* ---------- Cart (kept in this browser until the order is placed) ---------- */

    const getCart = () => JSON.parse(localStorage.getItem(CART_KEY) || '[]');

    function saveCart(items) {
        localStorage.setItem(CART_KEY, JSON.stringify(items));
        updateCartCount();
    }

    function updateCartCount() {
        const count = getCart().reduce((sum, item) => sum + item.quantity, 0);
        document.querySelectorAll('[data-cart-count]').forEach((badge) => {
            badge.textContent = count;
            badge.hidden = count === 0;
        });
    }

    function addToCart(product, quantity) {
        const items = getCart();
        const existing = items.find((item) => item.product_id === product.id);
        if (existing) existing.quantity += quantity;
        else items.push({ product_id: product.id, name: product.name, price: product.price, quantity });
        saveCart(items);
    }

    // Money as cents, so totals add up exactly
    const cents = (price) => Math.round(parseFloat(price) * 100);
    const fromCents = (value) => (value / 100).toFixed(2);

    function initCart() {
        const render = () => {
            const items = getCart();
            document.getElementById('cart-empty').hidden = items.length > 0;
            document.getElementById('cart-layout').hidden = items.length === 0;

            const change = (productId, quantity) => {
                saveCart(getCart()
                    .map((item) => (item.product_id === productId ? { ...item, quantity } : item))
                    .filter((item) => item.quantity > 0));
                render();
            };

            document.getElementById('cart-items').replaceChildren(...items.map((item, index) => {
                const decrease = el('button', { type: 'button', class: 'qty__btn', 'aria-label': 'إنقاص الكمية', text: '−' });
                const increase = el('button', { type: 'button', class: 'qty__btn', 'aria-label': 'زيادة الكمية', text: '+' });
                const remove = el('button', { type: 'button', class: 'icon-btn icon-btn--danger', 'aria-label': 'إزالة ' + item.name + ' من السلة' });
                remove.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>';
                decrease.addEventListener('click', () => change(item.product_id, Math.max(1, item.quantity - 1)));
                increase.addEventListener('click', () => change(item.product_id, item.quantity + 1));
                remove.addEventListener('click', () => change(item.product_id, 0));

                return el('div', { class: 'cart-row' }, [
                    el('div', { class: 'cart-row__info' }, [
                        el('a', { href: '/products/' + item.product_id, class: 'cart-row__name', text: item.name }),
                        el('span', { class: 'cart-row__price', dir: 'ltr', text: formatPrice(item.price) }),
                        el('span', { class: 'field__error', 'data-error-for': 'items.' + index + '.quantity' }),
                    ]),
                    el('div', { class: 'qty' }, [increase, el('span', { class: 'qty__value', text: item.quantity }), decrease]),
                    el('span', { class: 'cart-row__total', dir: 'ltr', text: formatPrice(fromCents(cents(item.price) * item.quantity)) }),
                    remove,
                ]);
            }));

            document.getElementById('cart-count').textContent = items.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-total').textContent = formatPrice(
                fromCents(items.reduce((sum, item) => sum + cents(item.price) * item.quantity, 0)),
            );
        };

        render();

        const button = document.getElementById('checkout-button');
        button.addEventListener('click', async () => {
            if (!getToken()) return window.location.href = '/login';

            showAlert('');
            button.disabled = true;
            try {
                const items = getCart().map(({ product_id, quantity }) => ({ product_id, quantity }));
                const { response, data } = await api('/orders', { method: 'POST', body: { items } });

                if (response.ok) {
                    saveCart([]);
                    return window.location.href = '/orders/' + data.data.id + '?placed=1';
                }
                if (response.status === 401) {
                    clearSession();
                    return window.location.href = '/login';
                }
                if (response.status === 422) {
                    // e.g. "only 1 left": show it under the product it belongs to
                    Object.entries(data.errors).forEach(([key, messages]) => {
                        const target = document.querySelector('[data-error-for="' + key + '"]');
                        if (target) target.textContent = messages[0];
                    });
                    showAlert('تعذّر إنشاء الطلب. راجع الملاحظات تحت المنتجات.');
                } else {
                    showAlert('حدث خطأ غير متوقع. حاول مرة أخرى.');
                }
            } catch {
                showAlert('تعذّر الاتصال بالخادم. تحقق من اتصالك وحاول مرة أخرى.');
            } finally {
                button.disabled = false;
            }
        });
    }

    /* ---------- Orders ---------- */

    const STATUS_LABELS = {
        pending: 'قيد الانتظار',
        confirmed: 'مؤكد',
        shipped: 'تم الشحن',
        delivered: 'تم التسليم',
        cancelled: 'ملغى',
    };
    const STATUS_FLOW = ['pending', 'confirmed', 'shipped', 'delivered'];

    const formatDate = (iso) => new Date(iso).toLocaleDateString('ar-SY', { dateStyle: 'long' });
    const statusBadge = (status, extraClass = '') => el('span', {
        class: 'status-badge status-badge--' + status + (extraClass ? ' ' + extraClass : ''),
        text: STATUS_LABELS[status] || status,
    });
    const piecesText = (count) => (count === 1 ? 'قطعة واحدة' : count === 2 ? 'قطعتان' : count + ' قطع');

    async function loadOrders() {
        if (!getToken()) return window.location.replace('/login');

        const page = new URLSearchParams(window.location.search).get('page') || '1';
        const { response, data } = await api('/orders?page=' + encodeURIComponent(page));
        if (response.status === 401) {
            clearSession();
            return window.location.replace('/login');
        }

        const isAdmin = getUser()?.role === 'admin';
        document.getElementById('orders-title').textContent = isAdmin ? 'الطلبات' : 'طلباتي';
        document.querySelectorAll('[data-admin-col]').forEach((cell) => { cell.hidden = !isAdmin; });

        const orders = data.data;
        document.getElementById('orders-empty').hidden = orders.length > 0;
        document.getElementById('orders-table').hidden = orders.length === 0;

        document.getElementById('orders-body').replaceChildren(...orders.map((order) => el('tr', {}, [
            el('td', { class: 'table__strong', text: '#' + order.id }),
            ...(isAdmin ? [el('td', { text: order.customer.name })] : []),
            el('td', { text: formatDate(order.created_at) }),
            el('td', { text: piecesText(order.quantity) }),
            el('td', { class: 'table__money table__strong', dir: 'ltr', text: formatPrice(order.total) }),
            el('td', {}, [statusBadge(order.status)]),
            el('td', {}, [el('a', { href: '/orders/' + order.id, class: 'table__link', text: 'عرض' })]),
        ])));

        renderPagination(data.meta, (p) => '/orders' + (p > 1 ? '?page=' + p : ''));
    }

    async function loadOrder(id) {
        if (!getToken()) return window.location.replace('/login');

        const { response, data } = await api('/orders/' + id);
        if (response.status === 401) {
            clearSession();
            return window.location.replace('/login');
        }
        if (!response.ok) {
            const error = document.getElementById('order-error');
            error.textContent = response.status === 403
                ? 'لا تملك صلاحية عرض هذا الطلب.'
                : 'هذا الطلب غير موجود.';
            error.hidden = false;
            return;
        }

        if (new URLSearchParams(window.location.search).has('placed')) {
            document.getElementById('placed-notice').hidden = false;
        }
        renderOrder(data.data);
    }

    function renderOrder(order) {
        const field = (name) => document.querySelector('[data-order="' + name + '"]');
        document.title = 'الطلب #' + order.id + ' – المتجر الإلكتروني';

        field('title').textContent = 'الطلب #' + order.id;
        field('meta').textContent = formatDate(order.created_at) + ' · ' + order.customer.name;
        field('status').className = 'status-badge status-badge--lg status-badge--' + order.status;
        field('status').textContent = STATUS_LABELS[order.status] || order.status;

        const reached = STATUS_FLOW.indexOf(order.status);
        field('steps').replaceChildren(...STATUS_FLOW.map((status, index) => {
            const done = order.status !== 'cancelled' && index <= reached;
            return el('li', { class: 'steps__item' + (done ? ' is-done' : '') }, [
                el('span', { class: 'steps__dot', text: String(index + 1) }),
                el('span', { class: 'steps__label', text: STATUS_LABELS[status] }),
            ]);
        }));
        field('steps').hidden = order.status === 'cancelled';

        field('items').replaceChildren(...order.items.map((item) => el('tr', {}, [
            el('td', { class: 'table__strong', text: item.product_name }),
            el('td', { class: 'table__money', dir: 'ltr', text: formatPrice(item.unit_price) }),
            el('td', { text: String(item.quantity) }),
            el('td', { class: 'table__money table__strong', dir: 'ltr', text: formatPrice(item.line_total) }),
        ])));
        field('total').textContent = formatPrice(order.total);

        document.getElementById('order').hidden = false;
        setupStatusPanel(order);
    }

    // Admin only: move the order to one of its allowed next statuses
    function setupStatusPanel(order) {
        const panel = document.getElementById('status-panel');
        const isAdmin = getUser()?.role === 'admin';
        panel.hidden = !isAdmin || order.next_statuses.length === 0;
        if (panel.hidden) return;

        const form = document.getElementById('status-form');
        const select = document.getElementById('next-status');
        select.replaceChildren(...order.next_statuses.map((status) => el('option', { value: status, text: STATUS_LABELS[status] })));

        form.onsubmit = async (event) => {
            event.preventDefault();
            showAlert('');
            const button = form.querySelector('button');
            button.disabled = true;
            try {
                const { response, data } = await api('/orders/' + order.id + '/status', {
                    method: 'PATCH',
                    body: { status: select.value },
                });
                if (response.ok) return renderOrder(data.data);
                showAlert(data?.errors?.status?.[0]
                    || (response.status === 403 ? 'هذه العملية متاحة للمسؤول فقط.' : 'حدث خطأ غير متوقع. حاول مرة أخرى.'));
            } catch {
                showAlert('تعذّر الاتصال بالخادم. تحقق من اتصالك وحاول مرة أخرى.');
            } finally {
                button.disabled = false;
            }
        };
    }

    // Admin: add a product. Non-admins are sent back; the API enforces the same rule.
    async function initProductForm() {
        const user = getUser();
        if (!getToken() || !user) return window.location.replace('/login');
        if (user.role !== 'admin') return window.location.replace('/products');

        const { data } = await api('/categories');
        const select = document.getElementById('category_id');
        select.append(...(data?.data || []).map((category) => el('option', { value: category.id, text: category.name })));

        const input = document.getElementById('image');
        const preview = document.getElementById('image-preview');
        input.addEventListener('change', () => {
            const file = input.files[0];
            preview.hidden = !file;
            if (file) {
                preview.src = URL.createObjectURL(file);
                document.getElementById('image-label').textContent = file.name;
            }
        });

        handleSubmit(document.getElementById('product-form'), '/products', {
            asFormData: true,
            messages: {
                401: 'انتهت الجلسة. سجّل الدخول من جديد.',
                403: 'هذه العملية متاحة للمسؤول فقط.',
            },
            onSuccess: (result) => {
                window.location.href = '/products/' + result.data.id;
            },
        });
    }

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-action="logout"]')) logout();
    });
    updateCartCount();

    return {
        bindForm, redirectIfLoggedIn, loadAccount, logout,
        loadProducts, loadProduct, initProductForm,
        initCart, loadOrders, loadOrder,
    };
})();
