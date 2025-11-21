<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\PasswordChangeRequest;

class UserSettingsController extends Controller
{
    /**
     * Display the user settings page.
     */
    public function index()
    {
        return view('settings.index');
    }

    /**
     * Update the user's password from settings page.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]+$/'],
        ], [
            'current_password.current_password' => __('auth.password.current_incorrect'),
            'password.regex' => __('auth.password.weak_password'),
            'password.min' => __('auth.password.validation.min_length'),
        ]);

        $user = auth()->user();
        $user->password = Hash::make($request->password);
        $user->last_password_change_at = now();
        $user->save();

        return redirect()->route('settings.index')->with('success', __('auth.password.change_success'));
    }

    /**
     * Update the user's YouTube API key.
     */
    public function updateApiKey(Request $request)
    {
        $request->validate([
            'youtube_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $user = auth()->user();

        // Only update if a new key is provided (not the masked version)
        if ($request->youtube_api_key && $request->youtube_api_key !== '••••••••••••••••') {
            $user->youtube_api_key = $request->youtube_api_key;
            $user->save();

            return redirect()->route('settings.index')->with('success', 'YouTube API 金鑰已成功儲存。');
        }

        return redirect()->route('settings.index')->with('success', 'API 金鑰未更改。');
    }

    /**
     * Remove the user's YouTube API key.
     */
    public function removeApiKey()
    {
        $user = auth()->user();
        $user->youtube_api_key = null;
        $user->save();

        return redirect()->route('settings.index')->with('success', 'YouTube API 金鑰已移除。');
    }
}
