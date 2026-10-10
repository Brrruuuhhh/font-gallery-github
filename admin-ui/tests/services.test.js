import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createServer } from 'vite'
import { createPinia, setActivePinia } from 'pinia'
import { AxiosError } from 'axios'

// Проверяем настоящие службы запросов и хранилища состояния, подменяя HTTP-ответы без запуска сервера и подключения к БД.
let server
let api
let configureApiAuth
let useFontsStore
let useAuthStore

before(async () => {
  const root = fileURLToPath(new URL('../', import.meta.url))
  server = await createServer({
    root,
    configFile: false,
    logLevel: 'error',
    optimizeDeps: { noDiscovery: true },
    resolve: { alias: { '@': fileURLToPath(new URL('../src', import.meta.url)) } },
    server: { middlewareMode: true, hmr: false, ws: false, watch: null },
  })
  const service = await server.ssrLoadModule('/src/services/api.js')
  api = service.default
  configureApiAuth = service.configureApiAuth
  ;({ useFontsStore } = await server.ssrLoadModule('/src/stores/fonts.js'))
  ;({ useAuthStore } = await server.ssrLoadModule('/src/stores/auth.js'))
})
after(async () => server?.close())

function reply(config, data, status = 200) {
  return { config, data, status, statusText: String(status), headers: {} }
}

function delayedAdapter() {
  const requests = []
  let notify
  api.defaults.adapter = (config) =>
    new Promise((resolve, reject) => {
      requests.push({ config, resolve, reject })
      notify?.()
      notify = undefined
    })
  return {
    requests,
    async next() {
      await new Promise((resolve) => {
        notify = resolve
      })
      return requests.at(-1)
    },
  }
}

test('401 завершает только текущий вход; старый ответ и неверный пароль его не очищают', async () => {
  let token = 'current-token'
  let expired = 0
  configureApiAuth(
    () => token,
    () => expired++,
  )
  api.defaults.adapter = async (config) => {
    if (config.url === '/stale') token = 'new-token'
    throw new AxiosError(
      'Unauthorized',
      '',
      config,
      null,
      reply(config, { error: { code: 'INVALID_TOKEN', message: 'Войдите повторно.' } }, 401),
    )
  }
  await assert.rejects(api.get('/fonts'), (error) => error.status === 401)
  assert.equal(expired, 1)
  await assert.rejects(api.post('/auth', {}), (error) => error.status === 401)
  await assert.rejects(api.get('/stale'), (error) => error.status === 401)
  assert.equal(expired, 1)
  assert.equal(token, 'new-token')
})

test('результат позднего поиска не заменяет новые данные и не меняет состояние ожидания', async () => {
  setActivePinia(createPinia())
  configureApiAuth(
    () => 'test-token',
    () => {},
  )
  const adapter = delayedAdapter()
  const store = useFontsStore()
  store.search = 'first'
  const firstRequest = adapter.next()
  const firstLoad = store.loadFonts()
  const first = await firstRequest
  store.search = 'second'
  const secondRequest = adapter.next()
  const secondLoad = store.loadFonts()
  const second = await secondRequest
  assert.equal(first.config.params.q, 'first')
  assert.equal(second.config.params.q, 'second')
  const result = (name) => ({
    data: [{ id: 1, name }],
    pagination: { total: 1, catalogTotal: 1, pages: 1, page: 1 },
  })
  second.resolve(reply(second.config, result('second')))
  await secondLoad
  first.resolve(reply(first.config, result('first')))
  await firstLoad
  assert.equal(store.fonts[0].name, 'second')
  assert.equal(store.loading, false)
  assert.equal(store.error, '')
})

test('выход очищает список и отменённый запрос не восстанавливает его', async () => {
  setActivePinia(createPinia())
  configureApiAuth(
    () => 'test-token',
    () => {},
  )
  const adapter = delayedAdapter()
  const store = useFontsStore()
  const pending = adapter.next()
  const load = store.loadFonts()
  const request = await pending
  store.reset()
  request.resolve(
    reply(request.config, {
      data: [{ id: 1 }],
      pagination: { total: 1, catalogTotal: 1, pages: 1, page: 1 },
    }),
  )
  await load
  assert.deepEqual(store.fonts, [])
  assert.equal(store.loaded, false)
  assert.equal(store.loading, false)
})

test('поздний ответ входа после выхода не возвращает токен', async () => {
  setActivePinia(createPinia())
  configureApiAuth(
    () => '',
    () => {},
  )
  const adapter = delayedAdapter()
  const auth = useAuthStore()
  const pending = adapter.next()
  const login = auth.login('admin', 'not-a-real-password')
  const request = await pending
  auth.logout()
  request.resolve(
    reply(request.config, {
      data: {
        token: 'test-token',
        expiresAt: Math.floor(Date.now() / 1000) + 7200,
        user: { id: 1, login: 'admin', role: 'admin' },
      },
    }),
  )
  assert.equal(await login, false)
  assert.equal(auth.token, '')
  assert.equal(auth.authenticated, false)
  assert.equal(auth.loading, false)
})

test('завершённое после выхода действие не оставляет уведомление для следующего входа', async () => {
  setActivePinia(createPinia())
  configureApiAuth(() => 'test-token', () => {})
  const adapter = delayedAdapter()
  const store = useFontsStore()
  const pending = adapter.next()
  const deletion = store.removeFont(123)
  const request = await pending
  store.reset()
  request.resolve(reply(request.config, '', 204))
  await deletion
  assert.equal(store.notice, '')
})
