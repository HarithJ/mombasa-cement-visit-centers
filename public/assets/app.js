'use strict';
// The booking form, on the page. It works without this script (a plain POST to /#book); this adds checks before
// sending, a busy state, and the formatted confirmation after the redirect.
const destinations = JSON.parse(document.querySelector('#destination-config').textContent);
const serverState = JSON.parse(document.querySelector('#booking-state').textContent);
const form = document.querySelector('#booking-form');
const locationField = form.elements.location;
const overnight = form.elements.overnight;
const dateField = form.elements.visitDate;
const submit = form.querySelector('[type="submit"]');
const formView = document.querySelector('#form-view');
const confirmationView = document.querySelector('#confirmation-view');
const bookSection = document.querySelector('#book');
let busy = false;
const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Nairobi', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const validDate = value => /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
const dateLabel = value => new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(value));
const timeLabel = value => { const [hours, minutes] = value.split(':'); const hour = Number(hours); return `${hour % 12 || 12}:${minutes} ${hour < 12 ? 'am' : 'pm'}`; };

function clearErrors() {
  form.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
  form.querySelectorAll('.field-error').forEach(error => { error.textContent = ''; });
  const summary = document.querySelector('#error-summary');
  summary.hidden = true; summary.textContent = '';
}
function setBusy(value) {
  busy = value; submit.disabled = value;
  form.setAttribute('aria-busy', String(value));
  document.querySelector('#submit-label').textContent = value ? 'Saving…' : 'Book my visit';
  form.querySelector('.spinner').hidden = !value;
  form.querySelector('.submit-arrow').hidden = value;
}
function syncSchedule() {
  const destination = destinations[locationField.value];
  if (!destination) return;
  const slot = form.elements.timeSlot, chosen = slot.value;
  slot.replaceChildren(new Option('Select a time', ''));
  destination.slots.forEach(time => slot.add(new Option(timeLabel(time), time, false, time === chosen)));
  dateField.min = today();
}
function showForm() {
  formView.hidden = false; confirmationView.hidden = true;
  bookSection.setAttribute('aria-labelledby', 'booking-title');
}
function validate() {
  clearErrors(); const errors = [];
  const error = (name, message) => { const input = form.elements[name]; input.setAttribute('aria-invalid', 'true'); document.querySelector(`#${input.id}-error`).textContent = message; errors.push(input); };
  const name = form.elements.fullName.value.trim();
  if (!name) error('fullName', 'Please enter your full name.');
  const organization = form.elements.organization; organization.value = organization.value.trim();
  if (organization.value.length > 120) error('organization', 'Enter an organization name up to 120 characters.');
  const email = form.elements.email; email.value = email.value.trim();
  if (email.value && (email.value.length > 254 || !email.validity.valid || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value))) error('email', 'Enter a valid email address.');
  const phone = form.elements.phone.value.trim(); const digits = phone.replace(/[\s()+-]/g, '');
  if (!/^[+\d][\d\s()+-]*$/.test(phone) || !/^\d{7,15}$/.test(digits)) error('phone', 'Enter a valid phone number (7–15 digits).');
  if (!destinations[locationField.value]) error('location', 'Visits can only be booked at Galana Farm.');
  if (!validDate(dateField.value)) error('visitDate', 'Please choose a visit date.');
  else if (dateField.value < today()) error('visitDate', 'Choose today or a future date.');
  if (!destinations[locationField.value]?.slots.includes(form.elements.timeSlot.value)) error('timeSlot', 'Please select an available time.');
  const attendees = Number(form.elements.attendees.value);
  if (!Number.isSafeInteger(attendees) || attendees < 1) error('attendees', 'Enter a whole number of at least 1.');
  if (errors.length) { const summary = document.querySelector('#error-summary'); summary.hidden = false; summary.textContent = `Please check ${errors.length === 1 ? 'the highlighted field' : `the ${errors.length} highlighted fields`} below.`; errors[0].focus({ preventScroll: true }); (errors[0].closest(".field") || errors[0]).scrollIntoView({ block: "center" }); }
  return !errors.length;
}
function showConfirmation(data) {
  document.querySelectorAll('[data-contact-destination]').forEach(team => { team.hidden = team.dataset.contactDestination !== data.location; });
  const rows = [ ['Name', data.fullName.trim()], ['Organization', data.organization || 'Not provided'], ['Email', data.email || 'Not provided'], ['Destination', destinations[data.location].name], ['Visit date', dateLabel(data.visitDate)], ['Time', timeLabel(data.timeSlot)], ['Attendees', data.attendees], ['Transport', data.transportRequested === 'on' ? 'Requested (subject to availability)' : 'Not requested'], ['Phone', `••• ••• ${data.phone.replace(/\D/g, '').slice(-3)}`] ];
  if (data.overnight === 'on' && data.location === 'galana') rows.push(['Overnight stay', 'Requested']);
  const list = document.querySelector('#confirmation-details'); list.replaceChildren();
  rows.forEach(([label, value]) => { const row = document.createElement('div'); const dt = document.createElement('dt'); const dd = document.createElement('dd'); dt.textContent = label; dd.textContent = value; row.append(dt, dd); list.append(row); });
  formView.hidden = true; confirmationView.hidden = false;
  bookSection.setAttribute('aria-labelledby', 'confirmation-title');
  document.querySelector('#confirmation-title').focus({ preventScroll: true });
}
form.addEventListener('submit', event => {
  if (busy || !validate()) { event.preventDefault(); return; }
  setBusy(true);
});
// Coming back to the page (back button) must not leave the form stuck as "Saving…".
addEventListener('pageshow', event => { if (event.persisted) setBusy(false); });
// "Book another visit": back to an empty form, here on the page.
document.querySelector('.confirmation-close').addEventListener('click', event => {
  event.preventDefault();
  form.reset(); clearErrors(); setBusy(false); showForm(); syncSchedule();
  history.replaceState(null, '', '/#book');
  bookSection.scrollIntoView({ block: 'start' });
  form.elements.fullName.focus({ preventScroll: true });
});

syncSchedule();
if (serverState.confirmation) {
  const booking = serverState.confirmation;
  showConfirmation({ fullName: booking.full_name, organization: booking.organization || '', email: booking.email, location: booking.destination, visitDate: booking.visit_date, timeSlot: booking.booked_time, attendees: String(booking.attendees), transportRequested: booking.transport_requested ? 'on' : '', phone: booking.phone, overnight: booking.overnight ? 'on' : '' });
} else if (Object.keys(serverState.errors || {}).length) {
  // The server sent the form back with problems: mark the fields and bring the summary into view.
  const summary = document.querySelector('#error-summary'); summary.hidden = false;
  summary.textContent = Object.values(serverState.errors).join(' ');
  for (const [key, message] of Object.entries(serverState.errors)) {
    const field = form.elements[key];
    if (field) { field.setAttribute('aria-invalid', 'true'); const label = document.getElementById(field.id + '-error'); if (label) label.textContent = message; }
  }
  summary.focus({ preventScroll: true });
}
