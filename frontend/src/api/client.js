import axios from 'axios';

const TOKEN_KEY = 'wt_token';

export const tokenStore = {
  get: () => { try { return localStorage.getItem(TOKEN_KEY); } catch { return null; } },
  set: (t) => { try { t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY); } catch { /* storage unavailable */ } },
};

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
  headers: { Accept: 'application/json' },
  timeout: 20000,
});

api.interceptors.request.use((config) => {
  const t = tokenStore.get();
  if (t) config.headers.Authorization = `Bearer ${t}`;
  return config;
});

let onUnauthorized = () => {};
export const setUnauthorizedHandler = (fn) => { onUnauthorized = fn; };

api.interceptors.response.use(
  (res) => res,
  (err) => {
    const status = err.response?.status;
    if (status === 401 && !err.config?.url?.includes('/auth/login')) onUnauthorized();
    const body = err.response?.data || {};
    const e = new Error(body.message || (err.code === 'ECONNABORTED' ? 'Request timed out' : !err.response ? 'Network error — check your connection' : 'Something went wrong'));
    e.status = status;
    e.errors = body.errors || {};
    e.offline = !err.response;
    return Promise.reject(e);
  }
);

/** Unwraps {success, data, meta} */
export const get = (url, params) => api.get(url, { params }).then((r) => r.data);
export const post = (url, data, config) => api.post(url, data, config).then((r) => r.data);
export const put = (url, data) => api.put(url, data).then((r) => r.data);
export const del = (url) => api.delete(url).then((r) => r.data);

/** Build FormData, skipping null/undefined; arrays become key[]. */
export const toForm = (obj) => {
  const fd = new FormData();
  Object.entries(obj).forEach(([k, v]) => {
    if (v === null || v === undefined || v === '') return;
    if (Array.isArray(v)) v.forEach((x) => fd.append(`${k}[]`, x));
    else if (typeof v === 'boolean') fd.append(k, v ? '1' : '0');
    else fd.append(k, v);
  });
  return fd;
};

/** Fetch a protected file and open it in a new tab. */
export const openFile = async (url) => {
  const res = await api.get(url, { responseType: 'blob' });
  const blobUrl = URL.createObjectURL(res.data);
  window.open(blobUrl, '_blank', 'noopener');
  setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
};

export const downloadFile = async (url, params, filename) => {
  const res = await api.get(url, { params, responseType: 'blob' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(res.data);
  a.download = filename;
  a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 10000);
};

export default api;
