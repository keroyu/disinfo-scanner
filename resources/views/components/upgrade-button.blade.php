{{-- Upgrade to Paid Member Button Component --}}
{{-- Usage: @include('components.upgrade-button', ['size' => 'sm|md|lg', 'variant' => 'primary|secondary']) --}}

@props([
    'size' => 'md',           // sm, md, lg
    'variant' => 'primary'    // primary, secondary
])

@php
    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base'
    ];

    $variantClasses = [
        'primary' => 'bg-gradient-to-r from-yellow-400 to-orange-500 text-white hover:from-yellow-500 hover:to-orange-600 shadow-md hover:shadow-lg',
        'secondary' => 'bg-white text-orange-600 border-2 border-orange-500 hover:bg-orange-50'
    ];

    $buttonClasses = $sizeClasses[$size] . ' ' . $variantClasses[$variant];
@endphp

{{-- Only show for Regular Members --}}
@if(auth()->check() && auth()->user()->roles->contains('name', 'regular_member'))
<div class="inline-flex items-center">
    <button type="button"
            onclick="showUpgradeModal()"
            class="inline-flex items-center font-semibold rounded-lg transition-all duration-200 {{ $buttonClasses }} transform hover:scale-105">
        <svg class="mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
        升級為付費會員
    </button>
</div>

<script>
function showUpgradeModal() {
    // Dispatch Alpine.js event to show the permission modal
    window.dispatchEvent(new CustomEvent('permission-modal', {
        detail: { type: 'upgrade', feature: '' }
    }));
}
</script>
@endif
