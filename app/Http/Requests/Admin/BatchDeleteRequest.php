<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T056 [US4]: Batch Delete Form Request
 *
 * Validates batch delete requests with Traditional Chinese error messages
 * Enforces maximum 50 videos per batch operation
 */
class BatchDeleteRequest extends FormRequest
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
            'video_ids' => ['required', 'array', 'min:1', 'max:50'],
            'video_ids.*' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{11}$/'],
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
            'video_ids.required' => '請選擇要刪除的影片',
            'video_ids.array' => '影片列表格式無效',
            'video_ids.min' => '請選擇要刪除的影片',
            'video_ids.max' => '一次最多刪除50部影片',
            'video_ids.*.required' => '影片ID不能為空',
            'video_ids.*.string' => '影片ID格式無效',
            'video_ids.*.regex' => '影片ID格式無效',
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
