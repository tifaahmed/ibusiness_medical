<template>
  <!-- Kept as the historical name: the implementation now lives in
       editor/RichTextEditor.vue, which is the component to change or replace. -->
  <RichTextEditor v-bind="$attrs" />
</template>

<script setup>
import RichTextEditor from './editor/RichTextEditor.vue';
</script>
