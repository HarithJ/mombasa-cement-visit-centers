'use strict';
const destinations = JSON.parse(document.querySelector('#destination-config').textContent);
const serverState = JSON.parse(document.querySelector('#booking-state').textContent);
const dialog = document.querySelector('#booking-dialog');
const form = document.querySelector('#booking-form');
const locationField = form.elements.location;
const overnight = form.elements.overnight;
const dateField = form.elements.visitDate;
const submit = form.querySelector('[type="submit"]');
let opener;
let attempt = 0;
let busy = false;
const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Nairobi', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const validDate = value => /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
const dateLabel = value => new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(value));
const timeLabel = value => { const [hours, minutes] = value.split(':'); const hour = Number(hours); return `${hour % 12 || 12}:${minutes} ${hour < 12 ? 'am' : 'pm'} (Africa/Nairobi)`; };
function clearErrors() {
  form.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
  form.querySelectorAll('.field-error').forEach(error => { error.textContent = ''; });
  const summary = document.querySelector('#error-summary');
  summary.hidden = true; summary.textContent = '';
}
function setBusy(value) {
  busy = value; submit.disabled = value;
  form.setAttribute('aria-busy', String(value));
  document.querySelector('#submit-label').textContent = value ? 'Saving your visit…' : 'Book my visit';
  form.querySelector('.spinner').hidden = !value;
  form.querySelector('.submit-arrow').hidden = value;
  // Reset disabled controls when the dialog returns to its editable state.
  [...form.elements].forEach(input => { if (input !== submit) input.disabled = value; });
  if (!value) syncOvernight();
  overnight.disabled = value;
}
function syncOvernight() {
  form.elements.arrivalDate.readOnly = true;
  const isGalana = locationField.value === 'galana';
  if (!isGalana) overnight.checked = false;
  document.querySelector('#overnight-option').hidden = !isGalana;
  const active = isGalana && overnight.checked;
  document.querySelector('#overnight-fields').hidden = !active;
  ['arrivalDate', 'departureDate', 'overnightGuests'].forEach(name => {
    form.elements[name].disabled = !active || busy;
    form.elements[name].required = active;
  });
  if (!active) { form.elements.arrivalDate.value = ''; form.elements.departureDate.value = ''; form.elements.overnightGuests.value = '1'; }
  else form.elements.arrivalDate.value = dateField.value;
}
function syncDestination() {
  const destination = destinations[locationField.value];
  if (!destination) return;
  const slot = form.elements.timeSlot;
  slot.replaceChildren(new Option('Select a time', ''));
  destination.slots.forEach(time => slot.add(new Option(timeLabel(time), time)));
  document.querySelector('#aside-destination').textContent = destination.name;
  document.querySelector('#booking-image').src = document.querySelector(`[data-destination="${locationField.value}"] .photo-primary`).getAttribute('src');
  syncOvernight();
}
function showForm() {
  document.querySelector('#form-view').hidden = false;
  document.querySelector('#confirmation-view').hidden = true;
  dialog.setAttribute('aria-labelledby', 'booking-title');
}
function openBooking(id, button) {
  opener = button; attempt++; form.reset();  clearErrors(); setBusy(false); showForm();
  locationField.value = id;
  dateField.min = today(); form.elements.departureDate.min = today();
  syncDestination(); dialog.showModal(); document.body.style.overflow = 'hidden';
  document.querySelector('.dialog-shell').scrollTop = 0;
  form.elements.fullName.focus({ preventScroll: true });
}
document.querySelectorAll('[data-book]').forEach(button => button.addEventListener('click', event => { event.preventDefault(); openBooking(button.dataset.book, button); }));
document.querySelectorAll('.close-button,.confirmation-close').forEach(button => button.addEventListener('click', event => { event.preventDefault(); dialog.close(); }));
dialog.addEventListener('click', event => { if (event.target === dialog) { const box = dialog.getBoundingClientRect(); if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close(); } });
dialog.addEventListener('close', () => { attempt++; setBusy(false); form.reset(); clearErrors(); document.querySelector('#confirmation-details').replaceChildren(); document.body.style.overflow = ''; opener?.focus({ preventScroll: true }); });
locationField.addEventListener('change', () => { clearErrors(); syncDestination(); });
overnight.addEventListener('change', () => { clearErrors(); syncOvernight(); });
dateField.addEventListener('change', () => { syncOvernight(); form.elements.departureDate.min = dateField.value || today(); });
function validate() {
  clearErrors(); const errors = [];
  const error = (name, message) => { const input = form.elements[name]; input.setAttribute('aria-invalid', 'true'); document.querySelector(`#${input.id}-error`).textContent = message; errors.push(input); };
  const name = form.elements.fullName.value.trim();
  if (!name) error('fullName', 'Please enter your full name.');
  const phone = form.elements.phone.value.trim(); const digits = phone.replace(/[\s()+-]/g, '');
  if (!/^[+\d][\d\s()+-]*$/.test(phone) || !/^\d{7,15}$/.test(digits)) error('phone', 'Enter a valid phone number (7–15 digits).');
  if (!destinations[locationField.value]) error('location', 'Choose one of the three destinations.');
  if (!validDate(dateField.value)) error('visitDate', 'Please choose a visit date.');
  else if (dateField.value < today()) error('visitDate', 'Choose today or a future date.');
  if (!destinations[locationField.value]?.slots.includes(form.elements.timeSlot.value)) error('timeSlot', 'Please select an available time.');
  const attendees = Number(form.elements.attendees.value);
  if (!Number.isSafeInteger(attendees) || attendees < 1) error('attendees', 'Enter a whole number of at least 1.');
  if (locationField.value === 'galana' && overnight.checked) {
    if (!validDate(form.elements.arrivalDate.value) || form.elements.arrivalDate.value !== dateField.value) error('arrivalDate', 'Choose a visit date to set your arrival.');
    const departure = form.elements.departureDate.value;
    if (!validDate(departure)) error('departureDate', 'Please choose a departure date.');
    else if (departure <= dateField.value) error('departureDate', 'Departure must be after arrival.');
    const guests = Number(form.elements.overnightGuests.value);
    if (!Number.isSafeInteger(guests) || guests < 1) error('overnightGuests', 'Enter a whole number of at least 1.');
    else if (guests > attendees) error('overnightGuests', 'Guests cannot exceed total attendees.');
  }
  if (errors.length) { const summary = document.querySelector('#error-summary'); summary.hidden = false; summary.textContent = `Please check ${errors.length === 1 ? 'the highlighted field' : `the ${errors.length} highlighted fields`} below.`; errors[0].focus(); }
  return !errors.length;
}
function showConfirmation(data) {
  const rows = [ ['Name', data.fullName.trim()], ['Destination', destinations[data.location].name], ['Visit date', dateLabel(data.visitDate)], ['Time', timeLabel(data.timeSlot)], ['Attendees', data.attendees], ['Phone', `••• ••• ${data.phone.replace(/\D/g, '').slice(-3)}`] ];
  if (data.overnight === 'on' && data.location === 'galana') rows.push(['Overnight stay', `${dateLabel(data.arrivalDate)} – ${dateLabel(data.departureDate)}`], ['Staying guests', data.overnightGuests]);
  const list = document.querySelector('#confirmation-details'); list.replaceChildren();
  rows.forEach(([label, value]) => { const row = document.createElement('div'); const dt = document.createElement('dt'); const dd = document.createElement('dd'); dt.textContent = label; dd.textContent = value; row.append(dt, dd); list.append(row); });
  document.querySelector('#form-view').hidden = true; document.querySelector('#confirmation-view').hidden = false;
  dialog.setAttribute('aria-labelledby', 'confirmation-title'); document.querySelector('.dialog-shell').scrollTop = 0;
  document.querySelector('#confirmation-title').focus({ preventScroll: true });
}
form.addEventListener('submit', event => {
  if (busy || !validate()) { event.preventDefault(); return; }
  busy = true; submit.disabled = true;
  document.querySelector('#submit-label').textContent = 'Saving your visit…';
});
document.querySelector('#edit-preview').addEventListener('click', () => { window.location.href = '/'; });
if (serverState.openForm || serverState.confirmation) {
  dialog.removeAttribute('open');
  locationField.value = serverState.input.location || serverState.confirmation?.destination || 'sahajanand';
  overnight.checked = serverState.input.overnight === 'on';
  syncDestination();
  for (const [key, value] of Object.entries(serverState.input)) if (form.elements[key] && key !== 'overnight') form.elements[key].value = value;
  // Arrival is derived, so stale/tampered values never trap correction in a readonly field.
  syncOvernight();
  dialog.showModal(); document.body.style.overflow = 'hidden';
  if (serverState.confirmation) {
    const booking = serverState.confirmation;
    showConfirmation({ fullName: booking.full_name, location: booking.destination, visitDate: booking.visit_date, timeSlot: booking.booked_time, attendees: String(booking.attendees), phone: booking.phone, overnight: booking.overnight ? 'on' : '', arrivalDate: booking.arrival_date, departureDate: booking.departure_date, overnightGuests: String(booking.overnight_guests) });
  } else {
    const summary = document.querySelector('#error-summary'); summary.hidden = false;
    summary.textContent = Object.values(serverState.errors).join(' ');
    for (const [key, message] of Object.entries(serverState.errors)) {
      const field = form.elements[key];
      if (field) { field.setAttribute('aria-invalid', 'true'); const label = document.getElementById(field.id + '-error'); if (label) label.textContent = message; }
    }
    summary.focus();
  }
}
document.querySelectorAll('.destination-photo').forEach(photo => {
  const images = JSON.parse(photo.dataset.images);
  const alts = JSON.parse(photo.dataset.alts);
  const layers = [photo.querySelector('.photo-primary'), photo.querySelector('.photo-secondary')];
  const button = photo.querySelector('.photo-toggle');
  const name = destinations[photo.closest('[data-destination]').dataset.destination].name;
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  let index = 0, layer = 0, timer = null, hovered = false;
  photo.dataset.activeLayer = '0';
  // Warm the next image without downloading every photograph on initial page load.
  const preloadNext = () => { const image = new Image(); image.src = images[(index + 1) % images.length]; };
  const progress = photo.querySelector('.photo-progress circle');
  let progressAnimation = null;
  const fillRing = () => {
    progressAnimation?.cancel();
    if (progress && timer) progressAnimation = progress.animate([{ strokeDashoffset: '100' }, { strokeDashoffset: '0' }], { duration: 2500, easing: 'linear', fill: 'forwards' });
  };
  const stop = () => { clearInterval(timer); timer = null; progressAnimation?.cancel(); };
  const next = () => {
    index = (index + 1) % images.length;
    layer = 1 - layer;
    layers[layer].src = images[index];
    layers[layer].alt = alts[index] || (photo.classList.contains('placeholder') ? 'Illustrated placeholder, not a destination photograph' : `${name} visit photograph ${index + 1}`);
    photo.dataset.activeLayer = String(layer);
    photo.dataset.photoIndex = String(index);
    button.setAttribute('aria-label', `Next photo of ${name} (${index + 1} of ${images.length})`);
    button.setAttribute('aria-pressed', String(index !== 0));
    preloadNext();
    fillRing();
  };
  const start = () => {
    stop();
    if (!reducedMotion.matches && !document.hidden && !dialog.open && images.length > 2) timer = setInterval(next, 2500);
    fillRing();
  };
  button.setAttribute('aria-label', `Next photo of ${name} (1 of ${images.length})`);
  button.addEventListener('click', () => { next(); if (hovered) start(); });
  photo.addEventListener('pointerenter', event => {
    if (event.pointerType !== 'mouse') return;
    hovered = true;
    if (!reducedMotion.matches) { next(); start(); }
  });
  photo.addEventListener('pointerleave', () => { hovered = false; stop(); });
  photo.addEventListener('focus', () => { if (!hovered && !reducedMotion.matches) next(); });
  document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); else if (hovered) start(); });
  reducedMotion.addEventListener('change', () => { stop(); if (hovered) start(); });
  const observer = new MutationObserver(() => { if (dialog.open) stop(); else if (hovered) start(); });
  observer.observe(dialog, { attributes: true, attributeFilter: ['open'] });
  preloadNext();
});
if ('IntersectionObserver' in window) { document.body.classList.add('js-ready'); const observer = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); } }), { threshold: .08 }); document.querySelectorAll('.reveal').forEach(section => observer.observe(section)); }
