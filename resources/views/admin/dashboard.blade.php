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

<!-- ========== ADMIN CHAT PANEL (Messenger-style inbox) ========== -->
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

/* Inbox rows */
.cj-inbox-row { transition: background-color 1s ease; }
.cj-inbox-row.cj-unread { background: var(--amber-light, #fef3dc); }
.cj-inbox-row.cj-unread .cj-inbox-name,
.cj-inbox-row.cj-unread .cj-inbox-preview { font-weight: 700; color: var(--text-dark, #2B211D); }
.cj-inbox-row.cj-inbox-row-active { background: var(--green-light, #FBE8DE); }
.cj-inbox-badge {
    background: var(--green-mid, #C1441E); color: #fff; border-radius: 999px;
    min-width: 20px; height: 20px; font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; padding: 0 6px; flex-shrink: 0;
}
@media (prefers-reduced-motion: no-preference) {
    .cj-inbox-pulse { animation: cjInboxPulse .7s ease; }
    @keyframes cjInboxPulse {
        0%, 100% { background-color: var(--amber-light, #fef3dc); }
        40%      { background-color: #ffdd8a; }
    }
}

/* Mobile: full-screen panel, same proven pattern as the customer chat widget
   — floating buttons must never sit on top of the thread's Send button, so
   the trigger bubble is hidden entirely while open (see body.admin-chat-open
   below) and the panel's own header × is the only way to close it. */
@media (max-width: 768px) {
    #admin-chat-panel {
        width: 100vw !important;
        height: 100dvh !important;
        border-radius: 0;
    }
}
body.admin-chat-open #admin-chat-btn { display: none !important; }

/* Desktop ≥992px: two-pane — list always visible on the left, conversation
   on the right, no single-pane show/hide switching and no "back" affordance
   (there's nothing to go back from when both panes are always on screen). */
@media (min-width: 992px) {
    #admin-chat-panel { width: 860px !important; }
    #admin-customer-list { display: block !important; width: 320px; flex-shrink: 0; border-right: 1px solid #eee; }
    #admin-convo { display: flex !important; }
    #admin-convo-header { pointer-events: none; cursor: default; }
    #admin-convo-back-icon { display: none; }
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
           flex-direction:column;overflow:hidden;">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#7A2E1D,#C1441E);padding:16px 20px;
                display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <div style="color:#fff;font-weight:700;font-size:1rem;">💬 Customer Messages</div>
        <div style="display:flex;align-items:center;gap:2px;">
            <button type="button" id="admin-mute-btn" onclick="toggleAdminMute()" aria-pressed="false"
                    aria-label="Mute notification sound"
                    style="background:none;border:none;padding:0;color:#fff;cursor:pointer;font-size:1rem;
                           width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-volume-up-fill" aria-hidden="true"></i>
            </button>
            <button type="button" onclick="toggleAdminChat()" aria-label="Close messages panel"
                    style="background:none;border:none;padding:0;color:#fff;cursor:pointer;font-size:1.4rem;line-height:1;
                           font:inherit;width:36px;height:36px;">&times;</button>
        </div>
    </div>

    <div style="flex:1;display:flex;overflow:hidden;min-height:0;">
        <!-- Customer List -->
        <div id="admin-customer-list" style="overflow-y:auto;flex:1;"></div>

        <!-- Conversation View (hidden by default on mobile/tablet; forced visible ≥992px) -->
        <div id="admin-convo" style="display:none;flex-direction:column;flex:1;overflow:hidden;min-width:0;">
            <div id="admin-convo-empty" style="display:flex;flex:1;align-items:center;justify-content:center;
                        flex-direction:column;gap:10px;color:#aaa;text-align:center;padding:20px;">
                <i class="bi bi-chat-dots" style="font-size:2.2rem;opacity:.4;" aria-hidden="true"></i>
                <span style="font-size:.85rem;">Select a conversation to start replying</span>
            </div>
            <div id="admin-convo-active" style="display:none;flex-direction:column;flex:1;overflow:hidden;min-height:0;">
                <button type="button" id="admin-convo-header" onclick="showCustomerList()" aria-label="Back to conversation list"
                    style="padding:10px 16px;background:var(--cream);border:none;border-bottom:1px solid #eee;
                           display:flex;align-items:center;gap:10px;cursor:pointer;width:100%;text-align:left;
                           font:inherit;flex-shrink:0;min-height:44px;">
                    <i class="bi bi-arrow-left" id="admin-convo-back-icon" aria-hidden="true"></i>
                    <span id="admin-convo-name" style="font-weight:600;font-size:.9rem;"></span>
                </button>
                <div id="admin-messages" class="cj-chat-messages"
                    style="flex:1;overflow-y:auto;padding:14px;display:flex;
                           flex-direction:column;gap:8px;min-height:0;"></div>
                <div id="admin-input-row" style="padding:10px 12px;border-top:1px solid #eee;display:flex;gap:8px;
                            background:#fff;flex-shrink:0;">
                    <input id="admin-input" type="text" placeholder="Reply..." aria-label="Reply to customer"
                        style="flex:1;min-width:0;border:1.5px solid #C1441E;border-radius:20px;
                               padding:8px 14px;font-size:16px;outline:none;"
                        onkeydown="if(event.key==='Enter') sendAdminMessage()">
                    <button onclick="sendAdminMessage()" aria-label="Send message"
                        style="background:linear-gradient(135deg,#7A2E1D,#C1441E);border:none;flex-shrink:0;
                               border-radius:50%;width:44px;height:44px;color:#fff;cursor:pointer;
                               display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
                        ➤
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ADMIN_ID = {{ auth()->id() }};
const ADMIN_BASE_TITLE = document.title;
let adminChatOpen = false;
let activeCustomerId = null;
let adminSubscribed = {};
let adminPulseAfterPop = false;
let inboxData = [];

let adminMuted = false;
try { adminMuted = localStorage.getItem('cj_admin_chat_muted') === '1'; } catch (e) {}

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

// visualViewport (not plain 100dvh) tracks the iOS on-screen keyboard — same
// fix already proven on the customer chat widget.
if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', function () {
        const panel = document.getElementById('admin-chat-panel');
        if (adminChatOpen && window.matchMedia('(max-width: 768px)').matches) {
            panel.style.setProperty('height', window.visualViewport.height + 'px', 'important');
        }
    });
}

function toggleAdminChat() {
    adminChatOpen = !adminChatOpen;
    const panel = document.getElementById('admin-chat-panel');
    const bubble = document.getElementById('admin-chat-btn');

    bubble.classList.remove('cj-bubble-idle');
    bubble.classList.remove('cj-bubble-pop');
    void bubble.offsetWidth; // force reflow so the pop animation restarts on rapid clicks
    bubble.classList.add('cj-bubble-pop');
    bubble.classList.toggle('cj-chat-open', adminChatOpen);
    document.body.classList.toggle('admin-chat-open', adminChatOpen);
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
                panel.style.removeProperty('height'); // clear any keyboard-driven override
            }
        }, 300);

        updateAdminBadgeAndTitle();
        adminPulseAfterPop = (document.getElementById('admin-badge').style.display !== 'flex');
        bubble.focus();
    }
}

// Close the panel on Escape, same as the customer-side chat widget.
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && adminChatOpen) {
        toggleAdminChat();
    }
});

function toggleAdminMute() {
    adminMuted = !adminMuted;
    try { localStorage.setItem('cj_admin_chat_muted', adminMuted ? '1' : '0'); } catch (e) {}
    updateAdminMuteButton();
}
function updateAdminMuteButton() {
    const btn = document.getElementById('admin-mute-btn');
    btn.querySelector('i').className = adminMuted ? 'bi bi-volume-mute-fill' : 'bi bi-volume-up-fill';
    btn.setAttribute('aria-pressed', String(adminMuted));
    btn.setAttribute('aria-label', adminMuted ? 'Unmute notification sound' : 'Mute notification sound');
}
updateAdminMuteButton();

// A short synthesized tone — no audio asset to commit, no new dependency.
function playAdminChatSound() {
    if (adminMuted) return;
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.3);
    } catch (e) {}
}

// "2m" / "3h" / "Yesterday" / "Oct 15" — no library.
function relTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const diffMin = Math.floor((Date.now() - d.getTime()) / 60000);
    if (diffMin < 1) return 'now';
    if (diffMin < 60) return diffMin + 'm';
    if (diffMin < 1440) return Math.floor(diffMin / 60) + 'h';
    const startOfToday = new Date(); startOfToday.setHours(0, 0, 0, 0);
    const startOfYesterday = new Date(startOfToday); startOfYesterday.setDate(startOfYesterday.getDate() - 1);
    if (d >= startOfYesterday && d < startOfToday) return 'Yesterday';
    if (diffMin < 10080) return Math.floor(diffMin / 1440) + 'd';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function inboxRowSkeleton() {
    return `<div style="padding:12px 16px;border-bottom:1px solid #f0ebe0;display:flex;align-items:center;gap:12px;min-height:56px;">
        <div class="cj-skeleton" style="width:40px;height:40px;border-radius:50%;flex-shrink:0;"></div>
        <div style="flex:1;">
            <div class="cj-skeleton" style="width:55%;height:12px;margin-bottom:6px;"></div>
            <div class="cj-skeleton" style="width:70%;height:10px;"></div>
        </div>
    </div>`;
}

function chatMessageSkeleton() {
    const bubble = (side, w, h) => `<div style="display:flex;justify-content:${side};">
        <div class="cj-skeleton" style="width:${w};height:${h}px;border-radius:${side === 'flex-start' ? '16px 16px 16px 4px' : '16px 16px 4px 16px'};"></div>
    </div>`;
    return bubble('flex-start', '65%', 38) + bubble('flex-end', '45%', 32) + bubble('flex-start', '75%', 44);
}

let inboxLoaded = false;
function loadAdminInbox() {
    const list = document.getElementById('admin-customer-list');
    if (!inboxLoaded) list.innerHTML = inboxRowSkeleton().repeat(3);
    fetch('/admin/chat/inbox')
        .then(r => r.json())
        .then(data => {
            inboxData = data.customers;
            inboxLoaded = true;
            renderInboxList();
            updateAdminBadgeAndTitle();
        });
}

function renderInboxList() {
    const list = document.getElementById('admin-customer-list');
    list.innerHTML = '';
    if (inboxData.length === 0) {
        list.innerHTML = '<div style="padding:20px;text-align:center;color:#aaa;font-size:.85rem;">No messages yet.</div>';
        return;
    }
    inboxData.forEach(c => list.appendChild(buildInboxRow(c)));
}

// Built via DOM APIs (not an innerHTML template) so customer names/previews
// never pass through HTML parsing.
function buildInboxRow(c) {
    const row = document.createElement('button');
    row.type = 'button';
    row.dataset.customerId = c.id;
    row.className = 'cj-inbox-row'
        + (c.unread_count > 0 ? ' cj-unread' : '')
        + (c.id === activeCustomerId ? ' cj-inbox-row-active' : '');
    row.style.cssText = 'width:100%;text-align:left;border:none;background:none;font:inherit;'
        + 'padding:12px 16px;border-bottom:1px solid #f0ebe0;cursor:pointer;min-height:56px;'
        + 'display:flex;align-items:center;gap:12px;';
    row.addEventListener('click', () => openAdminConvo(c.id, c.name));

    const avatar = document.createElement('span');
    avatar.style.cssText = 'width:40px;height:40px;border-radius:50%;flex-shrink:0;'
        + 'background:linear-gradient(135deg,#7A2E1D,#C1441E);'
        + 'display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;';
    avatar.textContent = c.name.charAt(0).toUpperCase();
    avatar.setAttribute('aria-hidden', 'true');

    const meta = document.createElement('span');
    meta.style.cssText = 'flex:1;min-width:0;';

    const topLine = document.createElement('span');
    topLine.style.cssText = 'display:flex;justify-content:space-between;align-items:baseline;gap:8px;';
    const nameEl = document.createElement('span');
    nameEl.className = 'cj-inbox-name';
    nameEl.style.cssText = 'font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;';
    nameEl.textContent = c.name;
    const timeEl = document.createElement('span');
    timeEl.style.cssText = 'font-size:.72rem;color:#999;flex-shrink:0;';
    timeEl.textContent = relTime(c.timestamp);
    topLine.appendChild(nameEl);
    topLine.appendChild(timeEl);

    const bottomLine = document.createElement('span');
    bottomLine.style.cssText = 'display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:2px;';
    const previewEl = document.createElement('span');
    previewEl.className = 'cj-inbox-preview';
    previewEl.style.cssText = 'font-size:.78rem;color:#888;overflow:hidden;text-overflow:ellipsis;'
        + 'white-space:nowrap;flex:1;min-width:0;display:block;';
    previewEl.textContent = c.preview || '';
    bottomLine.appendChild(previewEl);
    if (c.unread_count > 0) {
        const badge = document.createElement('span');
        badge.className = 'cj-inbox-badge';
        badge.textContent = c.unread_count > 9 ? '9+' : c.unread_count;
        bottomLine.appendChild(badge);
    }

    meta.appendChild(topLine);
    meta.appendChild(bottomLine);
    row.appendChild(avatar);
    row.appendChild(meta);
    return row;
}

function openAdminConvo(customerId, customerName) {
    activeCustomerId = customerId;
    document.getElementById('admin-customer-list').style.display = 'none';
    document.getElementById('admin-convo').style.display = 'flex';
    document.getElementById('admin-convo-empty').style.display = 'none';
    document.getElementById('admin-convo-active').style.display = 'flex';
    document.getElementById('admin-convo-name').textContent = customerName;
    document.getElementById('admin-messages').innerHTML = chatMessageSkeleton();

    markConvoReadLocally(customerId);

    fetch(`/admin/chat/messages/${customerId}`)
        .then(r => r.json())
        .then(data => {
            renderAdminMessages(data.messages);
            subscribeAdminChannel(customerId);
        });
}

// Client-side half of "opening a conversation marks it read": zeroes the
// local unread count/badge immediately and removes .cj-unread, which fades
// the row's highlight back to normal over the 1s transition defined on
// .cj-inbox-row — the server-side mark-as-read already happened (or is about
// to, via the fetch above / the live-message handler below).
function markConvoReadLocally(customerId) {
    const entry = inboxData.find(c => c.id === customerId);
    if (entry) entry.unread_count = 0;

    document.querySelectorAll('.cj-inbox-row-active').forEach(r => r.classList.remove('cj-inbox-row-active'));
    const row = document.querySelector('.cj-inbox-row[data-customer-id="' + customerId + '"]');
    if (row) {
        row.classList.remove('cj-unread');
        row.classList.add('cj-inbox-row-active');
        const badge = row.querySelector('.cj-inbox-badge');
        if (badge) badge.remove();
    }
    updateAdminBadgeAndTitle();
}

function markReadOnServer(customerId) {
    fetch(`/admin/chat/messages/${customerId}`).catch(() => {});
}

function showCustomerList() {
    if (window.matchMedia('(min-width: 992px)').matches) return; // two-pane: no "back" concept
    activeCustomerId = null;
    document.getElementById('admin-convo').style.display = 'none';
    document.getElementById('admin-customer-list').style.display = 'block';
    document.querySelectorAll('.cj-inbox-row-active').forEach(r => r.classList.remove('cj-inbox-row-active'));
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
            markReadOnServer(e.sender_id); // conversation is open and visible — read immediately
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

function updateAdminBadgeAndTitle() {
    const total = inboxData.reduce((sum, c) => sum + c.unread_count, 0);
    const badge = document.getElementById('admin-badge');
    badge.style.display = total > 0 ? 'flex' : 'none';
    badge.textContent = total > 9 ? '9+' : total;
    document.title = total > 0 ? `(${total}) ${ADMIN_BASE_TITLE}` : ADMIN_BASE_TITLE;

    // Idle pulse only while closed and nothing unread — an unread badge is
    // already its own, stronger signal.
    const bubble = document.getElementById('admin-chat-btn');
    bubble.classList.toggle('cj-bubble-idle', total === 0 && !adminChatOpen);
}

// Live inbox updates: reorder-to-top, highlight, badge, preview, sound —
// pushed to the admin's own already-authorized App.Models.User.{id} channel
// so this works regardless of which (if any) conversation is open.
function handleAdminInboxUpdate(e) {
    let entry = inboxData.find(c => c.id === e.customer_id);
    if (!entry) {
        entry = { id: e.customer_id, name: e.customer_name, preview: '', created_at: '', timestamp: '', unread_count: 0 };
        inboxData.push(entry);
    }
    entry.name = e.customer_name;
    entry.preview = e.preview;
    entry.created_at = e.created_at;
    entry.timestamp = e.timestamp;
    entry.unread_count = e.unread_count;

    // Already viewing this conversation — no highlight, mark read instead.
    const isOpenConvo = adminChatOpen && activeCustomerId === e.customer_id;
    if (isOpenConvo) entry.unread_count = 0;

    inboxData.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
    renderInboxList();
    updateAdminBadgeAndTitle();

    if (e.from_customer && !isOpenConvo) {
        playAdminChatSound();
        const row = document.querySelector('.cj-inbox-row[data-customer-id="' + e.customer_id + '"]');
        if (row) {
            row.classList.add('cj-inbox-pulse');
            row.addEventListener('animationend', () => row.classList.remove('cj-inbox-pulse'), { once: true });
        }
    }
}

// The Vite-built bundle loads as a type="module" script, which (per spec)
// executes deferred — after the document is parsed — while this inline
// script runs synchronously as the parser reaches it. window.Echo (set up in
// resources/js/bootstrap.js, part of that bundle) isn't guaranteed to exist
// yet at this point, so poll briefly instead of assuming it's ready.
(function subscribeAdminInboxChannel() {
    if (!window.Echo) {
        setTimeout(subscribeAdminInboxChannel, 50);
        return;
    }
    window.Echo.private('App.Models.User.' + ADMIN_ID).listen('AdminInboxUpdated', handleAdminInboxUpdate);
})();

// Fallback: only re-fetches when the socket genuinely isn't connected, so a
// healthy connection does no redundant work — the live push above is what
// normally drives updates.
setInterval(() => {
    const state = window.Echo && window.Echo.connector && window.Echo.connector.pusher
        ? window.Echo.connector.pusher.connection.state
        : 'unavailable';
    if (state !== 'connected') loadAdminInbox();
}, 12000);

loadAdminInbox();
</script>
</body>
</html>
