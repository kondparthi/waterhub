import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { get, post, setUnauthorizedHandler, tokenStore } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);

  const logoutLocal = useCallback(() => { tokenStore.set(null); setUser(null); }, []);

  const refresh = useCallback(async () => {
    if (!tokenStore.get()) { setUser(null); return null; }
    try { const r = await get('/auth/me'); setUser(r.data); return r.data; } catch { logoutLocal(); return null; }
  }, [logoutLocal]);

  useEffect(() => {
    setUnauthorizedHandler(logoutLocal);
    Promise.all([refresh(), get('/meta').then((r) => setMeta(r.data)).catch(() => {})]).finally(() => setLoading(false));
  }, [refresh, logoutLocal]);

  const login = async (login, password, role) => {
    const r = await post('/auth/login', { login, password, role });
    tokenStore.set(r.data.token);
    setUser(r.data.user);
    return r.data.user;
  };

  const register = async (kind, payload) => {
    const r = await post(`/auth/register/${kind}`, payload);
    tokenStore.set(r.data.token);
    setUser(r.data.user);
    return r;
  };

  const logout = async () => { try { await post('/auth/logout'); } catch { /* ignore */ } logoutLocal(); };

  return (
    <AuthContext.Provider value={{ user, meta, loading, login, register, logout, refresh, setUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
export const homeFor = (role) => ({ admin: '/admin', tanker: '/tanker', customer: '/customer' }[role] || '/');
