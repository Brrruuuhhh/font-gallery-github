<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, required: true },
})
</script>

<template>
  <section class="empty-state">
    <div class="empty-symbol" aria-hidden="true">Aa</div>
    <h1>{{ title }}</h1>
    <p class="muted">{{ description }}</p>
    <slot />
  </section>
</template>
