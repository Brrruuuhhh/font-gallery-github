import api from './api'
import { fontRequestBody } from '@/domain/font-payload'

export async function getFonts(params, signal) {
  const response = await api.get('/fonts', { params, signal })
  return response.data
}

export async function getFont(id, signal) {
  const response = await api.get(`/fonts/${id}`, { signal })
  return response.data.data
}

export async function saveFont(font) {
  const response = await api.request({
    method: font.id === null ? 'POST' : 'PUT',
    url: font.id === null ? '/fonts' : `/fonts/${font.id}`,
    data: fontRequestBody(font),
    timeout: 120000,
  })
  return response.data.data
}

export async function deleteFont(id) {
  await api.delete(`/fonts/${id}`)
}
