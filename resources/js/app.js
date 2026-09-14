// Livewire 4 ships and starts its own Alpine instance, so we must NOT import or
// start Alpine here (doing so triggers "Detected multiple instances of Alpine
// running" and breaks wire:click / x-data). Alpine is available globally as
// window.Alpine once Livewire boots.

import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
window.Chart = Chart;

const BRAND = '#0f766e'; // brand-700
const INK = '#64748b'; // ink-500

const Confirmar = Swal.mixin({
    reverseButtons: true,
    focusCancel: true,
    confirmButtonColor: BRAND,
    cancelButtonColor: INK,
    confirmButtonText: 'Sí, continuar',
    cancelButtonText: 'Cancelar',
});

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

window.Swal = Swal;

/** Toast de notificación (reemplaza los mensajes flash). */
window.toast = (icon, title) => Toast.fire({ icon: icon || 'success', title });

/** Confirmación con estética de la app. Devuelve Promise<boolean>. */
window.confirmar = (opts = {}) =>
    Confirmar.fire({
        icon: opts.icon || 'question',
        title: opts.title || '¿Confirmar acción?',
        text: opts.text || null,
        html: opts.html || null,
        showCancelButton: true,
        confirmButtonText: opts.confirmButtonText || 'Sí, continuar',
    }).then((r) => r.isConfirmed);

/** Confirmación destructiva (rojo). Devuelve Promise<boolean>. */
window.confirmarEliminar = (text, title = '¿Eliminar?') =>
    Confirmar.fire({
        icon: 'warning',
        title,
        text,
        showCancelButton: true,
        confirmButtonColor: '#e11d48', // rose-600
        confirmButtonText: 'Sí, eliminar',
    }).then((r) => r.isConfirmed);

/** Aviso bloqueante (p. ej. requisitos de cierre no cumplidos). */
window.aviso = (title, html) => Swal.fire({ icon: 'error', title, html, confirmButtonColor: BRAND });

// --- Interceptores declarativos: <form data-confirm="..."> y <a data-confirm="..."> ---
document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-confirm]');
    if (!form || form.dataset.confirmed === '1') return;

    e.preventDefault();
    const isDelete =
        (form.querySelector('input[name="_method"]')?.value || '').toUpperCase() === 'DELETE' ||
        form.dataset.confirmDanger === '1';

    const run = isDelete
        ? window.confirmarEliminar(form.dataset.confirm, form.dataset.confirmTitle || '¿Eliminar?')
        : window.confirmar({
              title: form.dataset.confirmTitle || '¿Confirmar?',
              text: form.dataset.confirm,
              icon: form.dataset.confirmIcon || 'question',
          });

    run.then((ok) => {
        if (!ok) return;
        form.dataset.confirmed = '1';
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
    });
});

document.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-confirm]');
    if (!link) return;
    e.preventDefault();
    window
        .confirmar({ title: link.dataset.confirmTitle || '¿Confirmar?', text: link.dataset.confirm })
        .then((ok) => ok && (window.location.href = link.href));
});

// --- Toasts disparados por componentes Livewire (guardar/editar/eliminar/aprobar/rechazar) ---
document.addEventListener('livewire:init', () => {
    Livewire.on('toast', ({ icon, title }) => window.toast(icon, title));
});
