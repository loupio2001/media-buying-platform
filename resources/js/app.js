import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

const toastTypes = ['success', 'error', 'warning', 'info', 'loading'];

const toastTitles = {
	success: 'Success',
	error: 'Error',
	warning: 'Warning',
	info: 'Info',
	loading: 'Working',
};

const toastClasses = {
	success: 'border-emerald-400/40 bg-emerald-950/95 shadow-emerald-950/30',
	error: 'border-rose-400/40 bg-rose-950/95 shadow-rose-950/30',
	warning: 'border-amber-400/40 bg-amber-950/95 shadow-amber-950/30',
	info: 'border-sky-400/40 bg-sky-950/95 shadow-sky-950/30',
	loading: 'border-slate-400/40 bg-slate-950/95 shadow-slate-950/30',
};

const toastDotClasses = {
	success: 'bg-emerald-300',
	error: 'bg-rose-300',
	warning: 'bg-amber-300',
	info: 'bg-sky-300',
	loading: 'bg-slate-300',
};

function normalizeToast(toast) {
	const type = toastTypes.includes(toast?.type) ? toast.type : 'info';
	const message = typeof toast?.message === 'string' ? toast.message.trim() : String(toast?.message ?? '');
	const title = typeof toast?.title === 'string' && toast.title.trim() !== '' ? toast.title.trim() : toastTitles[type];

	return {
		id: toast?.id ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`,
		type,
		title,
		message,
	};
}

window.havasToasts = {
	show(toast) {
		window.dispatchEvent(new CustomEvent('havas-toast', { detail: normalizeToast(toast) }));
	},
	success(message, title = toastTitles.success) {
		this.show({ type: 'success', title, message });
	},
	error(message, title = toastTitles.error) {
		this.show({ type: 'error', title, message });
	},
	warning(message, title = toastTitles.warning) {
		this.show({ type: 'warning', title, message });
	},
	info(message, title = toastTitles.info) {
		this.show({ type: 'info', title, message });
	},
	loading(message, title = toastTitles.loading) {
		this.show({ type: 'loading', title, message });
	},
};

window.dispatchToast = (toast) => {
	window.havasToasts.show(toast);
};

document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('form[data-toast-loading]').forEach((form) => {
		if (form.dataset.toastLoadingBound === 'true') {
			return;
		}

		form.dataset.toastLoadingBound = 'true';

		form.addEventListener('submit', () => {
			const message = form.dataset.toastLoading || 'Processing request...';
			const title = form.dataset.toastLoadingTitle || 'Working';
			window.havasToasts.loading(message, title);
		});
	});
});

document.addEventListener('alpine:init', () => {
	Alpine.data('toastStack', () => ({
		toasts: [],
		listener: null,
		init() {
			const initialToasts = Array.isArray(window.__initialToasts) ? window.__initialToasts : [];

			initialToasts.forEach((toast) => this.push(toast));
			window.__initialToasts = [];

			this.listener = (event) => {
				this.push(event.detail);
			};

			window.addEventListener('havas-toast', this.listener);
		},
		push(toast) {
			const normalized = normalizeToast(toast);
			this.toasts = [...this.toasts, normalized];
		},
		remove(id) {
			this.toasts = this.toasts.filter((toast) => toast.id !== id);
		},
		toastClasses(type) {
			return toastClasses[type] ?? toastClasses.info;
		},
		toastDotClasses(type) {
			return toastDotClasses[type] ?? toastDotClasses.info;
		},
	}));
});

Alpine.start();
