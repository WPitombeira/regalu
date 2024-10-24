<div>
    <section class="bg-white dark:bg-gray-900">
        <div class="grid max-w-screen-xl px-4 py-8 mx-auto lg:gap-8 xl:gap-0 lg:py-16 lg:grid-cols-12">
            <div class="mr-auto place-self-center lg:col-span-7">
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

    <section class="bg-white dark:bg-gray-900 pb-[40px]">
        <div class="w-100 m-auto justify-center flex">
            <div class="grid grid-cols-2 gap-4 place-content-center">
                <div class="max-w-lg">
                    <h1 class="text-xl font-bold tracking-tight leading-none md:text-xl xl:text-2xl dark:text-white">{{ __("messages.newsletter.headline") }}</h1>
                    <p class="mb-6 font-light text-gray-500 lg:mb-8 md:text-lg lg:text-xl dark:text-gray-400">{{ __("messages.newsletter.subheadline") }}</h2>
                </div>
                <div class="align-middle">
                    <form class="flex flex-col">
                        <div class="grid grid-cols-3 gap-4">
                            <input class="inset-0 py-2 px-3.5 text-sm h-9 col-span-2 form-input rounded-md px-4 py-3" type="email" name="email" id="email" placeholder="Your best e-mail" required="">
                            <button type="button" class="hover:text-violet-900 hover:bg-gray-200 transition hover:duration-200 ease-in-out shadow-sm text-violet-800 bg-white text-black font-bold text-sm rounded-md py-2 px-3.5">{{ __("messages.buttons.notify_me") }}</button>
                        </div>
                        <span class="m-auto text-base">{{ __("messages.newsletter.caredata") }} <a href="#" class="font-bold">{{ __("messages.newsletter.privacy_policy") }}</a></span>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
