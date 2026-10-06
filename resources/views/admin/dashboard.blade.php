<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Cojan Catering</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
</head>
<body>
<x-admin-nav />
<x-toast />

<div class="cj-page">
    <h1 class="cj-page-title">Welcome back, {{ auth()->user()->name }}!</h1>
    <p class="cj-page-sub">Here's what's happening with your catering business today.</p>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-blue">
                <h5>Total Orders</h5>
                <div class="stat-num">{{ $totalOrders }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-amber">
                <h5>Pending Orders</h5>
                <div class="stat-num">{{ $pendingOrders }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-green">
                <h5>Total Customers</h5>
                <div class="stat-num">{{ $totalCustomers }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-teal">
                <h5>Total Revenue</h5>
                <div class="stat-num" style="font-size:1.7rem">₱{{ number_format($totalRevenue, 0) }}</div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="cj-card">
        <div class="cj-card-header d-flex justify-content-between align-items-center">
            <span>Recent Orders</span>
            <a href="{{ route('admin.orders') }}" class="btn-cj-amber btn-cj btn-cj-sm">View All</a>
        </div>
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                        <tr>
                            <td><strong>{{ $order->order_number }}</strong></td>
                            <td>{{ $order->user->name }}</td>
                            <td>₱{{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <x-order-status-badge :order="$order" />
                                    @if($order->isBooking())
                                        <span class="badge-cj badge-booking-type">📦 Booking</span>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="btn-cj btn-cj-sm">View</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center py-4" style="color:var(--text-light)">No orders yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])

<meta name="csrf-token" content="{{ csrf_token() }}">

<!-- ========== ADMIN CHAT PANEL ========== -->
<style>
/* Bubble icon morph (chat <-> close) and panel expand — state values apply
   regardless of motion preference; only the smooth transition/animation
   itself is gated below, so reduced-motion users get an instant snap. */
.cj-bubble-icon {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cj-icon-chat { transform: rotate(0deg); opacity: 1; }
.cj-icon-close { transform: rotate(-90deg); opacity: 0; }
#admin-chat-btn.cj-chat-open .cj-icon-chat { transform: rotate(90deg); opacity: 0; }
#admin-chat-btn.cj-chat-open .cj-icon-close { transform: rotate(0deg); opacity: 1; }

#admin-chat-panel {
    transform-origin: bottom right;
    transform: scale(0.85) translateY(10px);
    opacity: 0;
}
#admin-chat-panel.cj-panel-open {
    transform: scale(1) translateY(0);
    opacity: 1;
}

@media (prefers-reduced-motion: no-preference) {
    .cj-bubble-icon { transition: transform .25s ease, opacity .25s ease; }
    #admin-chat-panel { transition: transform .28s ease-out, opacity .28s ease-out; }

    #admin-chat-btn.cj-bubble-pop { animation: cjBubblePop .38s cubic-bezier(0.34, 1.56, 0.64, 1); }
    @keyframes cjBubblePop {
        0%   { transform: scale(1); }
        40%  { transform: scale(0.9); }
        70%  { transform: scale(1.08); }
        100% { transform: scale(1); }
    }

    #admin-chat-btn.cj-bubble-idle { animation: cjBubblePulse 2.8s ease-in-out infinite; }
    @keyframes cjBubblePulse {
        0%, 100% { transform: scale(1); box-shadow: 0 4px 20px rgba(0,0,0,0.25); }
        50%      { transform: scale(1.04); box-shadow: 0 4px 20px rgba(0,0,0,0.25), 0 0 0 8px rgba(193,68,30,.18); }
    }
}
</style>
<button type="button" id="admin-chat-btn" onclick="toggleAdminChat()" class="cj-bubble-idle"
    aria-haspopup="dialog" aria-expanded="false" aria-label="Open customer messages"
    style="position:fixed;bottom:28px;right:28px;z-index:9999;
           width:58px;height:58px;border-radius:50%;cursor:pointer;border:none;padding:0;font:inherit;
           background:linear-gradient(135deg,#7A2E1D,#C1441E);
           box-shadow:0 4px 20px rgba(0,0,0,0.25);
           display:flex;align-items:center;justify-content:center;">
    <span class="cj-bubble-icon cj-icon-chat" style="color:#fff;font-size:1.5rem;" aria-hidden="true">💬</span>
    <span class="cj-bubble-icon cj-icon-close" style="color:#fff;font-size:1.5rem;" aria-hidden="true">✕</span>
    <span id="admin-badge" aria-hidden="true"
          style="display:none;position:absolute;top:2px;right:2px;
                 background:#e74c3c;color:#fff;border-radius:50%;
                 width:18px;height:18px;font-size:11px;font-weight:700;
                 align-items:center;justify-content:center;">0</span>
</button>

<div id="admin-chat-panel"
    style="display:none;position:fixed;top:0;right:0;width:360px;height:100vh;
           z-index:9998;background:#fff;box-shadow:-4px 0 24px rgba(0,0,0,0.15);
           flex-direction:column;">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#7A2E1D,#C1441E);padding:16px 20px;
                display:flex;align-items:center;justify-content:space-between;">
        <div style="color:#fff;font-weight:700;font-size:1rem;">💬 Customer Messages</div>
        <button type="button" onclick="toggleAdminChat()" aria-label="Close messages panel"
                style="background:none;border:none;padding:0;color:#fff;cursor:pointer;font-size:1.4rem;line-height:1;font:inherit;">&times;</button>
    </div>

    <!-- Customer List -->
    <div id="admin-customer-list" style="overflow-y:auto;flex:1;"></div>

    <!-- Conversation View (hidden by default) -->
    <div id="admin-convo" style="display:none;flex-direction:column;flex:1;overflow:hidden;height:100%;">
        <button type="button" id="admin-convo-header" onclick="showCustomerList()" aria-label="Back to conversation list"
            style="padding:10px 16px;background:var(--cream);border:none;border-bottom:1px solid #eee;
                   display:flex;align-items:center;gap:10px;cursor:pointer;width:100%;text-align:left;font:inherit;">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <span id="admin-convo-name" style="font-weight:600;font-size:.9rem;"></span>
        </button>
        <div id="admin-messages" class="cj-chat-messages"
            style="flex:1;overflow-y:auto;padding:14px;display:flex;
                   flex-direction:column;gap:8px;
                   height:calc(100vh - 180px);"></div>
        <div style="padding:10px 12px;border-top:1px solid #eee;display:flex;gap:8px;background:#fff;">
            <input id="admin-input" type="text" placeholder="Reply..." aria-label="Reply to customer"
                style="flex:1;border:1.5px solid #C1441E;border-radius:20px;
                       padding:8px 14px;font-size:.88rem;outline:none;"
                onkeydown="if(event.key==='Enter') sendAdminMessage()">
            <button onclick="sendAdminMessage()" aria-label="Send message"
                style="background:linear-gradient(135deg,#7A2E1D,#C1441E);border:none;
                       border-radius:50%;width:38px;height:38px;color:#fff;cursor:pointer;
                       display:flex;align-items:center;justify-content:center;font-size:1rem;">
                ➤
            </button>
        </div>
    </div>
</div>

<script>
const ADMIN_ID = {{ auth()->id() }};
let adminChatOpen = false;
let activeCustomerId = null;
let adminSubscribed = {};
let adminPulseAfterPop = false;

// The pop animation and the idle pulse both animate #admin-chat-btn's own
// transform, and CSS can't run two animations on the same element via
// separate classes without one fully overriding the other — so the pulse
// only ever resumes once the pop animation reports it has finished, never
// by re-adding the class immediately.
document.getElementById('admin-chat-btn').addEventListener('animationend', function (e) {
    if (e.animationName === 'cjBubblePop') {
        this.classList.remove('cj-bubble-pop');
        if (adminPulseAfterPop) {
            this.classList.add('cj-bubble-idle');
            adminPulseAfterPop = false;
        }
    }
});

function toggleAdminChat() {
    adminChatOpen = !adminChatOpen;
    const panel = document.getElementById('admin-chat-panel');
    const bubble = document.getElementById('admin-chat-btn');

    bubble.classList.remove('cj-bubble-idle');
    bubble.classList.remove('cj-bubble-pop');
    void bubble.offsetWidth; // force reflow so the pop animation restarts on rapid clicks
    bubble.classList.add('cj-bubble-pop');
    bubble.classList.toggle('cj-chat-open', adminChatOpen);
    bubble.setAttribute('aria-expanded', String(adminChatOpen));

    if (adminChatOpen) {
        bubble.setAttribute('aria-label', 'Close customer messages');
        adminPulseAfterPop = false;
        panel.style.display = 'flex';
        panel.style.flexDirection = 'column';
        // Double rAF: guarantees the browser has painted the closed state
        // before the "open" class is added, so the expand transition runs.
        requestAnimationFrame(() => requestAnimationFrame(() => {
            panel.classList.add('cj-panel-open');
        }));
        loadAdminInbox();
    } else {
        bubble.setAttribute('aria-label', 'Open customer messages');
        panel.classList.remove('cj-panel-open');
        setTimeout(() => {
            if (!adminChatOpen) {
                panel.style.display = 'none';
                panel.style.flexDirection = '';
            }
        }, 300);

        const badge = document.getElementById('admin-badge');
        adminPulseAfterPop = (badge.style.display !== 'flex');
        bubble.focus();
    }
}

// Close the panel on Escape, same as the customer-side chat widget.
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && adminChatOpen) {
        toggleAdminChat();
    }
});

function inboxRowSkeleton() {
    return `<div style="padding:14px 16px;border-bottom:1px solid #f0ebe0;display:flex;align-items:center;gap:12px;">
        <div class="cj-skeleton" style="width:38px;height:38px;border-radius:50%;flex-shrink:0;"></div>
        <div style="flex:1;">
            <div class="cj-skeleton" style="width:55%;height:12px;margin-bottom:6px;"></div>
            <div class="cj-skeleton" style="width:35%;height:10px;"></div>
        </div>
    </div>`;
}

function chatMessageSkeleton() {
    const bubble = (side, w, h) => `<div style="display:flex;justify-content:${side};">
        <div class="cj-skeleton" style="width:${w};height:${h}px;border-radius:${side === 'flex-start' ? '16px 16px 16px 4px' : '16px 16px 4px 16px'};"></div>
    </div>`;
    return bubble('flex-start', '65%', 38) + bubble('flex-end', '45%', 32) + bubble('flex-start', '75%', 44);
}

function loadAdminInbox() {
    const list = document.getElementById('admin-customer-list');
    list.innerHTML = inboxRowSkeleton().repeat(3);
    fetch('/admin/chat/inbox')
        .then(r => r.json())
        .then(data => {
            list.innerHTML = '';
            if (data.customers.length === 0) {
                list.innerHTML = '<div style="padding:20px;text-align:center;color:#aaa;font-size:.85rem;">No messages yet.</div>';
                return;
            }
            data.customers.forEach(c => list.appendChild(buildInboxRow(c)));
        });
}

// Built via DOM APIs (not an innerHTML template) so customer names never pass
// through HTML/attribute parsing — also fixes the div-with-onclick pattern,
// which real keyboard users cannot Tab to or activate at all.
function buildInboxRow(c) {
    const row = document.createElement('button');
    row.type = 'button';
    row.style.cssText = 'width:100%;text-align:left;border:none;background:none;font:inherit;'
        + 'padding:14px 16px;border-bottom:1px solid #f0ebe0;cursor:pointer;'
        + 'display:flex;align-items:center;gap:12px;transition:background .2s;';
    row.addEventListener('mouseover', () => row.style.background = '#f4f9f6');
    row.addEventListener('mouseout', () => row.style.background = '');
    row.addEventListener('click', () => openAdminConvo(c.id, c.name));

    const avatar = document.createElement('span');
    avatar.style.cssText = 'width:38px;height:38px;border-radius:50%;flex-shrink:0;'
        + 'background:linear-gradient(135deg,#7A2E1D,#C1441E);'
        + 'display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;';
    avatar.textContent = c.name.charAt(0).toUpperCase();
    avatar.setAttribute('aria-hidden', 'true');

    const meta = document.createElement('span');
    const nameEl = document.createElement('span');
    nameEl.style.cssText = 'display:block;font-weight:600;font-size:.9rem;';
    nameEl.textContent = c.name;
    const hintEl = document.createElement('span');
    hintEl.style.cssText = 'display:block;font-size:.75rem;color:#888;';
    hintEl.textContent = 'Click to open chat';
    meta.appendChild(nameEl);
    meta.appendChild(hintEl);

    row.appendChild(avatar);
    row.appendChild(meta);
    return row;
}

function openAdminConvo(customerId, customerName) {
    activeCustomerId = customerId;
    document.getElementById('admin-customer-list').style.display = 'none';
    const convo = document.getElementById('admin-convo');
    convo.style.display = 'flex';
    convo.style.flexDirection = 'column';
    document.getElementById('admin-convo-name').textContent = customerName;
    document.getElementById('admin-messages').innerHTML = chatMessageSkeleton();

    fetch(`/admin/chat/messages/${customerId}`)
        .then(r => r.json())
        .then(data => {
            renderAdminMessages(data.messages);
            subscribeAdminChannel(customerId);
        });
}

function showCustomerList() {
    activeCustomerId = null;
    document.getElementById('admin-convo').style.display = 'none';
    document.getElementById('admin-customer-list').style.display = 'block';
    loadAdminInbox();
}

let lastAdminMessageSenderId = null;

function renderAdminMessages(messages) {
    const box = document.getElementById('admin-messages');
    if (messages.length === 0) {
        box.innerHTML = '<div style="text-align:center;color:#aaa;font-size:.82rem;margin-top:30px;">No messages yet.</div>';
        lastAdminMessageSenderId = null;
        return;
    }
    box.innerHTML = messages.map((m, i) =>
        adminBubble(m, i > 0 && messages[i - 1].sender_id === m.sender_id)
    ).join('');
    box.scrollTop = box.scrollHeight;
    lastAdminMessageSenderId = messages[messages.length - 1].sender_id;
}

// `grouped` tightens the row's spacing when this message immediately follows
// another one from the same sender — same convention as the customer widget.
function adminBubble(m, grouped) {
    const mine = m.sender_id === ADMIN_ID;
    const rowClass = grouped ? 'cj-chat-row-grouped' : '';
    const bubbleClass = mine ? 'cj-chat-bubble-mine' : 'cj-chat-bubble-theirs';
    return `<div class="${rowClass}" style="display:flex;justify-content:${mine ? 'flex-end' : 'flex-start'};">
        <div class="${bubbleClass}">
            ${m.body}
            <div class="cj-chat-timestamp">${m.created_at}</div>
        </div>
    </div>`;
}

function sendAdminMessage() {
    const input = document.getElementById('admin-input');
    const body = input.value.trim();
    if (!body || !activeCustomerId) return;
    input.value = '';

    fetch('/admin/chat/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ body, receiver_id: activeCustomerId })
    }).then(r => r.json()).then(data => {
        appendAdminMessage(data.message);
    });
}

function subscribeAdminChannel(customerId) {
    if (adminSubscribed[customerId]) return;
    adminSubscribed[customerId] = true;
    const ids = [ADMIN_ID, customerId].sort((a, b) => a - b);
    window.Echo.private(`chat.${ids[0]}.${ids[1]}`).listen('MessageSent', (e) => {
        if (activeCustomerId === e.sender_id) {
            appendAdminMessage(e);
        }
    });
}

function appendAdminMessage(m) {
    const box = document.getElementById('admin-messages');
    const grouped = lastAdminMessageSenderId === m.sender_id;
    const div = document.createElement('div');
    div.innerHTML = adminBubble(m, grouped);
    box.appendChild(div.firstElementChild);
    box.scrollTop = box.scrollHeight;
    lastAdminMessageSenderId = m.sender_id;
}

function checkAdminUnread() {
    fetch('/admin/chat/unread')
        .then(r => r.json())
        .then(d => {
            const badge = document.getElementById('admin-badge');
            badge.style.display = d.count > 0 ? 'flex' : 'none';
            badge.textContent = d.count;

            // Idle pulse only while closed and nothing unread — an unread
            // badge is already its own, stronger signal.
            const bubble = document.getElementById('admin-chat-btn');
            bubble.classList.toggle('cj-bubble-idle', d.count === 0 && !adminChatOpen);
        });
}
setInterval(checkAdminUnread, 20000);
checkAdminUnread();
</script>
</body>
</html>
