{{--
    The rating card on a customer's order page: a "Rate your order" prompt
    while rating is open, or their submitted rating (with any reply from
    Cojan, an Edit button while editable, and "Hidden by Cojan" if hidden).
    Nothing before delivery/completion, or once an unrated window closed.
--}}
@props(['order'])

@php
    use App\Services\Ratings;
    $status = Ratings::status($order);
    $feedback = $order->feedback;
    $noun = $order->isBooking() ? 'booking' : 'order';
@endphp

@if($status === Ratings::OPEN)
    <div class="cj-card mb-3 cj-rate-prompt">
        <div class="cj-card-body d-flex align-items-center gap-3 flex-wrap">
            <div style="flex:1 1 14rem;">
                <strong>How was your {{ $noun }}?</strong>
                <p class="mb-0" style="color:var(--text-light);font-size:var(--fs-sm);">
                    Rate the {{ $order->isBooking() ? 'package' : 'food' }} and our service — you can rate until
                    {{ Ratings::rateDeadline($order)->format('M j, Y') }}.
                </p>
            </div>
            <a href="{{ route('customer.feedback.create', $order->id) }}" class="btn-cj-amber btn-cj">
                <i class="bi bi-star" aria-hidden="true"></i> Rate your {{ $noun }}
            </a>
        </div>
    </div>
@elseif($feedback)
    @php $labels = \App\Models\Feedback::serviceLabels($order->isBooking()); @endphp
    <div class="cj-card mb-3">
        <div class="cj-card-header">Your rating</div>
        <div class="cj-card-body">
            @if($feedback->is_hidden)
                <div class="alert-cj alert-warning mb-3" role="status">
                    <i class="bi bi-eye-slash" aria-hidden="true"></i>
                    <span>Hidden by Cojan — this review isn't shown to other customers.</span>
                </div>
            @endif

            @foreach($feedback->items as $item)
                <div class="cj-rating-row">
                    <span>{{ $item->menuItem?->name ?? $item->package?->name ?? 'Item' }}</span>
                    <x-stars :value="$item->rating" />
                </div>
                @if($item->comment)
                    <p class="mb-2" style="font-size:var(--fs-sm);">“{{ $item->comment }}”</p>
                @endif
            @endforeach
            @if($feedback->items->isNotEmpty())<hr class="divider">@endif

            @foreach($labels as $column => $label)
                @if($feedback->{$column})
                    <div class="cj-rating-row">
                        <span>{{ $label }}</span>
                        <x-stars :value="$feedback->{$column}" />
                    </div>
                @endif
            @endforeach

            @if($feedback->comment)
                <p class="mt-2 mb-0">“{{ $feedback->comment }}”</p>
            @endif
            @if($feedback->photo_path)
                <img src="{{ route('ratings.photo', $feedback) }}" alt="Photo you added to your rating"
                     class="cj-rate-photo-img mt-3" loading="lazy">
            @endif

            @if($feedback->admin_reply)
                <div class="cj-rating-reply">
                    <strong style="font-size:var(--fs-sm);">Reply from Cojan</strong>
                    <p class="mb-0 mt-1">{{ $feedback->admin_reply }}</p>
                </div>
            @endif

            @if($status === Ratings::EDITABLE)
                <div class="d-flex align-items-center gap-3 flex-wrap mt-3">
                    <a href="{{ route('customer.feedback.create', $order->id) }}" class="btn-cj-secondary btn-cj">
                        <i class="bi bi-pencil" aria-hidden="true"></i> Edit rating
                    </a>
                    <small style="color:var(--text-light);">You can edit until {{ Ratings::editDeadline($order)->format('M j, g:i A') }}.</small>
                </div>
            @endif
        </div>
    </div>
@endif
