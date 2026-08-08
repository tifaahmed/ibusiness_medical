<template>
  <div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-2">
      {{ title }}
    </h1>
    <nav class="flex" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-3">
        <li
          v-for="(breadcrumb, index) in breadcrumbs"
          :key="index"
          class="inline-flex items-center"
        >
          <div v-if="index > 0" class="flex items-center">
            <svg
              class="w-6 h-6 text-gray-400"
              fill="currentColor"
              viewBox="0 0 20 20"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                fill-rule="evenodd"
                d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
          <Link
            v-if="breadcrumb.link && !breadcrumb.active"
            :href="breadcrumb.link"
            class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
          >
            {{ breadcrumb.label }}
          </Link>
          <span
            v-else
            :class="[
              'text-sm font-medium',
              breadcrumb.active
                ? 'text-gray-500 dark:text-gray-400'
                : 'text-gray-700 dark:text-gray-300',
            ]"
            :aria-current="breadcrumb.active ? 'page' : undefined"
          >
            {{ breadcrumb.label }}
          </span>
        </li>
      </ol>
    </nav>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  title: {
    type: String,
    required: true,
  },
  breadcrumbs: {
    type: Array,
    required: true,
    default: () => [],
  },
});
</script>


