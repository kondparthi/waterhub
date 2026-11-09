import { useCallback, useEffect, useRef, useState } from 'react';
import { get } from '../api/client';

/** Load a GET endpoint with loading/error state. Re-runs when url/params change. */
export function useApi(url, params, { poll } = {}) {
  const [data, setData] = useState(null);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(!!url);
  const [error, setError] = useState(null);
  const key = JSON.stringify(params || {});
  const alive = useRef(true);

  const load = useCallback(async (quiet = false) => {
    if (!url) return;
    if (!quiet) setLoading(true);
    try {
      const r = await get(url, params);
      if (!alive.current) return;
      setData(r.data); setMeta(r.meta || null); setError(null);
    } catch (e) { if (alive.current) setError(e); } finally { if (alive.current) setLoading(false); }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [url, key]);

  useEffect(() => { alive.current = true; load(); return () => { alive.current = false; }; }, [load]);
  useEffect(() => {
    if (!poll) return undefined;
    const t = setInterval(() => { if (document.visibilityState === 'visible') load(true); }, poll * 1000);
    return () => clearInterval(t);
  }, [poll, load]);

  return { data, meta, loading, error, reload: load, setData };
}
