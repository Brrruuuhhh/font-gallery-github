import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authenticate } from '@/services/auth-service'

export const useAuthStore = defineStore('auth', () => {
  const token = ref('')
  const user = ref(null)
  const expiresAt = ref(0)
  const loading = ref(false)
  const error = ref('')
  const authenticated = computed(() => Boolean(token.value) && expiresAt.value > Date.now() / 1000)
  let expiryTimer
  let attempt = 0

  function logout(message = '') {
    attempt++
    clearTimeout(expiryTimer)
    token.value = ''
    user.value = null
    expiresAt.value = 0
    loading.value = false
    error.value = message
  }

  async function login(loginName, password) {
    if (loading.value) return false
    const current = ++attempt
    loading.value = true
    error.value = ''
    try {
      const result = await authenticate(loginName, password)
      if (current !== attempt) return false
      if (
        !result.token ||
        !Number.isInteger(result.expiresAt) ||
        result.expiresAt <= Date.now() / 1000
      ) {
        throw new Error('Не удалось выполнить вход. Повторите попытку.')
      }
      user.value = result.user
      expiresAt.value = result.expiresAt
      token.value = result.token
      clearTimeout(expiryTimer)
      expiryTimer = setTimeout(
        () => logout('Время входа истекло. Войдите повторно.'),
        Math.max(0, result.expiresAt * 1000 - Date.now()),
      )
      return true
    } catch (cause) {
      if (current === attempt) error.value = cause.message
      return false
    } finally {
      if (current === attempt) loading.value = false
    }
  }

  return { token, user, expiresAt, loading, error, authenticated, login, logout }
})
