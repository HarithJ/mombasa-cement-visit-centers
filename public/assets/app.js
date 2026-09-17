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
  document.querySelector('#destination-map').href = locationField.selectedOptions[0].dataset.mapUrl;
  document.querySelector('#map-destination-name').textContent = locationField.selectedOptions[0].textContent;
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
const visiblePhotosReady = Promise.all([...document.querySelectorAll('.photo-primary')].map(image => image.decode().catch(() => {})));
let photoWarmup = visiblePhotosReady;
const queuePhotoWarmup = load => {
  photoWarmup = photoWarmup.then(() => new Promise(resolve => {
    if ('requestIdleCallback' in window) requestIdleCallback(resolve, { timeout: 1500 });
    else setTimeout(resolve, 100);
  })).then(load).catch(() => {});
};
const photoLightbox = document.querySelector('#photo-lightbox');
const galleryImage = document.querySelector('#gallery-image');
const galleryThumbnails = document.querySelector('#gallery-thumbnails');
let openedGallery = null, openedPhoto = 0, galleryRequest = 0;
const showGalleryPhoto = async position => {
  openedPhoto = (position + openedGallery.images.length) % openedGallery.images.length;
  const request = ++galleryRequest;
  const selected = openedPhoto;
  const collection = openedGallery;
  galleryImage.setAttribute('aria-busy', 'true');
  [...galleryThumbnails.children].forEach((thumbnail, index) => thumbnail.setAttribute('aria-pressed', String(index === openedPhoto)));
  galleryThumbnails.children[selected]?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'instant' });
  try {
    const incoming = new Image();
    incoming.src = collection.images[selected];
    await incoming.decode();
    if (request !== galleryRequest) return;
    galleryImage.src = incoming.src;
    galleryImage.alt = collection.alts[selected] || `${collection.name} photograph ${selected + 1}`;
    document.querySelector('#gallery-count').textContent = `Photograph ${String(selected + 1).padStart(2, '0')} / ${String(collection.images.length).padStart(2, '0')}`;
    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) galleryImage.animate([{opacity: .4}, {opacity: 1}], {duration: 350, easing: 'ease-out'});
  } catch {
    if (request === galleryRequest) document.querySelector('#gallery-count').textContent = 'This photograph could not load. Please choose another.';
  } finally {
    if (request === galleryRequest) galleryImage.setAttribute('aria-busy', 'false');
  }
};
const openPhotoGallery = (photo, images, variants, alts, name) => {
  openedGallery = { images, alts, name };
  galleryImage.removeAttribute('src');
  galleryImage.alt = '';
  document.querySelector('#gallery-count').textContent = 'Loading photograph…';
  document.querySelector('#gallery-title').textContent = name;
  galleryThumbnails.replaceChildren();
  images.forEach((source, index) => {
    const thumbnail = document.createElement('button');
    thumbnail.type = 'button';
    thumbnail.setAttribute('aria-label', `View photograph ${index + 1}`);
    const image = document.createElement('img');
    image.src = variants[index]?.[360] || source;
    image.alt = '';
    image.loading = 'lazy';
    thumbnail.append(image);
    thumbnail.addEventListener('click', () => showGalleryPhoto(index));
    galleryThumbnails.append(thumbnail);
  });
  showGalleryPhoto(Number(photo.dataset.photoIndex || 0));
  photoLightbox.showModal();
  stopPhotoCycle();
};
document.querySelector('#gallery-close').addEventListener('click', () => photoLightbox.close());
document.querySelector('#gallery-previous').addEventListener('click', () => showGalleryPhoto(openedPhoto - 1));
document.querySelector('#gallery-next').addEventListener('click', () => showGalleryPhoto(openedPhoto + 1));
photoLightbox.addEventListener('keydown', event => {
  if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
    event.preventDefault();
    showGalleryPhoto(openedPhoto + (event.key === 'ArrowRight' ? 1 : -1));
  }
});
photoLightbox.addEventListener('close', () => startPhotoCycle());
const galleries = [];
const photoCycleDelay = 5000;
const reducedPhotoMotion = matchMedia('(prefers-reduced-motion: reduce)');
let photoCycleTimer = null, photosChanging = false;
const canCyclePhotos = () => !document.hidden && !dialog.open && !photoLightbox.open && !reducedPhotoMotion.matches;
const stopPhotoCycle = () => {
  clearTimeout(photoCycleTimer);
  photoCycleTimer = null;
  galleries.forEach(gallery => { gallery.stopRing(); gallery.pauseZoom(); });
};
const startPhotoCycle = () => {
  stopPhotoCycle();
  if (!canCyclePhotos() || photosChanging) return;
  photoCycleTimer = setTimeout(() => advancePhotos(galleries, true), photoCycleDelay);
  galleries.forEach(gallery => { gallery.fillRing(); gallery.startZoom(); });
};
const advancePhotos = async (selection, automatic = false) => {
  if (photosChanging) return;
  photosChanging = true;
  stopPhotoCycle();
  try {
    // Decode every incoming layer before committing any of the crossfades.
    const prepared = await Promise.allSettled(selection.map(gallery => gallery.prepareNext()));
    if (prepared.some(result => result.status === 'rejected')) return;
    const commits = prepared.map(result => result.value);
    if (!automatic || canCyclePhotos()) commits.forEach(commit => commit());
  } catch {
    // Keep all current photographs visible if any incoming download fails.
  } finally {
    photosChanging = false;
    startPhotoCycle();
  }
};
document.querySelectorAll('.destination-photo').forEach(photo => {
  const images = JSON.parse(photo.dataset.images);
  const variants = JSON.parse(photo.dataset.variants || '[]');
  const alts = JSON.parse(photo.dataset.alts);
  const layers = [photo.querySelector('.photo-primary'), photo.querySelector('.photo-secondary')];
  const button = photo.querySelector('.photo-toggle');
  const name = destinations[photo.closest('[data-destination]').dataset.destination].name;
  let index = 0, layer = 0;
  const zoomAnimations = [null, null];
  const startZoom = () => {
    if (reducedPhotoMotion.matches) return;
    if (!zoomAnimations[layer]) {
      zoomAnimations[layer] = layers[layer].animate(
        [{ transform: 'scale(1)' }, { transform: 'scale(1.04)' }],
        { duration: photoCycleDelay, easing: 'linear', fill: 'forwards' }
      );
    } else if (zoomAnimations[layer].playState === 'paused') {
      zoomAnimations[layer].play();
    }
  };
  const pauseZoom = () => {
    zoomAnimations.forEach((animation, position) => {
      if (reducedPhotoMotion.matches) {
        animation?.cancel();
        zoomAnimations[position] = null;
      } else if (animation?.playState === 'running') animation.pause();
    });
  };
  photo.dataset.activeLayer = '0';
  const applySource = (image, position) => {
    const sources = variants[position] || {};
    image.sizes = layers[0].sizes;
    image.srcset = Object.entries(sources).map(([width, path]) => `${path} ${width}w`).join(', ');
    image.src = sources[720] || images[position];
  };
  const ready = new Map();
  const loadPhoto = position => {
    if (!ready.has(position)) {
      const image = new Image();
      image.decoding = 'async';
      applySource(image, position);
      ready.set(position, image.decode().catch(error => { ready.delete(position); throw error; }));
    }
    return ready.get(position);
  };
  // Warm only one photo ahead, after the visible photos have finished loading.
  const preloadNext = () => {
    const position = (index + 1) % images.length;
    queuePhotoWarmup(() => loadPhoto(position));
  };
  const progress = photo.querySelector('.photo-progress circle');
  let progressAnimation = null;
  const fillRing = () => {
    progressAnimation?.cancel();
    if (progress) progressAnimation = progress.animate([{ strokeDashoffset: '100' }, { strokeDashoffset: '0' }], { duration: photoCycleDelay, easing: 'linear', fill: 'forwards' });
  };
  const prepareNext = async () => {
    const position = (index + 1) % images.length;
    const nextLayer = 1 - layer;
    await loadPhoto(position);
    applySource(layers[nextLayer], position);
    await layers[nextLayer].decode();
    return () => {
      // Reset only the incoming layer; preserve the outgoing zoom during its fade.
      zoomAnimations[nextLayer]?.cancel();
      zoomAnimations[nextLayer] = null;
      index = position;
      layer = nextLayer;
      layers[layer].alt = alts[index] || (photo.classList.contains('placeholder') ? 'Illustrated placeholder, not a destination photograph' : `${name} visit photograph ${index + 1}`);
      photo.dataset.activeLayer = String(layer);
      photo.dataset.photoIndex = String(index);
      preloadNext();
    };
  };
  const gallery = { prepareNext, fillRing, startZoom, pauseZoom, stopRing: () => progressAnimation?.cancel() };
  galleries.push(gallery);
  button.setAttribute('aria-label', `Open ${name} photo gallery`);
  button.setAttribute('aria-haspopup', 'dialog');
  button.removeAttribute('aria-pressed');
  button.addEventListener('click', () => openPhotoGallery(photo, images, variants, alts, name));
  photo.setAttribute('aria-haspopup', 'dialog');
  photo.setAttribute('aria-label', `${name} photographs. Click or press Enter to open the gallery.`);
  photo.addEventListener('click', event => {
    if (!event.target.closest('button')) openPhotoGallery(photo, images, variants, alts, name);
  });
  photo.addEventListener('keydown', event => {
    if (event.target === photo && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      openPhotoGallery(photo, images, variants, alts, name);
    }
  });
  preloadNext();
});
document.addEventListener('visibilitychange', startPhotoCycle);
reducedPhotoMotion.addEventListener('change', startPhotoCycle);
new MutationObserver(startPhotoCycle).observe(dialog, { attributes: true, attributeFilter: ['open'] });
visiblePhotosReady.then(startPhotoCycle);
if ('IntersectionObserver' in window) { document.body.classList.add('js-ready'); const observer = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); } }), { threshold: .08 }); document.querySelectorAll('.reveal').forEach(section => observer.observe(section)); }
