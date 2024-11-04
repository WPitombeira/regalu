<div>
    <section class="bg-white dark:bg-gray-900">
        <div class="grid max-w-screen-xl px-6 py-8 mx-auto lg:gap-8 xl:gap-0 lg:py-16 lg:grid-cols-12">
            <div class="lg:mr-auto place-self-center text-center lg:col-span-7">
                <h1 class="max-w-2xl mb-4 text-4xl font-extrabold tracking-tight leading-none md:text-5xl xl:text-6xl dark:text-white">{{ __("messages.hero.headline") }}</h1>
                <p class="max-w-2xl mb-6 font-light text-gray-500 lg:mb-8 md:text-lg lg:text-xl dark:text-gray-400">{{ __("messages.hero.subheadline") }}</p>
                <a href="/login" class="inline-flex items-center justify-center px-5 py-3 mr-3 text-base font-medium text-center text-white rounded-lg bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-900">
                    {{ __("messages.get_started") }}
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
                    <div class="flex-shrink-0 flex flex-col justify-center">
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
                    <div class="flex-shrink-0">
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
                    <div class="flex-shrink-0">
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
                    <div class="flex-shrink-0">
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

    <section id="data_privacy" class="bg-white mt-10 dark:bg-gray-900 py-10">
        <div class="w-100 m-auto justify-center flex">
            <div class="grid lg:grid-cols-2 gap-4 place-content-center">
                <div class="max-w-lg md:px-4">
                    <h1 class="text-xl font-bold tracking-tight leading-none md:text-xl xl:text-2xl dark:text-white">{{ __("messages.newsletter.headline") }}</h1>
                    <p class="mb-6 font-light text-gray-500 lg:mb-8 md:text-lg lg:text-xl dark:text-gray-400">{{ __("messages.newsletter.subheadline") }}</h2>
                </div>
                <div class="align-middle flex flex-col justify-center">
                    <form class="flex flex-col" wire:submit="registerNewsletter">
                        <div class="grid grid-cols-3 gap-4">
                            <input wire:model="email"  class="inset-0 py-2 px-3.5 text-sm h-9 col-span-2 form-input rounded-md px-4 py-3" type="email" placeholder="Your best e-mail" required="">
                            <button type="submit" class="hover:text-violet-900 hover:bg-gray-200 transition hover:duration-200 ease-in-out shadow-sm text-violet-800 bg-white text-black font-bold text-sm rounded-md py-2 px-3.5">{{ __("messages.buttons.notify_me") }}</button>
                        </div>
                        <span class="m-auto text-base">{{ __("messages.newsletter.caredata") }} <a href="#" class="font-bold">{{ __("messages.newsletter.privacy_policy") }}</a></span>
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
                <form action="#" class="w-full flex flex-row justify-center">
                    <div class="w-8/12">
                        <div class="mb-4">
                            <label for="contact_name" class="hidden">Your Name</label>
                            <input type="text" class="form rounded-lg border border-gray-300 text-gray-900 dark:text-white focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Your Name (optional)">
                        </div>
                        <div class="mb-4">
                            <label for="contact_name" class="hidden">Your Email</label>
                            <input type="email" class="form rounded-lg border border-gray-300 text-gray-900 dark:text-white focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Your Email (optional)">
                        </div>
                        <div class="mb-4">
                            <label for="contact_name" class="hidden">Your Message</label>
                            <textarea placeholder="Your Message" class="form rounded-lg border border-gray-300 text-gray-900 dark:text-white focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" rows="5"></textarea>
                        </div>
                        <div class="w-full flex justify-center">
                            <button class="w-4/12 font-base text-lg hover:text-violet-900 hover:bg-gray-200 transition hover:duration-200 ease-in-out shadow-sm text-violet-800 bg-white text-black font-bold text-sm rounded-md py-2 px-3.5" type="button">Submit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
