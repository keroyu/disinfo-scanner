<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException|ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        // For web requests, use default Laravel behavior (redirect back with errors)
        if (!$this->expectsJson()) {
            throw (new ValidationException($validator))
                ->errorBag($this->errorBag)
                ->redirectTo($this->getRedirectUrl());
        }

        // For API requests, return JSON
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => '驗證失敗',
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
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
            'name.required' => '暱稱為必填欄位',
            'name.max' => '暱稱長度不得超過 255 個字元',
            'email.required' => '電子郵件為必填欄位',
            'email.email' => '請輸入有效的電子郵件格式',
            'email.unique' => '此電子郵件已被註冊',
            'email.max' => '電子郵件長度不得超過 255 個字元',
        ];
    }
}
