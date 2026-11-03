// Small client for the store pages: every page talks to the JSON API under /api/v1.
// The JWT from login/register is kept in localStorage and sent as a Bearer token.
window.Store = (() => {
    const API = '/api/v1';
    const TOKEN_KEY = 'store_token';

    const getToken = () => localStorage.getItem(TOKEN_KEY);
    const setToken = (token) => localStorage.setItem(TOKEN_KEY, token);
    const clearToken = () => localStorage.removeItem(TOKEN_KEY);

    async function api(path, { method = 'GET', body } = {}) {
        const headers = { Accept: 'application/json', 'Accept-Language': 'ar' };
        if (body) headers['Content-Type'] = 'application/json';
        const token = getToken();
        if (token) headers.Authorization = 'Bearer ' + token;

        const response = await fetch(API + path, {
            method,
            headers,
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = response.status === 204 ? null : await response.json().catch(() => null);
        return { response, data };
    }

    function showAlert(message) {
        const alert = document.getElementById('form-alert');
        alert.textContent = message;
        alert.hidden = !message;
    }

    function showFieldErrors(form, errors = {}) {
        form.querySelectorAll('[data-error-for]').forEach((el) => {
            const field = el.dataset.errorFor;
            const messages = errors[field] || [];
            el.textContent = messages[0] || '';
            form.elements[field]?.classList.toggle('is-invalid', messages.length > 0);
        });
    }

    // Submits a form as JSON; on success stores the token and opens the account page.
    // `messages` maps an HTTP status to the text shown above the form.
    function bindForm(formId, path, messages = {}) {
        const form = document.getElementById(formId);
        const button = form.querySelector('button[type="submit"]');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            showAlert('');
            showFieldErrors(form);
            button.disabled = true;

            try {
                const body = Object.fromEntries(new FormData(form));
                const { response, data } = await api(path, { method: 'POST', body });

                if (response.ok) {
                    setToken(data.data.token.access_token);
                    window.location.href = '/account';
                    return;
                }
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

    function redirectIfLoggedIn() {
        if (getToken()) window.location.replace('/account');
    }

    async function loadAccount() {
        if (!getToken()) return window.location.replace('/login');

        const { response, data } = await api('/auth/me');
        if (!response.ok) {
            clearToken();
            return window.location.replace('/login');
        }

        const user = data.data;
        const roleLabels = { customer: 'زبون', admin: 'مسؤول' };
        const values = {
            name: user.name,
            email: user.email,
            role: roleLabels[user.role] || user.role,
            created_at: new Date(user.created_at).toLocaleDateString('ar-SY', { dateStyle: 'long' }),
        };
        document.querySelectorAll('[data-user]').forEach((el) => {
            el.textContent = values[el.dataset.user];
        });
        document.querySelector('[data-user="role"]').classList.toggle('badge--admin', user.role === 'admin');
    }

    async function logout() {
        await api('/auth/logout', { method: 'POST' }).catch(() => {});
        clearToken();
        window.location.replace('/login');
    }

    return { bindForm, redirectIfLoggedIn, loadAccount, logout };
})();
