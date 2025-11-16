{{--
  Video Title Cell Component

  Purpose: Renders a clickable video title cell with fixed 200px width.
  Deep links to YouTube video with comment anchor (&lc parameter).

  Props:
  - $videoTitle (string): Display title of the video
  - $videoId (string): YouTube video ID for URL construction
  - $commentId (string): Comment ID for deep link anchor

  Responsive Behavior:
  - Fixed width: 200px (w-[200px])
  - Text truncated to 20 chars with ellipsis
  - Full title visible on hover via title attribute
  - Works on all viewports

  URL Format:
  https://www.youtube.com/watch?v=[VIDEO_ID]&lc=[COMMENT_ID]
  YouTube native navigation to comment on page load

  Accessibility:
  - Semantic <a> element
  - title attribute for full video title
  - aria-label describes link purpose
  - WCAG AA color contrast compliance

  Usage:
  <x-comments.video-title-cell
    :videoTitle="$comment->video->title"
    :videoId="$comment->video_id"
    :commentId="$comment->comment_id"
  />
--}}

<a
    href="https://www.youtube.com/watch?v={{ $videoId }}&lc={{ $commentId }}"
    target="_blank"
    rel="noopener noreferrer"
    class="text-blue-600 hover:text-blue-800 truncate block text-sm w-[200px] overflow-hidden text-ellipsis whitespace-nowrap"
    title="{{ $videoTitle }}"
    aria-label="Navigate to video: {{ $videoTitle }} with comment highlighted"
>
    {{ Str::limit($videoTitle, 20) }}
</a>
