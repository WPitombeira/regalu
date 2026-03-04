<?php

namespace App\Services;

class UrlMetaFetchService {
    /**
     * Fetch Open Graph meta (title and image) from a given URL.
     *
     * @return array{title?: string, image?: string}
     */
    public function fetch(string $url): array {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'Mozilla/5.0 (compatible; Regalu/1.0)',
                ],
            ]);

            $html = @file_get_contents($url, false, $context);

            if ($html === false) {
                return [];
            }

            libxml_use_internal_errors(true);
            $doc = new \DOMDocument();
            $doc->loadHTML($html);
            libxml_clear_errors();

            $result = [];
            $metas = $doc->getElementsByTagName('meta');

            foreach ($metas as $meta) {
                $property = $meta->getAttribute('property');
                $content = $meta->getAttribute('content');

                if ($property === 'og:title' && $content !== '') {
                    $result['title'] = $content;
                }

                if ($property === 'og:image' && $content !== '') {
                    $result['image'] = $content;
                }
            }

            // Fallback to <title> tag if og:title not found
            if (!isset($result['title'])) {
                $titleTags = $doc->getElementsByTagName('title');
                if ($titleTags->length > 0) {
                    $titleText = trim($titleTags->item(0)->textContent);
                    if ($titleText !== '') {
                        $result['title'] = $titleText;
                    }
                }
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }
}
