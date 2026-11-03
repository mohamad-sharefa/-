@extends('layouts.app')

@section('title', 'الإشعارات')

@section('active', 'notifications')

@section('content')
<main class="page-main">
    <div class="page-head">
        <h1 class="page-title">الإشعارات</h1>
        <button type="button" class="btn btn--outline" id="read-all-button" hidden>تحديد الكل كمقروء</button>
    </div>

    <p class="empty-state" id="notifications-empty" hidden>لا توجد إشعارات بعد.</p>
    <ul class="notification-list" id="notification-list" hidden></ul>

    <nav class="pagination" aria-label="الصفحات" id="pagination"></nav>
</main>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    Store.loadNotifications();
</script>
@endpush
