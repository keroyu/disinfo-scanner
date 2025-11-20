<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => '設定驗證失敗',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'youtube_api_key' => ['nullable', 'string', 'max:255'],
            'verification_method' => ['sometimes', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom validation messages in Traditional Chinese.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.string' => '名稱必須是文字格式',
            'name.max' => '名稱長度不得超過 255 個字元',
            'youtube_api_key.string' => 'YouTube API 金鑰必須是文字格式',
            'youtube_api_key.max' => 'YouTube API 金鑰長度不得超過 255 個字元',
            'verification_method.string' => '驗證方式必須是文字格式',
            'verification_method.max' => '驗證方式長度不得超過 50 個字元',
        ];
    }
}
