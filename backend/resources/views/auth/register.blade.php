@extends('layouts.app')

@section('title', 'إنشاء حساب')

@section('active', 'register')

@section('content')
<main class="auth-main">
    <div class="card card--wide">
        <div class="card__head">
            <h1 class="card__title">إنشاء حساب جديد</h1>
            <p class="card__subtitle">سجّل حسابك لتتمكن من الطلب ومتابعة طلباتك.</p>
        </div>

        <div class="alert" id="form-alert" role="alert" hidden></div>

        <form class="form" id="register-form" novalidate>
            <div class="field">
                <label for="name">الاسم الكامل</label>
                <input id="name" name="name" type="text" autocomplete="name" required>
                <span class="field__error" data-error-for="name"></span>
            </div>
            <div class="field">
                <label for="email">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" dir="ltr" placeholder="name@example.com" autocomplete="email" required>
                <span class="field__error" data-error-for="email"></span>
            </div>
            <div class="field">
                <label for="password">كلمة المرور</label>
                <input id="password" name="password" type="password" dir="ltr" autocomplete="new-password" aria-describedby="password-hint" required>
                <span class="field__hint" id="password-hint">8 أحرف على الأقل، وتحتوي على حروف وأرقام.</span>
                <span class="field__error" data-error-for="password"></span>
            </div>
            <div class="field">
                <label for="password_confirmation">تأكيد كلمة المرور</label>
                <input id="password_confirmation" name="password_confirmation" type="password" dir="ltr" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn">إنشاء الحساب</button>
        </form>

        <p class="card__footer">لديك حساب بالفعل؟ <a href="{{ route('login') }}">سجّل الدخول</a></p>
    </div>
</main>
@endsection

@push('scripts')
<script>
    Store.redirectIfLoggedIn();
    Store.bindForm('register-form', '/auth/register');
</script>
@endpush
