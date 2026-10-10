import { createRouter, createWebHistory } from 'vue-router'
import FontsView from '@/views/FontsView.vue'
import FontEditorView from '@/views/FontEditorView.vue'
import FontDeleteView from '@/views/FontDeleteView.vue'
import NotFoundView from '@/views/NotFoundView.vue'
import LoginView from '@/views/LoginView.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: (_to, _from, savedPosition) => savedPosition || { top: 0 },
  routes: [
    { path: '/', redirect: '/fonts' },
    { path: '/login', name: 'login', component: LoginView, meta: { title: 'Вход', public: true } },
    { path: '/fonts', name: 'fonts', component: FontsView, meta: { title: 'Шрифты' } },
    {
      path: '/fonts/create',
      name: 'font-create',
      component: FontEditorView,
      meta: { title: 'Добавить шрифт' },
    },
    {
      path: '/fonts/:id/edit',
      name: 'font-edit',
      component: FontEditorView,
      meta: { title: 'Редактирование' },
    },
    {
      path: '/fonts/:id/delete',
      name: 'font-delete',
      component: FontDeleteView,
      meta: { title: 'Удаление' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: NotFoundView,
      meta: { title: 'Страница не найдена' },
    },
  ],
})
router.beforeEach((to) => {
  const auth = useAuthStore()
  if (!to.meta.public && !auth.authenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.name === 'login' && auth.authenticated) return { name: 'fonts' }
})
router.afterEach((to) => {
  document.title = `${to.meta.title} · Админка · Галерея шрифтов`
})
export default router
