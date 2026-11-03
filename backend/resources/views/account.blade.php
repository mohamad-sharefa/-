@extends('layouts.app')

@section('title', 'حسابي')

@section('active', 'account')

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
<script nonce="{{ Vite::cspNonce() }}">
    Store.loadAccount();
</script>
@endpush
