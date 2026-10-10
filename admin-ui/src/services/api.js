import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/admin',
  timeout: 60000,
})

let getToken = () => ''
let expireSession = () => {}

export function configureApiAuth(tokenProvider, onUnauthorized) {
  getToken = tokenProvider
  expireSession = onUnauthorized
}

export class ApiError extends Error {
  constructor(message, status = 0, code = '', fields = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.fields = fields
  }
}

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token && config.url !== '/auth') config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (response) => response,
  (cause) => {
    if (axios.isCancel(cause)) return Promise.reject(cause)
    const status = cause.response?.status ?? 0
    const details = cause.response?.data?.error
    if (
      status === 401 &&
      cause.config?.url !== '/auth' &&
      cause.config?.headers?.Authorization === `Bearer ${getToken()}`
    ) {
      expireSession()
    }
    return Promise.reject(
      new ApiError(
        details?.message ||
          (status
            ? 'Не удалось выполнить запрос. Повторите попытку позднее.'
            : 'Нет соединения с сервером. Проверьте подключение и повторите попытку.'),
        status,
        details?.code || cause.code,
        details?.fields || {},
      ),
    )
  },
)

export default api
