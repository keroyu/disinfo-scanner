{{--
  Channel Name Cell Component

  Purpose: Renders a clickable channel name cell with fixed 100px width and text truncation.
  Links to YouTube channel page; handles responsive display across all breakpoints.

  Props:
  - $channelName (string): Display name of the channel
  - $channelIdentifier (string|null): Channel identifier (@username or channel ID)
  - $channelId (string): Fallback if identifier unavailable

  Responsive Behavior:
  - Fixed width: 100px (w-[100px])
  - Text truncated to 15 chars with ellipsis overflow
  - Full name visible on hover via title attribute
  - Works on mobile, tablet, desktop

  Accessibility:
  - Semantic <a> element for screen readers
  - title attribute for full channel name
  - aria-label provides screen reader context
  - Blue link color meets WCAG AA contrast

  Usage:
  <x-comments.channel-cell
    :channelName="$comment->video->channel->name"
    :channelIdentifier="$comment->video->channel->channel_identifier"
    :channelId="$comment->video->channel->channel_id"
  />
--}}

<a
    href="https://www.youtube.com/@{{ $channelIdentifier ?? $channelId }}"
    target="_blank"
    rel="noopener noreferrer"
    class="text-blue-600 hover:text-blue-800 truncate block text-sm w-[100px] overflow-hidden text-ellipsis whitespace-nowrap"
    title="{{ $channelName }}"
    aria-label="Navigate to {{ $channelName }} YouTube channel"
>
    {{ Str::limit($channelName, 15) }}
</a>
