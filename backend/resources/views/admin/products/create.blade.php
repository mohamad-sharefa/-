@extends('layouts.app')

@section('title', 'إضافة منتج')

@section('active', 'add-product')

@section('content')
<main class="page-main">
    <div class="page-head page-head--stacked">
        <h1 class="page-title">إضافة منتج جديد</h1>
        <p class="muted">هذه الصفحة متاحة للمسؤول فقط.</p>
    </div>

    <form class="form form-panel" id="product-form" novalidate>
        <div class="alert" id="form-alert" role="alert" hidden></div>

        <div class="field">
            <label for="name">اسم المنتج</label>
            <input id="name" name="name" type="text" required>
            <span class="field__error" data-error-for="name"></span>
        </div>

        <div class="form-row">
            <div class="field">
                <label for="category_id">التصنيف</label>
                <select id="category_id" name="category_id" required>
                    <option value="">اختر التصنيف</option>
                </select>
                <span class="field__error" data-error-for="category_id"></span>
            </div>
            <div class="field">
                <label for="price">السعر ($)</label>
                <input id="price" name="price" type="number" min="0" step="0.01" dir="ltr" placeholder="0.00" required>
                <span class="field__error" data-error-for="price"></span>
            </div>
            <div class="field">
                <label for="stock">الكمية في المخزون</label>
                <input id="stock" name="stock" type="number" min="0" step="1" dir="ltr" placeholder="0" required>
                <span class="field__error" data-error-for="stock"></span>
            </div>
        </div>

        <div class="field">
            <label for="description">الوصف</label>
            <textarea id="description" name="description" rows="4"></textarea>
            <span class="field__error" data-error-for="description"></span>
        </div>

        <div class="field">
            <span class="field__label">صورة المنتج</span>
            <label for="image" class="upload">
                <img class="upload__preview" id="image-preview" alt="معاينة الصورة المختارة" hidden>
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#0E7490" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m17 8-5-5-5 5"></path><path d="M12 3v12"></path></svg>
                <span class="upload__title" id="image-label">اختر صورة</span>
                <span class="field__hint">JPG أو PNG أو WEBP، بحجم أقصاه 2 ميغابايت</span>
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="visually-hidden" required>
            </label>
            <span class="field__error" data-error-for="image"></span>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--wide">حفظ المنتج</button>
            <a href="{{ route('products.index') }}" class="btn btn--ghost">إلغاء</a>
        </div>
    </form>
</main>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    Store.initProductForm();
</script>
@endpush
