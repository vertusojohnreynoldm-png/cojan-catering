{{--
    Shown to a customer an admin has blocked (User::isBlocked()) — on the
    cart, packages page and the dedicated /customer/account-restricted page
    the 'not-blocked' middleware redirects to. Deliberately neutral: the
    admin's reason is never shown, and it points them to the chat.
--}}
<div class="alert-cj alert-warning cj-restricted-notice" role="status">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    <div>
        <strong>New orders and bookings are paused on your account.</strong>
        <p class="mb-2">
            You can still view your past orders and chat with us as usual. If you think this is a mistake,
            please send us a message — we'll be glad to sort it out.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn-cj btn-cj-sm" onclick="if (typeof chatOpen !== 'undefined' && !chatOpen) toggleChat();">
                <i class="bi bi-chat-dots"></i> Message us
            </button>
            <a href="{{ route('customer.orders') }}" class="btn-cj btn-cj-sm"
               style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);">
                View my orders
            </a>
        </div>
    </div>
</div>
