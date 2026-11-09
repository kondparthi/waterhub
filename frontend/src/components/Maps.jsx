import { useEffect, useMemo, useState } from 'react';
import L from 'leaflet';
import { MapContainer, Marker, Polyline, Popup, TileLayer, useMap, useMapEvents } from 'react-leaflet';

const TILE = import.meta.env.VITE_MAP_TILE_URL || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTR = import.meta.env.VITE_MAP_ATTRIBUTION || '&copy; OpenStreetMap contributors';
export const DEFAULT_CENTER = (import.meta.env.VITE_MAP_CENTER || '17.44,78.38').split(',').map(Number);

const divIcon = (html, className, size = [30, 30]) => L.divIcon({ html, className, iconSize: size, iconAnchor: [size[0] / 2, size[1]] });
export const icons = {
  drop: divIcon('<i class="bi bi-geo-alt-fill"></i>', 'drop-icon', [30, 30]),
  source: divIcon('<i class="bi bi-droplet-fill"></i>', 'source-icon', [22, 22]),
  tanker: (stale) => L.divIcon({ html: '<i class="bi bi-truck"></i>', className: `tanker-icon${stale ? ' stale' : ''}`, iconSize: [34, 34], iconAnchor: [17, 17] }),
};

const Tiles = () => <TileLayer url={TILE} attribution={ATTR} />;

function FitBounds({ points }) {
  const map = useMap();
  const key = JSON.stringify(points);
  useEffect(() => {
    const valid = points.filter((p) => p && p[0] != null && p[1] != null);
    try {
      if (valid.length === 1) map.setView(valid[0], Math.max(map.getZoom(), 14), { animate: false });
      else if (valid.length > 1) map.fitBounds(valid, { padding: [40, 40], maxZoom: 15, animate: false });
    } catch { /* map not ready / unmounting */ }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);
  return null;
}

function ClickToSet({ onPick }) {
  useMapEvents({ click: (e) => onPick(e.latlng.lat, e.latlng.lng) });
  return null;
}

/** Location pin picker: tap map or drag pin; "Use my location"; address search via OpenStreetMap Nominatim. */
export function MapPicker({ lat, lng, onChange, height = 300 }) {
  const [q, setQ] = useState('');
  const [searching, setSearching] = useState(false);
  const [msg, setMsg] = useState(null);
  const pos = lat != null && lng != null && lat !== '' ? [Number(lat), Number(lng)] : null;

  const locate = () => {
    if (!navigator.geolocation) return setMsg('Location not supported on this device.');
    setMsg('Finding your location…');
    navigator.geolocation.getCurrentPosition(
      (p) => { onChange(+p.coords.latitude.toFixed(7), +p.coords.longitude.toFixed(7)); setMsg(null); },
      (e) => setMsg(e.code === 1 ? 'Location permission denied — tap the map to set the pin.' : 'Could not get location.'),
      { enableHighAccuracy: true, timeout: 15000 }
    );
  };
  const search = async (e) => {
    e.preventDefault();
    if (!q.trim()) return;
    setSearching(true); setMsg(null);
    try {
      const r = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=in&q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
      const j = await r.json();
      if (j[0]) onChange(+(+j[0].lat).toFixed(7), +(+j[0].lon).toFixed(7)); else setMsg('No match — try a nearby landmark, or tap the map.');
    } catch { setMsg('Search unavailable — tap the map to set the pin.'); } finally { setSearching(false); }
  };

  return (
    <div>
      <div className="d-flex gap-2 mb-2">
        <form className="input-group" onSubmit={search}>
          <input className="form-control" placeholder="Search area or landmark" value={q} onChange={(e) => setQ(e.target.value)} />
          <button className="btn btn-outline-secondary" disabled={searching} type="submit"><i className="bi bi-search" /></button>
        </form>
        <button type="button" className="btn btn-outline-primary text-nowrap" onClick={locate}><i className="bi bi-crosshair me-1" />My location</button>
      </div>
      <div className="map" style={{ height }}>
        <MapContainer center={pos || DEFAULT_CENTER} zoom={pos ? 15 : 12} style={{ height: '100%' }} scrollWheelZoom={false} zoomAnimation={false}>
          <Tiles />
          <ClickToSet onPick={(a, b) => onChange(+a.toFixed(7), +b.toFixed(7))} />
          {pos && <Marker position={pos} icon={icons.drop} draggable eventHandlers={{ dragend: (e) => { const p = e.target.getLatLng(); onChange(+p.lat.toFixed(7), +p.lng.toFixed(7)); } }} />}
          {pos && <FitBounds points={[pos]} />}
        </MapContainer>
      </div>
      <div className="form-text">{msg || (pos ? `Pin: ${pos[0].toFixed(5)}, ${pos[1].toFixed(5)} — drag the pin to correct it.` : 'Tap the map to drop a pin at the delivery point.')}</div>
    </div>
  );
}

/** Live tracking view for one booking. */
export function TrackingMap({ data, tall }) {
  const dest = data?.destination ? [data.destination.latitude, data.destination.longitude] : null;
  const src = data?.source ? [data.source.latitude, data.source.longitude] : null;
  const loc = data?.location ? [data.location.latitude, data.location.longitude] : null;
  const points = useMemo(() => [dest, loc, src].filter(Boolean), [JSON.stringify([dest, loc, src])]); // eslint-disable-line
  return (
    <div className={`map${tall ? ' tall' : ''}`}>
      <MapContainer center={dest || DEFAULT_CENTER} zoom={13} style={{ height: '100%' }}>
        <Tiles />
        {dest && <Marker position={dest} icon={icons.drop}><Popup>Delivery location<br />{data.destination.address}</Popup></Marker>}
        {src && <Marker position={src} icon={icons.source}><Popup>Source: {data.source.name}</Popup></Marker>}
        {data?.trail?.length > 1 && <Polyline positions={data.trail} pathOptions={{ color: '#1fb5c9', weight: 4, opacity: .8 }} />}
        {loc && <Marker position={loc} icon={icons.tanker(data.location.stale)}><Popup>{data.assignment?.vehicle}<br />Updated {data.location.age_seconds}s ago</Popup></Marker>}
        <FitBounds points={points} />
      </MapContainer>
    </div>
  );
}

/** Generic multi-marker map (admin live map, sources). */
export function MultiMap({ markers = [], tall, height }) {
  const points = markers.map((m) => m.position).filter(Boolean);
  return (
    <div className={`map${tall ? ' tall' : ''}`} style={height ? { height } : undefined}>
      <MapContainer center={DEFAULT_CENTER} zoom={12} style={{ height: '100%' }}>
        <Tiles />
        {markers.map((m) => m.position && (
          <Marker key={m.key} position={m.position} icon={m.icon || icons.drop}>{m.popup && <Popup>{m.popup}</Popup>}</Marker>
        ))}
        <FitBounds points={points} />
      </MapContainer>
    </div>
  );
}
