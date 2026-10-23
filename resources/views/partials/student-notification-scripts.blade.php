<script>
    function acknowledgeAlert(flagId, button) {
        const btn = button;
        const alertDiv = btn.closest('.alert');
        const actionsDiv = btn.closest('.alert-actions');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        fetch('/student/acknowledge-alert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ flag_id: flagId }),
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                if (actionsDiv) {
                    actionsDiv.innerHTML = '<span class="badge bg-success">'
                        + '<i class="fas fa-check me-1"></i> Acknowledged</span>';
                }

                if (alertDiv) {
                    alertDiv.classList.remove('alert-warning', 'alert-info', 'alert-danger', 'alert-secondary');
                    alertDiv.classList.add('alert-success');

                    const newBadge = alertDiv.querySelector('.badge.bg-danger');
                    if (newBadge && newBadge.textContent.trim() === 'NEW') {
                        newBadge.remove();
                    }
                }

                const bellCount = document.querySelector('#studentBellToggle .student-bell-count');
                if (bellCount) {
                    const remaining = Math.max(0, (parseInt(bellCount.textContent, 10) || 0) - 1);

                    if (remaining > 0) {
                        bellCount.textContent = remaining > 99 ? '99+' : String(remaining);
                    } else {
                        bellCount.remove();
                    }
                }

                showStudentToast('Alert acknowledged.', 'success');
            } else {
                showStudentToast(data.message || 'Could not acknowledge the alert.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Acknowledge';
            }
        })
        .catch(function (error) {
            console.error('[Student Dashboard] Acknowledge failed:', error);
            showStudentToast('Error: ' + error.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Acknowledge';
        });
    }

    function showStudentToast(message, type) {
        const colors = {
            success: 'bg-success text-white',
            error: 'bg-danger text-white',
            warning: 'bg-warning text-dark',
            info: 'bg-info text-white',
        };

        const toast = document.createElement('div');
        toast.className = 'custom-toast toast align-items-center border-0 show ' + (colors[type] || colors.info);
        toast.setAttribute('role', 'alert');
        toast.style.position = 'fixed';
        toast.style.top = '80px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.minWidth = '260px';
        toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        toast.style.borderRadius = '8px';
        toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div>'
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';

        document.body.appendChild(toast);

        setTimeout(function () { toast.remove(); }, 4000);
    }
</script>