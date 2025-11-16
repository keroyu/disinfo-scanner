{{--
  Sortable Column Header Component

  Purpose: Renders sortable table column header with direction indicator.
  Single-click toggles column sort; repeated clicks toggle direction.

  Props:
  - $label (string): Display text for column header
  - $sortKey (string): Sort param value ('likes', 'date', etc.)
  - $currentSort (string|null): Current sort column from request
  - $currentDirection (string|null): Sort direction ('asc'/'desc')
  - $hidden (bool, optional): Hide on tablet/mobile if true

  Responsive Behavior:
  - Hidden on tablet (<1024px) if $hidden = true
  - Visible on desktop (>1024px) for critical columns
  - Proper cursor pointer on hover
  - Button style suitable for header

  Sort Indicators:
  - ▲ for ascending (oldest to newest)
  - ▼ for descending (newest to oldest)
  - No indicator if column not sorted

  Accessibility:
  - Semantic <button> element
  - aria-label describes sort state
  - aria-pressed when column is sorted
  - Keyboard accessible (Tab, Enter, Space)
  - WCAG AA color contrast

  Usage:
  <x-comments.column-header
    label="Likes"
    sortKey="likes"
    :currentSort="request('sort')"
    :currentDirection="request('direction')"
    :hidden="false"
  />
--}}

<button
    type="submit"
    name="sort"
    value="{{ $sortKey }}"
    class="hover:text-blue-600 cursor-pointer inline-flex items-center gap-1 @if($hidden) hidden md:inline-flex @endif"
    aria-label="{{ $label }} column, currently @if($currentSort === $sortKey) sorted {{ $currentDirection === 'desc' ? 'descending' : 'ascending' }} @else unsorted @endif"
    @if($currentSort === $sortKey)
        aria-pressed="true"
    @endif
>
    {{ $label }}
    @if($currentSort === $sortKey)
        <span class="text-xs" aria-hidden="true">
            {{ $currentDirection === 'desc' ? '▼' : '▲' }}
        </span>
    @endif
</button>
