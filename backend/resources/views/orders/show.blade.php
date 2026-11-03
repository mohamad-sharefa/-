@extends('layouts.app')

@section('title', 'تفاصيل الطلب')

@section('active', 'orders')

@section('content')
<main class="page-main page-main--narrow-top">
    <a href="{{ route('orders.index') }}" class="back-link">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
        العودة إلى الطلبات
    </a>

    <p class="notice notice--success" id="placed-notice" role="status" hidden>تم إنشاء طلبك بنجاح.</p>
    <p class="empty-state" id="order-error" hidden></p>

    <div class="order" id="order" hidden>
        <div class="page-head">
            <div class="order__heading">
                <h1 class="page-title" data-order="title"></h1>
                <span class="muted" data-order="meta"></span>
            </div>
            <span class="status-badge status-badge--lg" data-order="status"></span>
        </div>

        <ol class="steps" aria-label="مراحل الطلب" data-order="steps"></ol>

        <section class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">المنتج</th>
                        <th scope="col">سعر القطعة</th>
                        <th scope="col">الكمية</th>
                        <th scope="col">المجموع</th>
                    </tr>
                </thead>
                <tbody data-order="items"></tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="3">الإجمالي</th>
                        <td dir="ltr" class="table__money table__total" data-order="total"></td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <section class="panel status-panel" id="status-panel" aria-labelledby="status-panel-title" hidden>
            <div>
                <h2 class="summary__title" id="status-panel-title">تغيير حالة الطلب</h2>
                <p class="muted">يظهر هذا القسم للمسؤول فقط.</p>
            </div>
            <div class="alert" id="form-alert" role="alert" hidden></div>
            <form class="status-form" id="status-form">
                <label for="next-status" class="visually-hidden">الحالة الجديدة</label>
                <select id="next-status" name="status"></select>
                <button type="submit" class="btn btn--wide">تحديث الحالة</button>
            </form>
        </section>
    </div>
</main>
@endsection

@push('scripts')
<script>
    Store.loadOrder(@json($orderId));
</script>
@endpush
