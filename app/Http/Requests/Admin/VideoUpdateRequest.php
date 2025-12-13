<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T031 [US2]: Video Update Form Request
 *
 * Validates video update requests with Traditional Chinese error messages
 */
class VideoUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware (check.admin)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'published_at' => ['required', 'date'],
        ];
    }

    /**
     * Get custom error messages for validation rules (zh-TW)
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => '標題不能為空',
            'title.string' => '標題必須是文字',
            'title.max' => '標題長度不能超過255個字元',
            'published_at.required' => '發布日期不能為空',
            'published_at.date' => '日期格式無效',
        ];
    }

    /**
     * Customize the response for failed validation
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json([
            'message' => '驗證失敗',
            'errors' => $validator->errors(),
        ], 422));
    }
}
