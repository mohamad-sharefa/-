@extends('layouts.app')

@section('title', 'تسجيل الدخول')

@section('active', 'login')

@section('content')
<main class="auth-main">
    <div class="card">
        <div class="card__head">
            <h1 class="card__title">تسجيل الدخول</h1>
            <p class="card__subtitle">أدخل بريدك الإلكتروني وكلمة المرور للمتابعة.</p>
        </div>

        <div class="alert" id="form-alert" role="alert" hidden></div>

        <form class="form" id="login-form" novalidate>
            <div class="field">
                <label for="email">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" dir="ltr" placeholder="name@example.com" autocomplete="email" required>
                <span class="field__error" data-error-for="email"></span>
            </div>
            <div class="field">
                <label for="password">كلمة المرور</label>
                <input id="password" name="password" type="password" dir="ltr" autocomplete="current-password" required>
                <span class="field__error" data-error-for="password"></span>
            </div>
            <button type="submit" class="btn">دخول</button>
        </form>

        <p class="card__footer">ليس لديك حساب؟ <a href="{{ route('register') }}">أنشئ حساباً جديداً</a></p>
    </div>
</main>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    Store.redirectIfLoggedIn();
    Store.bindForm('login-form', '/auth/login', {
        401: 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
    });
</script>
@endpush
