<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-[#F9F6F1]">
        <div>
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white text-gray-800 border border-gray-200 shadow-lg overflow-hidden sm:rounded-xl">
            <slot />
        </div>
    </div>
</template>
