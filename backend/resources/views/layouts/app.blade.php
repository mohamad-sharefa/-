@php($active = trim($__env->yieldContent('active')))
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-auth="guest">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') – المتجر الإلكتروني</title>
    <script>
        // Pick guest/user/admin before the page paints, so the menu doesn't flicker
        try {
            const user = JSON.parse(localStorage.getItem('store_user') || 'null');
            if (localStorage.getItem('store_token') && user) {
                document.documentElement.dataset.auth = user.role === 'admin' ? 'admin' : 'user';
            }
        } catch {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site-header">
    <div class="site-header__inner">
        <a href="{{ route('products.index') }}" class="brand">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0E7490" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
            المتجر الإلكتروني
        </a>
        <nav class="nav" aria-label="القائمة الرئيسية">
            <a href="{{ route('products.index') }}" @class(['nav__link', 'is-active' => $active === 'products'])>المنتجات</a>
            <a href="{{ route('admin.products.create') }}" data-show="admin" @class(['nav__link', 'is-active' => $active === 'add-product'])>إضافة منتج</a>
            <a href="{{ route('account') }}" data-show="user admin" @class(['nav__link', 'is-active' => $active === 'account'])>حسابي</a>
            <button type="button" class="btn btn--outline" data-show="user admin" data-action="logout">تسجيل الخروج</button>
            <a href="{{ route('login') }}" data-show="guest" @class(['nav__link', 'is-active' => $active === 'login'])>تسجيل الدخول</a>
            <a href="{{ route('register') }}" data-show="guest" @class(['nav__link', 'is-active' => $active === 'register'])>إنشاء حساب</a>
        </nav>
    </div>
</header>

@yield('content')

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
