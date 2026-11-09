<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

class UrlMetaFetchService
{
    private const MAX_BODY_BYTES = 524288;

    private const CACHE_TTL_SECONDS = 21600;

    public function __construct(private PublicUrlResolver $urlResolver) {}

    /**
     * Fetch Open Graph meta (title and image) from a given URL.
     *
     * @return array{title?: string, image?: string}
     */
    public function fetch(string $url): array
    {
        $resolved = $this->urlResolver->resolve($url);

        if ($resolved === null || ! $this->curlTransportAvailable()) {
            return [];
        }

        $cacheKey = 'url-meta:v1:'.hash('sha256', $url);
        $cached = Cache::get($cacheKey);

        if ($this->isValidMetadata($cached)) {
            return $cached;
        }

        $sink = fopen('php://temp/maxmemory:65536', 'w+b');

        if ($sink === false) {
            return [];
        }

        try {
            $curlOptions = [
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE => true,
            ];

            if (! $resolved['literal']) {
                $addresses = array_map(
                    static fn (string $address): string => str_contains($address, ':') ? '['.$address.']' : $address,
                    $resolved['addresses'],
                );
                $curlOptions[CURLOPT_RESOLVE] = [
                    $resolved['host'].':'.$resolved['port'].':'.implode(',', $addresses),
                ];
            }

            $response = Http::withOptions([
                'allow_redirects' => false,
                'connect_timeout' => 2,
                'decode_content' => false,
                'on_headers' => static function (ResponseInterface $response): void {
                    $contentLength = trim($response->getHeaderLine('Content-Length'));

                    if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > self::MAX_BODY_BYTES) {
                        throw new \RuntimeException('Response body exceeds the metadata size limit.');
                    }
                },
                'progress' => static fn (int $downloadSize, int $downloaded, int $uploadSize, int $uploaded): bool => $downloaded > self::MAX_BODY_BYTES,
                'proxy' => ['no' => '*'],
                'sink' => $sink,
                'curl' => $curlOptions,
            ])
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.1',
                    'Accept-Encoding' => 'identity',
                ])
                ->withUserAgent('Mozilla/5.0 (compatible; Regalu/1.0)')
                ->connectTimeout(2)
                ->timeout(5)
                ->get($resolved['url']);

            if (! $response->successful()) {
                return [];
            }

            $stat = fstat($sink);
            $size = is_array($stat) ? $stat['size'] : self::MAX_BODY_BYTES + 1;

            if ($size > self::MAX_BODY_BYTES || ! rewind($sink)) {
                return [];
            }

            $html = stream_get_contents($sink, self::MAX_BODY_BYTES + 1);

            if (! is_string($html) || strlen($html) > self::MAX_BODY_BYTES) {
                return [];
            }

            $metadata = $this->parseMetadata($html, $resolved['url']);

            if ($metadata !== []) {
                Cache::put($cacheKey, $metadata, now()->addSeconds(self::CACHE_TTL_SECONDS));
            }

            return $metadata;
        } catch (\Throwable) {
            return [];
        } finally {
            fclose($sink);
        }
    }

    private function curlTransportAvailable(): bool
    {
        return extension_loaded('curl')
            && defined('CURLOPT_RESOLVE');
    }

    /**
     * @return array{title?: string, image?: string}
     */
    private function parseMetadata(string $html, string $sourceUrl): array
    {
        $previousErrorMode = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument;

            if (! $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT)) {
                return [];
            }

            $metadata = [];

            foreach ($document->getElementsByTagName('meta') as $meta) {
                if (strtolower(trim($meta->getAttribute('property'))) === 'og:title') {
                    $title = $this->cleanTitle($meta->getAttribute('content'));

                    if ($title !== '') {
                        $metadata['title'] = $title;
                    }
                }

                if (! isset($metadata['image']) && strtolower(trim($meta->getAttribute('property'))) === 'og:image') {
                    $image = $this->sanitizeImageUrl($meta->getAttribute('content'), $sourceUrl);

                    if ($image !== null) {
                        $metadata['image'] = $image;
                    }
                }
            }

            if (! isset($metadata['title'])) {
                $titleTags = $document->getElementsByTagName('title');
                if ($titleTags->length > 0) {
                    $title = $this->cleanTitle($titleTags->item(0)->textContent);

                    if ($title !== '') {
                        $metadata['title'] = $title;
                    }
                }
            }

            return $metadata;
        } catch (\Throwable) {
            return [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    private function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $title) ?? '');

        if (function_exists('mb_strcut')) {
            return mb_strcut($title, 0, 255, 'UTF-8');
        }

        return substr($title, 0, 255);
    }

    private function sanitizeImageUrl(string $imageUrl, string $sourceUrl): ?string
    {
        $imageUrl = trim($imageUrl);

        if ($imageUrl === '' || strlen($imageUrl) > 2048 || preg_match('/[\x00-\x20\x7f]/', $imageUrl) === 1) {
            return null;
        }

        try {
            $absoluteUrl = (string) UriResolver::resolve(new Uri($sourceUrl), new Uri($imageUrl));
        } catch (\Throwable) {
            return null;
        }

        return $this->urlResolver->resolve($absoluteUrl) === null ? null : $absoluteUrl;
    }

    private function isValidMetadata(mixed $metadata): bool
    {
        if (! is_array($metadata) || $metadata === []) {
            return false;
        }

        foreach ($metadata as $key => $value) {
            if (! in_array($key, ['title', 'image'], true) || ! is_string($value) || $value === '') {
                return false;
            }
        }

        return true;
    }
}
