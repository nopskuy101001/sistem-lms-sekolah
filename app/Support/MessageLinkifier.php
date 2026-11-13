<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

final class MessageLinkifier
{
    private const URL_PATTERN = '~(?<![\w@])((?:https?://|www\.)[^\s<>"\']+)~iu';

    public static function render(?string $text): HtmlString
    {
        if ($text === null || $text === '') {
            return new HtmlString('');
        }

        preg_match_all(self::URL_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE);

        if ($matches[0] === []) {
            return new HtmlString(e($text));
        }

        $html = '';
        $offset = 0;

        foreach ($matches[0] as [$matchedUrl, $position]) {
            $html .= e(substr($text, $offset, $position - $offset));
            [$url, $trailingPunctuation] = self::separateTrailingPunctuation($matchedUrl);

            if ($url === '') {
                $html .= e($matchedUrl);
            } else {
                $href = str_starts_with(strtolower($url), 'www.') ? 'https://'.$url : $url;
                $html .= '<a href="'.e($href).'" target="_blank" rel="noopener noreferrer nofollow" '
                    .'class="break-all font-semibold underline decoration-current underline-offset-2 hover:opacity-80">'
                    .e($url).'</a>'.e($trailingPunctuation);
            }

            $offset = $position + strlen($matchedUrl);
        }

        $html .= e(substr($text, $offset));

        return new HtmlString($html);
    }

    /**
     * Keep sentence punctuation outside the generated anchor.
     *
     * @return array{string, string}
     */
    private static function separateTrailingPunctuation(string $url): array
    {
        if (! preg_match('/[.,!?;:\)\]\}]+$/u', $url, $match, PREG_OFFSET_CAPTURE)) {
            return [$url, ''];
        }

        $position = $match[0][1];

        return [substr($url, 0, $position), substr($url, $position)];
    }
}
