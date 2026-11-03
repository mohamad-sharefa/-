@extends('layouts.app')

@section('title', 'حسابي')

@section('nav')
    <span class="nav__user">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
        <span data-user="name">…</span>
    </span>
    <button type="button" class="btn btn--outline" id="logout-button">تسجيل الخروج</button>
@endsection

@section('content')
<main class="page-main">
    <h1 class="page-title">حسابي</h1>

    <section class="panel" aria-label="بيانات الحساب">
        <dl class="details">
            <div class="details__row">
                <dt>الاسم</dt>
                <dd data-user="name">…</dd>
            </div>
            <div class="details__row">
                <dt>البريد الإلكتروني</dt>
                <dd dir="ltr" data-user="email">…</dd>
            </div>
            <div class="details__row">
                <dt>نوع الحساب</dt>
                <dd><span class="badge" data-user="role">…</span></dd>
            </div>
            <div class="details__row">
                <dt>تاريخ التسجيل</dt>
                <dd data-user="created_at">…</dd>
            </div>
        </dl>
    </section>
</main>
@endsection

@push('scripts')
<script>
    Store.loadAccount();
    document.getElementById('logout-button').addEventListener('click', Store.logout);
</script>
@endpush
