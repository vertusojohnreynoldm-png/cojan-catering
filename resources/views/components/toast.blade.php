{{-- Transient success/error confirmations as a corner toast, not a full-width
     banner. Form validation errors ($errors->any()) are NOT handled here —
     those stay as inline alert-cj blocks near the field they belong to. --}}
@if(session('success') || session('error'))
<div id="cj-toast-container" role="status" aria-live="polite" style="position:fixed;bottom:24px;left:24px;z-index:10050;display:flex;flex-direction:column;gap:.6rem;"></div>
<script>
(function () {
    const messages = [];
    @if(session('success'))
        messages.push({ type: 'success', text: @json(session('success')) });
    @endif
    @if(session('error'))
        messages.push({ type: 'error', text: @json(session('error')) });
    @endif

    function showToast(type, text) {
        const container = document.getElementById('cj-toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'cj-toast cj-toast-' + type;

        const icon = document.createElement('span');
        icon.textContent = type === 'success' ? '✅' : '❌';
        const msg = document.createElement('span');
        msg.textContent = text; // textContent, not innerHTML — never interprets the message as markup

        toast.appendChild(icon);
        toast.appendChild(msg);
        container.appendChild(toast);

        requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('cj-toast-show')));
        setTimeout(() => {
            toast.classList.remove('cj-toast-show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    messages.forEach(m => showToast(m.type, m.text));
})();
</script>
@endif
