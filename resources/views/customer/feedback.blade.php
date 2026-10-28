@extends('layouts.customer')

@php
    $editing = (bool) $feedback;
    $isBooking = $order->isBooking();
    $noun = $isBooking ? 'booking' : 'order';
    $labels = \App\Models\Feedback::serviceLabels($isBooking);
    $itemRatings = $feedback ? $feedback->items->keyBy('order_item_id') : collect();
    $lines = $order->orderItems;
@endphp

@section('title', ($editing ? 'Edit your rating' : 'Rate your ' . $noun) . ' — Cojan Catering')

@section('content')
<x-breadcrumbs :items="[
    'My Orders' => route('customer.orders'),
    'Order #' . $order->order_number => route('customer.orders.show', $order->id),
    ($editing ? 'Edit rating' : 'Rate') => null,
]" />
<h1 class="cj-page-title">{{ $editing ? 'Edit your rating' : 'Rate your ' . $noun }}</h1>
<p class="cj-page-sub">
    Order #{{ $order->order_number }} ·
    {{ $editing ? 'You can edit this until' : 'You can rate this until' }} {{ $deadline->format('M j, Y g:i A') }}
</p>

@if($feedback?->is_hidden)
    <div class="alert-cj alert-warning" role="status">
        <i class="bi bi-eye-slash" aria-hidden="true"></i>
        <span>Hidden by Cojan — this review isn't shown to other customers.</span>
    </div>
@endif

@if($errors->any())
    <div class="alert-cj alert-danger" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <div>
            <strong>Please check your rating:</strong>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<form method="POST" enctype="multipart/form-data" class="cj-rate-form"
      action="{{ $editing ? route('customer.feedback.update', $order->id) : route('customer.feedback.store', $order->id) }}">
    @csrf
    @if($editing) @method('PUT') @endif

    {{-- The food / the package --}}
    <div class="cj-card mt-3">
        <div class="cj-card-header">{{ $isBooking ? 'The package' : 'The food' }}</div>
        <div class="cj-card-body">
            @if($lines->count() > 1)
                <x-star-input label="Rate all items the same" shortcut
                              hint="Sets every item below at once — you can still change any of them." />
            @endif

            @foreach($lines as $line)
                @php $saved = $itemRatings->get($line->id); @endphp
                <div class="cj-rate-item">
                    <x-star-input :name="'items[' . $line->id . '][rating]'"
                                  :value="old('items.' . $line->id . '.rating', $saved?->rating)"
                                  :label="$line->displayName() . ($line->quantity > 1 ? ' ×' . $line->quantity : '')"
                                  required listen />
                    <label class="visually-hidden" for="item-comment-{{ $line->id }}">Comment on {{ $line->displayName() }} (optional)</label>
                    <input type="text" id="item-comment-{{ $line->id }}" class="cj-input cj-rate-item-comment"
                           name="items[{{ $line->id }}][comment]" maxlength="300"
                           value="{{ old('items.' . $line->id . '.comment', $saved?->comment) }}"
                           placeholder="Add a short comment (optional)">
                </div>
            @endforeach
        </div>
    </div>

    {{-- The service --}}
    <div class="cj-card mt-3">
        <div class="cj-card-header">The service</div>
        <div class="cj-card-body">
            @if($hasService)
                <x-star-input name="rating_service" required
                              :label="$labels['rating_service'] . ($riderName ? ' (' . \Illuminate\Support\Str::before($riderName, ' ') . ')' : '')"
                              :value="old('rating_service', $feedback?->rating_service)" />
            @endif
            <x-star-input name="rating_food" required :label="$labels['rating_food']"
                          :value="old('rating_food', $feedback?->rating_food)" />
            <x-star-input name="rating" required :label="$labels['rating']"
                          :value="old('rating', $feedback?->rating)" />
        </div>
    </div>

    {{-- Comment + photo --}}
    <div class="cj-card mt-3">
        <div class="cj-card-header">Anything else? <span class="cj-card-header-note">(optional)</span></div>
        <div class="cj-card-body" x-data="{ n: {{ mb_strlen(old('comment', $feedback?->comment ?? '')) }} }">
            <label class="cj-label" for="rate-comment">Your comment</label>
            <textarea id="rate-comment" name="comment" rows="4" maxlength="500" class="cj-textarea"
                      placeholder="Tell us what you liked, or what we could do better."
                      @input="n = $el.value.length" aria-describedby="rate-comment-count">{{ old('comment', $feedback?->comment) }}</textarea>
            <small id="rate-comment-count" class="cj-rate-count"><span x-text="n">0</span>/500</small>

            <div class="cj-rate-photo mt-3" x-data="cjRatingPhoto()">
                <span class="cj-label d-block">Photo</span>
                @if($feedback?->photo_path)
                    <div class="cj-rate-photo-current" x-show="!preview">
                        <img src="{{ route('ratings.photo', $feedback) }}" alt="Your current photo" class="cj-rate-photo-img">
                        <label class="cj-check">
                            <input type="checkbox" name="remove_photo" value="1"> Remove this photo
                        </label>
                    </div>
                @endif
                <img x-show="preview" x-cloak :src="preview" alt="Selected photo" class="cj-rate-photo-img">
                <label class="btn-cj-secondary btn-cj cj-rate-photo-btn" for="rate-photo">
                    <i class="bi bi-camera" aria-hidden="true"></i>
                    <span x-text="preview ? 'Choose a different photo' : '{{ $feedback?->photo_path ? 'Replace photo' : 'Add a photo' }}'">Add a photo</span>
                </label>
                <input type="file" id="rate-photo" name="photo" accept="image/jpeg,image/png,image/webp"
                       class="visually-hidden" x-ref="file" @change="pick($event)">
                <small class="cj-rate-photo-msg" x-text="msg" x-show="msg" x-cloak role="status"></small>
            </div>
        </div>
    </div>

    <div class="cj-sticky-actions mt-3">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <button type="submit" class="btn-cj btn-cj">{{ $editing ? 'Save changes' : 'Submit rating' }}</button>
            <a href="{{ route('customer.orders.show', $order->id) }}" class="cj-back-link">Cancel</a>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
// Photo: resized in the browser to at most 1280px and re-encoded as JPEG
// (like the delivery proof photo), then swapped into the file input so the
// normal form post uploads the small version. If the browser can't do that,
// the original file is sent and the server's size check applies.
function cjRatingPhoto() {
    return {
        preview: null, msg: '',
        async pick(e) {
            const input = e.target, file = input.files[0];
            this.msg = '';
            if (!file) { this.preview = null; return; }
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
                input.value = ''; this.preview = null;
                this.msg = 'Please choose a JPG, PNG or WebP image.';
                return;
            }
            try {
                const blob = await cjCompressImage(file);
                const dt = new DataTransfer();
                dt.items.add(new File([blob], 'rating.jpg', { type: 'image/jpeg' }));
                input.files = dt.files;
                this.preview = URL.createObjectURL(blob);
            } catch (err) {
                this.preview = URL.createObjectURL(file);
            }
        },
    };
}
function cjCompressImage(file) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const MAX = 1280;
            let { width, height } = img;
            if (width > MAX || height > MAX) {
                if (width > height) { height = Math.round(height * MAX / width); width = MAX; }
                else { width = Math.round(width * MAX / height); height = MAX; }
            }
            const canvas = document.createElement('canvas');
            canvas.width = width; canvas.height = height;
            canvas.getContext('2d').drawImage(img, 0, 0, width, height);
            URL.revokeObjectURL(img.src);
            canvas.toBlob((b) => b ? resolve(b) : reject(new Error('compression failed')), 'image/jpeg', 0.75);
        };
        img.onerror = () => reject(new Error('decode failed'));
        img.src = URL.createObjectURL(file);
    });
}
</script>
@endsection
