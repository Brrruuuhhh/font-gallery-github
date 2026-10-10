import api from './api'

export async function authenticate(login, password) {
  const response = await api.post('/auth', { login, password })
  return response.data.data
}
