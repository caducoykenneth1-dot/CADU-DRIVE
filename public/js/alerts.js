window.addEventListener("DOMContentLoaded", () => {
    // Auto-dismiss flash alerts
    document.querySelectorAll('.alert-success, .alert-danger').forEach(alert => {
        const fadeOutDelay = 3000;
        setTimeout(() => {
            alert.classList.add('fade-out');
            alert.addEventListener('animationend', () => alert.remove(), { once: true });
        }, fadeOutDelay);
    });

    const showConfirm = ({ title, text, confirmButtonText }) => {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                title,
                text,
                icon: 'warning',
                showCancelButton: true,
                focusCancel: true,
                confirmButtonText,
                cancelButtonText: 'Cancel',
                buttonsStyling: false,
                customClass: {
                    popup: 'swal-confirm-modal',
                    title: 'swal-confirm-title',
                    htmlContainer: 'swal-confirm-text',
                    confirmButton: 'swal-confirm-btn',
                    cancelButton: 'swal-cancel-btn'
                }
            }).then(result => result.isConfirmed);
        }

        return Promise.resolve(window.confirm(text));
    };

    document.addEventListener('click', event => {
        const button = event.target.closest('.confirm-delete-btn');
        if (!button) {
            return;
        }

        event.preventDefault();

        const form = button.closest('form');
        if (!form) {
            return;
        }

        const title = button.dataset.confirmTitle || 'Are you sure?';
        const text = button.dataset.confirmText || button.dataset.confirm || 'This action cannot be undone.';
        const confirmButtonText = button.dataset.confirmButton || 'Yes, proceed';

        showConfirm({ title, text, confirmButtonText }).then(confirmed => {
            if (confirmed) {
                form.submit();
            }
        });
    });
});
