const TZ = 'Asia/Kolkata';

export const money = (v) => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export const litres = (v) => (v || v === 0 ? Number(v).toLocaleString('en-IN') + ' L' : '—');
export const date = (v) => (v ? new Date(v.length === 10 ? v + 'T00:00:00' : v).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', ...(v.length === 10 ? {} : { timeZone: TZ }) }) : '—');
export const dateTime = (v) => (v ? new Date(v).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: TZ }) : '—');
export const ago = (seconds) => {
  if (seconds === null || seconds === undefined) return 'never';
  if (seconds < 60) return `${seconds}s ago`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
  return `${Math.floor(seconds / 3600)} h ago`;
};
export const label = (s) => (s ? String(s).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : '—');
export const today = () => new Date().toLocaleDateString('en-CA', { timeZone: TZ });
export const tomorrow = () => { const d = new Date(); d.setDate(d.getDate() + 1); return d.toLocaleDateString('en-CA', { timeZone: TZ }); };

/** Status → [label, bootstrap tone, icon]. Labels are always shown as text, not only colour. */
export const STATUS = {
  new: ['New', 'secondary', 'inbox'],
  under_review: ['Under review', 'info', 'search'],
  quoted: ['Quoted', 'primary', 'receipt'],
  confirmed: ['Confirmed', 'primary', 'check2-circle'],
  assigned: ['Tanker assigned', 'warning', 'truck'],
  accepted: ['Driver accepted', 'warning', 'hand-thumbs-up'],
  in_transit: ['On the way', 'success', 'geo-alt'],
  arrived: ['Arrived', 'success', 'pin-map'],
  delivered: ['Delivered', 'success', 'droplet-half'],
  completed: ['Completed', 'dark', 'patch-check'],
  rejected: ['Rejected', 'danger', 'x-octagon'],
  cancelled: ['Cancelled', 'danger', 'x-circle'],
  contacted: ['Contacted', 'info', 'telephone'],
  quotation_sent: ['Quotation sent', 'primary', 'receipt'],
  converted: ['Converted', 'success', 'arrow-right-circle'],
  closed: ['Closed', 'dark', 'archive'],
  pending_review: ['Pending review', 'warning', 'hourglass-split'],
  approved: ['Approved', 'success', 'check-circle'],
  active: ['Active', 'success', 'check-circle'],
  suspended: ['Suspended', 'danger', 'slash-circle'],
  inactive: ['Inactive', 'secondary', 'pause-circle'],
  maintenance: ['Maintenance', 'warning', 'tools'],
  correction_requested: ['Correction requested', 'warning', 'pencil-square'],
  pending: ['Pending', 'warning', 'hourglass-split'],
  superseded: ['Superseded', 'secondary', 'layers'],
  withdrawn: ['Withdrawn', 'secondary', 'arrow-counterclockwise'],
  available: ['Available', 'success', 'circle-fill'],
  busy: ['Busy', 'warning', 'circle-half'],
  unavailable: ['Unavailable', 'secondary', 'circle'],
  unpaid: ['Unpaid', 'danger', 'wallet2'],
  partial: ['Part paid', 'warning', 'wallet2'],
  paid: ['Paid', 'success', 'wallet-fill'],
  refunded: ['Refunded', 'secondary', 'arrow-return-left'],
  received: ['Received', 'success', 'check'],
  void: ['Void', 'secondary', 'slash'],
};
