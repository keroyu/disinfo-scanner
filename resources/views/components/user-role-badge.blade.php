@props(['user'])

@php
    $role = $user->roles->first();
    $badgeClasses = [
        'visitor' => 'bg-gray-100 text-gray-700 border-gray-300',
        'regular_member' => 'bg-blue-100 text-blue-700 border-blue-300',
        'premium_member' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
        'website_editor' => 'bg-purple-100 text-purple-700 border-purple-300',
        'administrator' => 'bg-red-100 text-red-700 border-red-300',
    ];

    $displayNames = [
        'visitor' => '訪客',
        'regular_member' => '一般會員',
        'premium_member' => '高級會員',
        'website_editor' => '網站編輯',
        'administrator' => '管理員',
    ];

    $roleName = $role->name ?? 'visitor';
    $badgeClass = $badgeClasses[$roleName] ?? $badgeClasses['visitor'];
    $displayName = $displayNames[$roleName] ?? $role->display_name ?? '未知角色';
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $badgeClass }}">
    @if($roleName === 'administrator')
        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
    @elseif($roleName === 'premium_member')
        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>
    @endif
    {{ $displayName }}
</span>
