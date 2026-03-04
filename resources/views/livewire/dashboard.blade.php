<x-layouts.app>
    <section class="bg-gray-50 dark:bg-gray-900">
        <div class="h-[70vh] flex flex-col items-center justify-center grow lg:py-0 transition-all ease-in-out delay-200">
            <div class="flex flex-col items-center justify-center m-5">
                <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">Hello,  {{ auth()->user()->name }} !</h1>
            </div>
            <div class="flex flex-col items-center justify-center">
                teste   
            </div>
        </div>
    </section>
</x-layouts.app>