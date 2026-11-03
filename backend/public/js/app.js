// Small client for the store pages: every page talks to the JSON API under /api/v1.
// The JWT from login/register is kept in localStorage and sent as a Bearer token.
window.Store = (() => {
    const API = '/api/v1';
    const TOKEN_KEY = 'store_token';
    const USER_KEY = 'store_user';

    const getToken = () => localStorage.getItem(TOKEN_KEY);
    const getUser = () => JSON.parse(localStorage.getItem(USER_KEY) || 'null');

    function saveSession(token, user) {
        localStorage.setItem(TOKEN_KEY, token);
        localStorage.setItem(USER_KEY, JSON.stringify(user));
    }

    function clearSession() {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
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
        renderPagination(products.data.meta, categoryId);
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

    function renderPagination(meta, categoryId) {
        const nav = document.getElementById('pagination');
        if (meta.last_page <= 1) return nav.replaceChildren();

        const link = (label, page, { current = false, disabled = false } = {}) => {
            if (disabled) return el('span', { class: 'page-link is-disabled', 'aria-disabled': 'true', text: label });
            return el('a', {
                href: linkFor(categoryId, page),
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

    return { bindForm, redirectIfLoggedIn, loadAccount, logout, loadProducts, loadProduct, initProductForm };
})();
