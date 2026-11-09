import { useEffect, useRef, useState } from 'react';
import { post } from '../api/client';
import { BackgroundGeolocation, isNative } from '../native';

/**
 * Shares the driver's location with the API while `active` is true.
 * - In the Android app: native background location with a persistent notification,
 *   so updates continue when the screen is locked or another app is open.
 * - In a browser: navigator.geolocation; this pauses when the phone locks (shown in the UI).
 */
export function useGeoShare(assignmentId, active, intervalSeconds = 12) {
  const [status, setStatus] = useState('idle'); // idle | requesting | live | denied | unsupported | error
  const [lastSentAt, setLastSentAt] = useState(null);
  const [lastError, setLastError] = useState(null);
  const [background, setBackground] = useState(false);
  const latest = useRef(null);
  const lastSentMs = useRef(0);
  const sending = useRef(false);

  useEffect(() => {
    if (!active || !assignmentId) { setStatus('idle'); return undefined; }

    const send = async (p) => {
      if (!p || sending.current) return;
      sending.current = true;
      try {
        await post('/tanker/locations', {
          assignment_id: assignmentId, latitude: p.latitude, longitude: p.longitude,
          accuracy_m: p.accuracy ?? null, speed: p.speed != null ? Math.max(0, p.speed * 3.6) : null,
          heading: p.bearing ?? p.heading ?? null, recorded_at: new Date(p.time || Date.now()).toISOString(),
        });
        lastSentMs.current = Date.now();
        setLastSentAt(new Date()); setLastError(null);
      } catch (e) { setLastError(e.message); } finally { sending.current = false; }
    };

    // ---------- Android app: native background tracking ----------
    if (isNative()) {
      let watcherId = null;
      let cancelled = false;
      setStatus('requesting'); setBackground(true);
      BackgroundGeolocation.addWatcher(
        {
          backgroundTitle: 'WaterHub — delivery in progress',
          backgroundMessage: 'Sharing your tanker location with the customer until delivery.',
          requestPermissions: true,
          stale: false,
          distanceFilter: 10,
        },
        (location, error) => {
          if (error) {
            setStatus(error.code === 'NOT_AUTHORIZED' ? 'denied' : 'error');
            setLastError(error.message);
            return;
          }
          if (!location) return;
          latest.current = location;
          setStatus('live');
          // Send directly from the native callback (timers can be throttled in the background).
          if (Date.now() - lastSentMs.current >= intervalSeconds * 1000) send(location);
        }
      ).then((id) => { if (cancelled) BackgroundGeolocation.removeWatcher({ id }); else watcherId = id; })
        .catch((e) => { setStatus('error'); setLastError(e.message); });

      // Heartbeat: re-send the last fix if the tanker is standing still (distanceFilter suppresses updates).
      const heartbeat = setInterval(() => {
        if (latest.current && Date.now() - lastSentMs.current >= intervalSeconds * 1000) send({ ...latest.current, time: Date.now() });
      }, intervalSeconds * 1000);

      return () => {
        cancelled = true;
        clearInterval(heartbeat);
        if (watcherId) BackgroundGeolocation.removeWatcher({ id: watcherId });
      };
    }

    // ---------- Browser ----------
    setBackground(false);
    if (!('geolocation' in navigator)) { setStatus('unsupported'); return undefined; }
    setStatus('requesting');
    const watchId = navigator.geolocation.watchPosition(
      (pos) => {
        latest.current = { latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy, speed: pos.coords.speed, heading: pos.coords.heading, time: pos.timestamp };
        setStatus('live');
      },
      (err) => { setStatus(err.code === 1 ? 'denied' : 'error'); setLastError(err.message); },
      { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 }
    );
    const first = setTimeout(() => send(latest.current), 1500);
    const timer = setInterval(() => send(latest.current), intervalSeconds * 1000);
    return () => { clearTimeout(first); clearInterval(timer); navigator.geolocation.clearWatch(watchId); };
  }, [assignmentId, active, intervalSeconds]);

  return { status, lastSentAt, lastError, background };
}
