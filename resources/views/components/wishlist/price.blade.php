@props(['minimum' => null, 'maximum' => null, 'currency' => null])

@if ($minimum !== null || $maximum !== null)
    @php
        $currencyLabel = $currency ?? __('price_import.unknown_currency');
        // Stored decimals are strings: localize the separator without rounding or conversion.
        $min = app()->getLocale() === 'pt_BR' ? str_replace('.', ',', (string) $minimum) : $minimum;
        $max = app()->getLocale() === 'pt_BR' ? str_replace('.', ',', (string) $maximum) : $maximum;
    @endphp
    <span {{ $attributes }}>
        @if ($minimum !== null && $maximum !== null && $minimum === $maximum)
            {{ __('price_import.exact', ['currency' => $currencyLabel, 'amount' => $min]) }}
        @elseif ($minimum !== null && $maximum !== null)
            {{ __('price_import.range', ['currency' => $currencyLabel, 'min' => $min, 'max' => $max]) }}
        @else
            {{ __($minimum !== null ? 'price_import.from' : 'price_import.up_to', ['currency' => $currencyLabel, 'amount' => $minimum !== null ? $min : $max]) }}
        @endif
    </span>
@endif
