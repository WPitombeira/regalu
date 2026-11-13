<div>
    <section class="bg-white dark:bg-gray-900">
        <div class="grid max-w-screen-xl px-6 py-8 mx-auto lg:gap-8 xl:gap-0 lg:py-16 lg:grid-cols-12">
            <div class="lg:mr-auto place-self-center text-center lg:col-span-7">
                <h1 class="max-w-2xl mb-4 text-4xl font-extrabold tracking-tight leading-none md:text-5xl xl:text-6xl dark:text-white">{{ __("messages.hero.headline") }}</h1>
                <p class="max-w-2xl mb-6 font-light text-gray-500 lg:mb-8 md:text-lg lg:text-xl dark:text-gray-400">{{ __("messages.hero.subheadline") }}</p>
                <a href="/login" class="inline-flex items-center justify-center px-5 py-3 mr-3 text-base font-medium text-center text-white rounded-lg bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-900">
                    {{ __("messages.buttons.get_started") }}
                    <svg class="w-5 h-5 ml-2 -mr-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                </a>
            </div>
            <div class="hidden lg:mt-0 lg:col-span-5 lg:flex">
                <img src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/hero/phone-mockup.png" alt="mockup">
            </div>                
        </div>
    </section>

    <section id="features" class="bg-white dark:bg-gray-900 mt-10">
        <div class="grid max-w-screen-xl px-6 py-8 mx-auto lg:gap-8 xl:gap-0 lg:py-16 lg:grid-cols-12">
            <div class="lg:mr-auto place-self-center text-center lg:col-span-7">
                <div>
                    <h1 class="max-w-2xl mb-4 text-4xl font-extrabold tracking-tight leading-none md:text-5xl xl:text-6xl dark:text-white">{{ __("messages.features.headline") }}</h1>
                    <h2 class="max-w-2xl mb-6 font-light text-white lg:mb-8 text-2xl dark:text-gray-400">{{ __("messages.features.subheadline") }}</h2>
                </div>
                <div>
                    <span class="antialiased tracking-tight font-light text-gray-400 text-justify lg:mb-8 text-lg">{!! __("messages.features.content") !!}</span>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-8 lg:col-span-5">
                <div class="flex items center">
                    <div class="shrink-0 flex flex-col justify-center">
                        <div class="flex flex-col items-center items center justify-center w-12 h-12 text-white bg-primary-700 rounded-md">
                            <x-ui.icons.heart class="w-8 h-8" />
                        </div>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __("messages.features.wishlist.headline") }}</h2>
                        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">{{ __("messages.features.wishlist.subheadline") }}</p>
                    </div>
                </div>
                <div class="flex items center">
                    <div class="shrink-0">
                        <div class="flex flex-col items-center items center justify-center w-12 h-12 text-white bg-primary-700 rounded-md">
                            <x-ui.icons.share class="w-8 h-8" />
                        </div>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __("messages.features.family_friends.headline") }}</h2>
                        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">{{ __("messages.features.family_friends.subheadline") }}</p>
                    </div>
                </div>
                <div class="flex items center">
                    <div class="shrink-0">
                        <div class="flex flex-col items-center items center justify-center w-12 h-12 text-white bg-primary-700 rounded-md">
                            <x-ui.icons.notifications class="w-8 h-8" />
                        </div>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __("messages.features.notifications.headline") }}</h2>
                        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">{{ __("messages.features.notifications.subheadline") }}</p>
                    </div>
                </div>
                <div class="flex items center">
                    <div class="shrink-0">
                        <div class="flex flex-col items-center items center justify-center w-12 h-12 text-white bg-primary-700 rounded-md">
                            <x-ui.icons.lock class="w-8 h-8" />
                        </div>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __("messages.features.privacy.headline") }}</h2>
                        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">{{ __("messages.features.privacy.subheadline") }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="newsletter" class="bg-white mt-10 dark:bg-gray-900 py-10">
        <div class="mx-auto max-w-6xl px-4">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="min-w-0 max-w-lg md:px-4">
                    <h2 class="text-xl font-bold tracking-tight leading-none md:text-xl xl:text-2xl dark:text-white">{{ __("messages.newsletter.headline") }}</h2>
                    <p class="mt-3 font-light text-gray-500 md:text-lg lg:text-xl dark:text-gray-400">{{ __("messages.newsletter.subheadline") }}</p>
                </div>
                <div class="min-w-0">
                    <form class="flex flex-col gap-3" wire:submit="registerNewsletter">
                        <label for="newsletter-email" class="text-sm font-medium text-gray-900 dark:text-white">{{ __('newsletter.email') }}</label>
                        <input id="newsletter-email" wire:model.live.blur="email" class="form-input min-h-11 w-full min-w-0 rounded-md border-gray-300 px-4 py-3 text-base text-gray-900 focus:border-violet-500 focus:ring-violet-500" type="email" autocomplete="email" maxlength="254" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="newsletter-purpose{{ $errors->has('email') ? ' newsletter-email-error' : '' }}">
                        @error('email')
                            <p id="newsletter-email-error" role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                        @enderror
                        <p id="newsletter-purpose" class="text-sm text-gray-600 dark:text-gray-300">{{ __('newsletter.purpose') }}</p>
                        <div class="flex min-h-11 items-start gap-3">
                            <input id="newsletter-consent" wire:model.live="newsletterConsent" type="checkbox" required class="mt-3 h-5 w-5 shrink-0 rounded border-gray-300 text-violet-700 focus:ring-violet-500" aria-invalid="{{ $errors->has('newsletterConsent') ? 'true' : 'false' }}" @if($errors->has('newsletterConsent')) aria-describedby="newsletter-consent-error" @endif>
                            <label for="newsletter-consent" class="min-h-11 cursor-pointer py-2 text-sm text-gray-900 dark:text-white">{{ __('newsletter.consent') }}</label>
                        </div>
                        @error('newsletterConsent')
                            <p id="newsletter-consent-error" role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                        @enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="registerNewsletter" class="min-h-11 w-full rounded-md bg-white px-4 py-3 text-sm font-bold text-violet-800 shadow-xs transition hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-500 disabled:cursor-wait disabled:opacity-60 sm:w-auto sm:self-start">{{ __("messages.buttons.notify_me") }}</button>
                        @error('newsletter')
                            <p id="newsletter-error" role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                        @enderror
                        @if($newsletterSubmitted)
                            <p id="newsletter-status" role="status" aria-live="polite" class="text-sm text-green-800 dark:text-green-300">{{ __('newsletter.success') }}</p>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </section>
    
    <section id="contact" class="bg-white mt-10 dark:bg-gray-900">
        <div class="grid grid-rows-[10vh_1fr] min-h-[50vh] max-w-screen-xl px-6 py-8 mx-auto justify-center">
            <div class="flex flex-col">
                <h1 class="text-4xl text-white font-bold">Have any suggestion, feedback or need assistance?</h1>
                <h2 class="text-lg text-center dark:text-white font-light">We would love to hear you</h2>
            </div>
            <div>
                <form wire:submit="registerFeedback" class="w-full flex flex-row justify-center">
                    <div class="w-8/12">
                        <div class="mb-4">
                            <label for="feedback_name" class="hidden">Your Name</label>
                            <input type="text" wire:model="feedback_name" class="form rounded-lg border border-gray-300 text-gray-900 dark:text-white focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Your Name (optional)">
                        </div>
                        <div class="mb-4">
                            <label for="feedback_email" class="hidden">Your Email</label>
                            <input type="email" wire:model="feedback_email" class="form rounded-lg border border-gray-300 text-gray-900 dark:text-white focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Your Email (optional)">
                        </div>
                        <div class="mb-4">
                            <label for="feedback_message" class="hidden">Your Message</label>
                            <textarea placeholder="Your Message" wire:model="feedback_message" class="form rounded-lg border border-gray-300 text-gray-900 dark:text-white focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" rows="5"></textarea>
                            @error('feedback_message')
                                <span>Missing field</span>
                            @enderror
                        </div>
                        @error('feedback')
                            <p id="contact-error" role="alert" class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <div class="w-full flex justify-center">
                            <button class="w-4/12 font-base text-lg hover:text-violet-900 hover:bg-gray-200 transition hover:duration-200 ease-in-out shadow-xs text-violet-800 bg-white text-black font-bold text-sm rounded-md py-2 px-3.5" type="submit">Submit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
