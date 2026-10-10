<script setup>
import { RouterLink } from 'vue-router'
import EmptyState from '@/components/EmptyState.vue'
</script>

<template>
  <EmptyState
    title="Страница не найдена"
    description="Такого раздела нет в административной панели."
    ><RouterLink class="btn btn-primary" to="/fonts" title="К списку шрифтов"
      >К списку шрифтов</RouterLink
    ></EmptyState
  >
</template>
