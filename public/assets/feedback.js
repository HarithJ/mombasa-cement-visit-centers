const form = document.querySelector('form');
if (form) {
  const rating = document.querySelector('#rating-field');
  const syncAttendance = () => {
    const absent = form.elements.attendance.value === 'not_attended';
    rating.hidden = absent;
    rating.querySelectorAll('input').forEach(input => { input.disabled = absent; if (absent) input.checked = false; });
  };
  form.addEventListener('change', syncAttendance);
  syncAttendance();
  const summary = document.querySelector('.error-summary');
  summary?.focus();
  if (document.body.hasAttribute('data-feedback-preview')) {
    form.addEventListener('submit', event => {
      event.preventDefault();
      document.querySelectorAll('.field-error').forEach(node => node.textContent = '');
      const errors = [];
      const error = (name, message) => { document.querySelector(`#${name}-error`).textContent = message; errors.push(name); };
      if (!form.elements.attendance.value) error('attendance', 'Please tell us whether you attended.');
      if (form.elements.attendance.value === 'attended' && !form.elements.rating.value) error('rating', 'Choose a rating from 1 to 5.');
      for (const name of ['enjoyment', 'improvement', 'comments']) if ([...form.elements[name].value].length > 2000) error(name, 'Use up to 2,000 characters.');
      if (errors.length) { const field = form.elements[errors[0]]; (field[0] || field).focus(); return; }
      document.querySelector('#feedback-form-view').hidden = true;
      const done = document.querySelector('#feedback-preview-done'); done.hidden = false; done.focus();
    });
    form.querySelector('[type=submit]').disabled = false;
  }
}
