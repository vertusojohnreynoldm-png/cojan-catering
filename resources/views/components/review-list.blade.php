{{--
    Ratings summary + recent visible reviews for one menu item or package
    (RatingStats::reviews / breakdown). Reviewers show as "Juan D.".
--}}
@props(['stats' => null, 'reviews', 'breakdown', 'subject' => 'this dish'])

<section class="cj-card mt-3" aria-labelledby="reviews-heading">
    <div class="cj-card-header" id="reviews-heading">Ratings &amp; reviews</div>
    <div class="cj-card-body">
        @if(!$stats)
            <div class="cj-reviews-empty">
                <div class="cj-reviews-empty-stars" aria-hidden="true">☆☆☆☆☆</div>
                <p class="mb-0"><strong>No ratings yet.</strong> Be the first to rate {{ $subject }} after your order is delivered.</p>
            </div>
        @else
            <div class="cj-reviews-summary">
                <div class="cj-reviews-score">
                    <div class="cj-reviews-avg">{{ number_format($stats['avg'], 1) }}</div>
                    <x-stars :value="$stats['avg']" />
                    <div class="cj-reviews-count">{{ $stats['count'] }} {{ \Illuminate\Support\Str::plural('rating', $stats['count']) }}</div>
                </div>
                <ul class="cj-reviews-bars" aria-label="Ratings by stars">
                    @foreach($breakdown as $stars => $count)
                        @php $pct = $stats['count'] ? round($count / $stats['count'] * 100) : 0; @endphp
                        <li>
                            <span class="cj-reviews-bar-label">{{ $stars }} ★</span>
                            <span class="cj-reviews-bar" aria-hidden="true"><span style="width:{{ $pct }}%"></span></span>
                            <span class="cj-reviews-bar-count">{{ $count }}<span class="visually-hidden"> {{ \Illuminate\Support\Str::plural('rating', $count) }} with {{ $stars }} stars</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>

            @foreach($reviews as $review)
                @php $feedback = $review->feedback; @endphp
                <article class="cj-review">
                    <header class="cj-review-head">
                        <strong>{{ $feedback->reviewerName() }}</strong>
                        <x-stars :value="$review->rating" />
                        <time datetime="{{ $review->created_at->toDateString() }}" class="cj-review-date">
                            {{ $review->created_at->format('M j, Y') }}@if($feedback->edited_at) · edited @endif
                        </time>
                    </header>
                    @if($review->comment)
                        <p class="cj-review-text">“{{ $review->comment }}”</p>
                    @endif
                    @if($feedback->comment)
                        <p class="cj-review-order"><span>About the whole order:</span> “{{ $feedback->comment }}”</p>
                    @endif
                    @if($feedback->photo_path)
                        <a href="{{ route('ratings.photo', $feedback) }}" target="_blank" rel="noopener" class="cj-review-photo-link">
                            <img src="{{ route('ratings.photo', $feedback) }}" alt="Photo from {{ $feedback->reviewerName() }}'s order" loading="lazy" class="cj-review-photo">
                        </a>
                    @endif
                    @if($feedback->admin_reply)
                        <div class="cj-rating-reply">
                            <strong style="font-size:var(--fs-sm);">Reply from Cojan</strong>
                            <p class="mb-0 mt-1">{{ $feedback->admin_reply }}</p>
                        </div>
                    @endif
                </article>
            @endforeach

            @if($reviews->hasPages())
                <nav class="cj-reviews-pager" aria-label="More reviews">
                    @if($reviews->previousPageUrl())
                        <a href="{{ $reviews->previousPageUrl() }}#reviews-heading" class="btn-cj-secondary btn-cj">← Newer</a>
                    @endif
                    <span>Page {{ $reviews->currentPage() }} of {{ $reviews->lastPage() }}</span>
                    @if($reviews->nextPageUrl())
                        <a href="{{ $reviews->nextPageUrl() }}#reviews-heading" class="btn-cj-secondary btn-cj">Older →</a>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</section>
