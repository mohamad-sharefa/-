<?php

namespace App\Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListProductsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only show products of this category
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            // How many products per page (default 12)
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
