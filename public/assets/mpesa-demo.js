document.querySelectorAll('[data-mpesa-demo]').forEach(demo => {
  const amount = demo.querySelector('input');
  const status = demo.querySelector('[data-demo-status]');
  const reset = () => { status.textContent = ''; };
  amount.addEventListener('input', reset);
  demo.querySelectorAll('[data-demo-amount]').forEach(button => {
    button.addEventListener('click', () => { amount.value = button.dataset.demoAmount; reset(); });
  });
  demo.querySelector('[data-demo-confirm]').addEventListener('click', () => {
    if (!amount.value || !amount.reportValidity()) return;
    status.textContent = `KES ${Number(amount.value).toLocaleString('en-KE')} selected.`;
  });
});
