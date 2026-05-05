<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Cojan Catering</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
</head>
<body>
<nav class="cj-nav">
    <a href="{{ route('admin.dashboard') }}" class="cj-nav-brand">🍽 Cojan <span>Admin</span></a>
    <div class="cj-nav-links">
        <a href="{{ route('admin.orders') }}" class="btn-cj-outline btn-cj btn-cj-sm">Orders</a>
        <a href="{{ route('admin.menu') }}" class="btn-cj-outline btn-cj btn-cj-sm">Menu</a>
        <a href="{{ route('admin.inventory') }}" class="btn-cj-outline btn-cj btn-cj-sm">Inventory</a>
        <a href="{{ route('admin.analytics') }}" class="btn-cj-outline btn-cj btn-cj-sm">Analytics</a>
        <a href="{{ route('admin.feedback') }}" class="btn-cj-outline btn-cj btn-cj-sm">Feedback</a>
        <a href="{{ route('admin.users') }}" class="btn-cj-outline btn-cj btn-cj-sm">Users</a>
        <form method="POST" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn-cj-amber btn-cj btn-cj-sm">Logout</button>
        </form>
    </div>
</nav>

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
                                <span class="badge-cj badge-{{ $order->status === 'delivered' ? 'delivered' : ($order->status === 'cancelled' ? 'cancelled' : ($order->status === 'out_for_delivery' ? 'delivery' : ($order->status === 'preparing' ? 'preparing' : ($order->status === 'confirmed' ? 'confirmed' : 'pending')))) }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
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
<div id="admin-chat-btn" onclick="toggleAdminChat()"
    style="position:fixed;bottom:28px;right:28px;z-index:9999;
           width:58px;height:58px;border-radius:50%;cursor:pointer;
           background:linear-gradient(135deg,#1a5c38,#2d8653);
           box-shadow:0 4px 20px rgba(0,0,0,0.25);
           display:flex;align-items:center;justify-content:center;">
    <span style="color:#fff;font-size:1.5rem;">💬</span>
    <span id="admin-badge"
          style="display:none;position:absolute;top:2px;right:2px;
                 background:#e74c3c;color:#fff;border-radius:50%;
                 width:18px;height:18px;font-size:11px;font-weight:700;
                 align-items:center;justify-content:center;">0</span>
</div>

<div id="admin-chat-panel"
    style="display:none;position:fixed;top:0;right:0;width:360px;height:100vh;
           z-index:9998;background:#fff;box-shadow:-4px 0 24px rgba(0,0,0,0.15);
           flex-direction:column;">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#1a5c38,#2d8653);padding:16px 20px;
                display:flex;align-items:center;justify-content:space-between;">
        <div style="color:#fff;font-weight:700;font-size:1rem;">💬 Customer Messages</div>
        <span onclick="toggleAdminChat()"
              style="color:#fff;cursor:pointer;font-size:1.4rem;line-height:1;">&times;</span>
    </div>

    <!-- Customer List -->
    <div id="admin-customer-list" style="overflow-y:auto;flex:1;">
        <div style="padding:20px;text-align:center;color:#aaa;font-size:.85rem;">
            Loading conversations...
        </div>
    </div>

    <!-- Conversation View (hidden by default) -->
    <div id="admin-convo" style="display:none;flex-direction:column;flex:1;overflow:hidden;height:100%;">
        <div id="admin-convo-header"
            style="padding:10px 16px;background:#f4f9f6;border-bottom:1px solid #eee;
                   display:flex;align-items:center;gap:10px;cursor:pointer;"
            onclick="showCustomerList()">
            <span>←</span>
            <span id="admin-convo-name" style="font-weight:600;font-size:.9rem;"></span>
        </div>
        <div id="admin-messages"
            style="flex:1;overflow-y:auto;padding:14px;display:flex;
                   flex-direction:column;gap:8px;background:#f4f9f6;
                   height:calc(100vh - 180px);"></div>
        <div style="padding:10px 12px;border-top:1px solid #eee;display:flex;gap:8px;background:#fff;">
            <input id="admin-input" type="text" placeholder="Reply..."
                style="flex:1;border:1.5px solid #2d8653;border-radius:20px;
                       padding:8px 14px;font-size:.88rem;outline:none;"
                onkeydown="if(event.key==='Enter') sendAdminMessage()">
            <button onclick="sendAdminMessage()"
                style="background:linear-gradient(135deg,#1a5c38,#2d8653);border:none;
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

function toggleAdminChat() {
    adminChatOpen = !adminChatOpen;
    const panel = document.getElementById('admin-chat-panel');
    panel.style.display = adminChatOpen ? 'flex' : 'none';
    panel.style.flexDirection = adminChatOpen ? 'column' : '';
    if (adminChatOpen) loadAdminInbox();
}

function loadAdminInbox() {
    fetch('/admin/chat/inbox')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('admin-customer-list');
            if (data.customers.length === 0) {
                list.innerHTML = '<div style="padding:20px;text-align:center;color:#aaa;font-size:.85rem;">No messages yet.</div>';
                return;
            }
            list.innerHTML = data.customers.map(c => `
                <div onclick="openAdminConvo(${c.id}, '${c.name}')"
                    style="padding:14px 16px;border-bottom:1px solid #f0ebe0;cursor:pointer;
                           display:flex;align-items:center;gap:12px;transition:background .2s;"
                    onmouseover="this.style.background='#f4f9f6'"
                    onmouseout="this.style.background=''">
                    <div style="width:38px;height:38px;border-radius:50%;
                                background:linear-gradient(135deg,#1a5c38,#2d8653);
                                display:flex;align-items:center;justify-content:center;
                                color:#fff;font-weight:700;font-size:1rem;">
                        ${c.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:.9rem;">${c.name}</div>
                        <div style="font-size:.75rem;color:#888;">Click to open chat</div>
                    </div>
                </div>`).join('');
        });
}

function openAdminConvo(customerId, customerName) {
    activeCustomerId = customerId;
    document.getElementById('admin-customer-list').style.display = 'none';
    const convo = document.getElementById('admin-convo');
    convo.style.display = 'flex';
    convo.style.flexDirection = 'column';
    document.getElementById('admin-convo-name').textContent = customerName;

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

function renderAdminMessages(messages) {
    const box = document.getElementById('admin-messages');
    box.innerHTML = messages.length === 0
        ? '<div style="text-align:center;color:#aaa;font-size:.82rem;margin-top:30px;">No messages yet.</div>'
        : messages.map(m => adminBubble(m)).join('');
    box.scrollTop = box.scrollHeight;
}

function adminBubble(m) {
    const mine = m.sender_id === ADMIN_ID;
    return `<div style="display:flex;justify-content:${mine ? 'flex-end' : 'flex-start'};">
        <div style="max-width:75%;padding:9px 13px;
                    border-radius:${mine ? '16px 16px 4px 16px' : '16px 16px 16px 4px'};
                    background:${mine ? 'linear-gradient(135deg,#1a5c38,#2d8653)' : '#fff'};
                    color:${mine ? '#fff' : '#333'};font-size:.87rem;
                    box-shadow:0 2px 6px rgba(0,0,0,0.08);">
            ${m.body}
            <div style="font-size:.7rem;opacity:.7;margin-top:3px;text-align:right;">${m.created_at}</div>
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
        const box = document.getElementById('admin-messages');
        const div = document.createElement('div');
        div.innerHTML = adminBubble(data.message);
        box.appendChild(div.firstElementChild);
        box.scrollTop = box.scrollHeight;
    });
}

function subscribeAdminChannel(customerId) {
    if (adminSubscribed[customerId]) return;
    adminSubscribed[customerId] = true;
    const ids = [ADMIN_ID, customerId].sort((a, b) => a - b);
    window.Echo.private(`chat.${ids[0]}.${ids[1]}`).listen('MessageSent', (e) => {
        if (activeCustomerId === e.sender_id) {
            const box = document.getElementById('admin-messages');
            const div = document.createElement('div');
            div.innerHTML = adminBubble(e);
            box.appendChild(div.firstElementChild);
            box.scrollTop = box.scrollHeight;
        }
    });
}

function checkAdminUnread() {
    fetch('/admin/chat/unread')
        .then(r => r.json())
        .then(d => {
            const badge = document.getElementById('admin-badge');
            badge.style.display = d.count > 0 ? 'flex' : 'none';
            badge.textContent = d.count;
        });
}
setInterval(checkAdminUnread, 20000);
checkAdminUnread();
</script>
</body>
</html>
