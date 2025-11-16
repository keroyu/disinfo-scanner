{{--
  Comment Content Cell Component

  Purpose: Renders comment text with multi-line wrapping and truncation.
  Preserves original line breaks and handles long words/URLs gracefully.

  Props:
  - $content (string): The full comment text
  - $maxLength (int, optional): Max chars to display (default: 150)

  Responsive Behavior:
  - Multi-line wrapping with whitespace-pre-wrap
  - Word breaking for long words/URLs
  - max-w-md readability constraint
  - Truncated via Str::limit() function
  - Full content on hover via title attribute

  Accessibility:
  - Semantic <div> with role="cell"
  - aria-label for screen readers
  - WCAG AA text contrast (gray-700)
  - Preserved line breaks aid readability

  Usage:
  <x-comments.comment-cell
    :content="$comment->text"
    :maxLength="150"
  />
--}}

<div
    class="text-sm text-gray-700 whitespace-pre-wrap break-words max-w-md overflow-hidden"
    title="{{ strlen($content) > ($maxLength ?? 150) ? $content : '' }}"
    role="cell"
    aria-label="Comment content"
>
    {{ Str::limit($content, $maxLength ?? 150) }}
</div>
