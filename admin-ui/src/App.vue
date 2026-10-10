<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useFontsStore } from '@/stores/fonts'
import { useAuthStore } from '@/stores/auth'
import './assets/admin.css'

const store = useFontsStore()
const auth = useAuthStore()
const route = useRoute()
const main = ref(null)
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '/api/admin'
const publicSiteUrl = /^https?:\/\//.test(apiBaseUrl) ? new URL(apiBaseUrl).origin + '/' : '/'
const currentTitle = computed(() => (route.name === 'fonts' ? '' : route.meta.title))
watch(
  () => route.fullPath,
  async () => {
    await nextTick()
    main.value?.focus({ preventScroll: true })
  },
)
</script>

<template>
  <a class="skip-link" href="#main" @click.prevent="main?.focus()">К содержимому</a>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" :href="publicSiteUrl" title="На главную страницу галереи шрифтов"
        ><span class="brand-mark" aria-hidden="true">Aa</span
        ><span
          ><span class="brand-title">Галерея шрифтов</span
          ><span class="brand-subtitle">Административная панель</span></span
        ></a
      >
      <nav v-if="auth.authenticated" class="header-nav" aria-label="Основная навигация">
        <RouterLink class="nav-active" to="/fonts" title="К списку шрифтов">Шрифты</RouterLink>
        <span>{{ auth.user?.login }}</span>
        <button class="btn-text" type="button" @click="auth.logout()">Выйти</button>
      </nav>
    </div>
  </header>
  <main id="main" ref="main" class="container" tabindex="-1">
    <nav v-if="auth.authenticated" class="breadcrumbs" aria-label="Хлебные крошки">
      <span>Администрирование</span><span aria-hidden="true">/</span
      ><template v-if="currentTitle"
        ><RouterLink to="/fonts" title="К списку шрифтов">Шрифты</RouterLink
        ><span aria-hidden="true">/</span
        ><span aria-current="page">{{ currentTitle }}</span></template
      ><span v-else aria-current="page">Шрифты</span>
    </nav>
    <div v-if="auth.authenticated && store.notice" class="form-status" role="status">
      {{ store.notice }}
      <button
        class="btn-text"
        type="button"
        aria-label="Закрыть уведомление"
        @click="store.notice = ''"
      >
        Закрыть
      </button>
    </div>
    <RouterView />
  </main>
</template>
