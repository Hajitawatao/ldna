import { ref } from 'vue'

export class ApiError extends Error {
  constructor(message, { status = 0, fields = null } = {}) {
    super(message)
    this.status = status
    this.fields = fields
  }
}

async function request(path, { method = 'GET', body, timeout = 10000 } = {}) {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), timeout)
  try {
    const res = await fetch(`/api/${path}`, {
      method,
      headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
      body: body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    })
    const isJson = res.headers.get('content-type')?.includes('application/json')
    if (!isJson) {
      throw new ApiError('The API server is not responding. Check that the PHP server is running on port 8000.', { status: res.status })
    }
    const data = await res.json()
    if (!res.ok) throw new ApiError(data.error ?? `The API returned an error (${res.status}).`, { status: res.status, fields: data.fields })
    return data
  } catch (err) {
    if (err instanceof ApiError) throw err
    if (err.name === 'AbortError') throw new ApiError('The API took too long to respond.')
    throw new ApiError('Could not reach the API. Check your connection and the PHP server.')
  } finally {
    clearTimeout(timer)
  }
}

export const apiGet = (path) => request(path)
export const apiPost = (path, body) => request(path, { method: 'POST', body })
export const apiPut = (path, body) => request(path, { method: 'PUT', body })
export const apiDelete = (path) => request(path, { method: 'DELETE' })

/** Reactive GET: `const { data, loading, error, load } = useApiGet('stats.php')` */
export function useApiGet(path, { immediate = true } = {}) {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)

  async function load() {
    loading.value = true
    error.value = null
    try {
      data.value = await apiGet(typeof path === 'function' ? path() : path)
    } catch (err) {
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  if (immediate) load()
  return { data, loading, error, load }
}
