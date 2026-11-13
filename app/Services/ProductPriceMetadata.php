<?php

namespace App\Services;

use App\Support\WishlistPrice;
use DOMDocument;

class ProductPriceMetadata
{
    /** @return array{price?: string, currency?: string} */
    public function fromDocument(DOMDocument $document): array
    {
        try {
            return $this->structuredPrice($document);
        } catch (\JsonException|\UnexpectedValueException) {
            return [];
        }
    }

    /** @return array{price?: string, currency?: string} */
    private function structuredPrice(DOMDocument $document): array
    {
        $products = [];
        $scripts = 0;
        $nodesRemaining = 256;
        foreach ($document->getElementsByTagName('script') as $script) {
            if (strtolower(trim($script->getAttribute('type'))) !== 'application/ld+json') {
                continue;
            }

            if (++$scripts > 10) {
                return [];
            }
            $data = $this->decode($script->textContent);
            $products = [...$products, ...$this->products($data, $nodesRemaining)];
        }

        $metaPrice = $this->metaPrice($document);
        if ($products === []) {
            return $metaPrice ?? [];
        }
        if (count($products) !== 1 || ! $this->hasType($products[0], 'Product') || $this->hasType($products[0], 'ProductGroup')) {
            return [];
        }

        $offer = $products[0]['offers'] ?? null;
        if (is_array($offer) && array_is_list($offer) && count($offer) === 1) {
            $offer = $offer[0];
        }
        if (! is_array($offer) || ! $this->hasType($offer, 'Offer') || $this->hasType($offer, 'AggregateOffer')) {
            return [];
        }

        $price = WishlistPrice::normalize($offer['price'] ?? null);
        $currency = $offer['priceCurrency'] ?? null;
        if ($price === null || ! in_array($currency, WishlistPrice::CURRENCIES, true)) {
            return [];
        }

        $quote = ['price' => $price, 'currency' => $currency];

        return $metaPrice === null || $metaPrice === $quote ? $quote : [];
    }

    private function decode(string $json): mixed
    {
        if (! json_validate($json, 16)) {
            throw new \UnexpectedValueException('Invalid structured metadata JSON.');
        }

        // Consume complete strings, including escaped keys. Quote only numeric
        // price values before decoding so excess precision cannot round away.
        $pattern = <<<'REGEX'
            ~("(?:[^"\\]++|\\.)*+")(?:(\s*:\s*)(-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?))?~s
            REGEX;
        $preserved = preg_replace_callback($pattern, static function (array $match): string {
            if (isset($match[3]) && json_decode($match[1], true, 16, JSON_THROW_ON_ERROR) === 'price') {
                return $match[1].$match[2].'"'.$match[3].'"';
            }

            return $match[0];
        }, $json);
        if ($preserved === null) {
            throw new \UnexpectedValueException('Structured metadata exceeds the token budget.');
        }

        return json_decode($preserved, true, 16, JSON_THROW_ON_ERROR);
    }

    /** @return array{price?: string, currency?: string}|null */
    private function metaPrice(DOMDocument $document): ?array
    {
        $amounts = [];
        $currencies = [];
        foreach ($document->getElementsByTagName('meta') as $meta) {
            $property = strtolower(trim($meta->getAttribute('property')));
            if ($property === 'product:price:amount') {
                $amounts[] = $meta->getAttribute('content');
            } elseif ($property === 'product:price:currency') {
                $currencies[] = $meta->getAttribute('content');
            }
        }

        if ($amounts === [] && $currencies === []) {
            return null;
        }
        if (count($amounts) !== 1 || count($currencies) !== 1) {
            return [];
        }
        $price = WishlistPrice::normalize($amounts[0]);

        return $price !== null && in_array($currencies[0], WishlistPrice::CURRENCIES, true)
            ? ['price' => $price, 'currency' => $currencies[0]]
            : [];
    }

    /** @return list<array<string, mixed>> */
    private function products(mixed $node, int &$nodesRemaining): array
    {
        if (! is_array($node)) {
            return [];
        }
        if (--$nodesRemaining < 0) {
            throw new \UnexpectedValueException('Structured metadata exceeds the node budget.');
        }

        $products = $this->hasType($node, 'Product') || $this->hasType($node, 'ProductGroup') ? [$node] : [];
        foreach ($node as $child) {
            if (is_array($child)) {
                $products = [...$products, ...$this->products($child, $nodesRemaining)];
            }
        }

        return $products;
    }

    /** @param array<mixed> $node */
    private function hasType(array $node, string $type): bool
    {
        $types = $node['@type'] ?? [];
        $types = is_array($types) ? $types : [$types];
        foreach ($types as $value) {
            if (! is_string($value)) {
                throw new \UnexpectedValueException('Invalid structured metadata type.');
            }
        }

        return array_intersect($types, [$type, 'https://schema.org/'.$type, 'http://schema.org/'.$type]) !== [];
    }
}
