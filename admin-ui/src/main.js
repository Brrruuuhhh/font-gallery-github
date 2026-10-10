import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useFontsStore } from '@/stores/fonts'
import { configureApiAuth } from '@/services/api'

import App from './App.vue'
import router from './router'

const app = createApp(App)

const pinia = createPinia()
app.use(pinia)
const auth = useAuthStore(pinia)
configureApiAuth(
  () => auth.token,
  () => auth.logout('Войдите в административную панель повторно.'),
)
watch(
  () => auth.token,
  (token) => {
    if (token) return
    useFontsStore(pinia).reset()
    if (router.currentRoute.value.name !== 'login') router.replace({ name: 'login' })
  },
)
app.use(router)

app.mount('#app')
