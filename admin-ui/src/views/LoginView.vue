<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const login = ref('')
const password = ref('')

async function submit() {
  const success = await auth.login(login.value.trim(), password.value)
  password.value = ''
  if (success) {
    const target = route.query.redirect
    await router.replace(
      typeof target === 'string' && /^\/fonts(?:\/|\?|$)/.test(target) ? target : '/fonts',
    )
  }
}
</script>

<template>
  <section class="login-card" aria-labelledby="login-title">
    <p class="eyebrow">Административная панель</p>
    <h1 id="login-title">Вход</h1>
    <p class="muted">Войдите, чтобы управлять каталогом шрифтов.</p>
    <form @submit.prevent="submit">
      <fieldset class="form-fields" :disabled="auth.loading">
        <div>
          <label for="login">Логин</label>
          <input id="login" v-model="login" autocomplete="username" required maxlength="64" />
        </div>
        <div>
          <label for="password">Пароль</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
          />
        </div>
        <p v-if="auth.error" class="form-status error-summary" role="alert">{{ auth.error }}</p>
        <button class="btn btn-primary" type="submit">
          {{ auth.loading ? 'Вход…' : 'Войти' }}
        </button>
      </fieldset>
    </form>
  </section>
</template>
