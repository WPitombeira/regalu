<x-mail::message>
# {{ __('wishlist_purchase.mail_subject') }}

{{ __('wishlist_purchase.mail_body') }}

<x-mail::button :url="$wishlistUrl">
{{ __('wishlist_purchase.mail_action') }}
</x-mail::button>

{{ __('wishlist_purchase.owner_privacy') }}
</x-mail::message>
