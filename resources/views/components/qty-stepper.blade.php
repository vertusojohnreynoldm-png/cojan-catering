{{--
    Quantity stepper: − [typed number] +, posting as "quantity" inside the
    parent form. Behaviour lives in window.cjQty (layouts/customer).
      max          per-item limit (null = no limit, e.g. catering packages)
      autosubmit   submit the parent form after a change (cart page): taps
                   submit at once, typing waits for blur/Enter or a short pause
      removeAtZero − at 1 submits 0, which removes the cart line
    The server re-validates every quantity — this is only a convenience.
--}}
@props([
    'value' => 1,
    'min' => 1,
    'max' => null,
    'autosubmit' => false,
    'removeAtZero' => false,
    'disabled' => false,
    'label' => 'Quantity',
])

<div class="cj-qty"
     x-data="cjQty({{ \Illuminate\Support\Js::from([
         'value' => (int) $value,
         'min' => (int) $min,
         'max' => $max ? (int) $max : null,
         'autosubmit' => (bool) $autosubmit,
         'removeAtZero' => (bool) $removeAtZero,
         'locked' => (bool) $disabled,
     ]) }})">
    <div class="cj-stepper cj-stepper-sm">
        <button type="button" class="cj-stepper-btn" @click="dec()"
                :disabled="locked || (qty <= min && !removeAtZero)"
                :title="removeAtZero && qty <= 1 ? 'Remove item' : 'Decrease quantity'"
                :aria-label="removeAtZero && qty <= 1 ? 'Remove item' : 'Decrease quantity'"
                @disabled(($value <= $min && !$removeAtZero) || $disabled)>−</button>
        <input type="text" name="quantity" value="{{ (int) $value }}" x-ref="input"
               class="cj-stepper-input" inputmode="numeric" pattern="[0-9]*"
               autocomplete="off" maxlength="4" aria-label="{{ $label }}"
               @if($max) aria-describedby="{{ $msgId = 'qty-msg-' . \Illuminate\Support\Str::random(6) }}" @endif
               @input="typed()" @blur="commit()" @keydown.enter.prevent="commit(); $el.blur()"
               @disabled($disabled)>
        <button type="button" class="cj-stepper-btn" @click="inc()"
                :disabled="locked || (max !== null && qty >= max)"
                aria-label="Increase quantity"
                @disabled(($max && $value >= $max) || $disabled)>+</button>
    </div>
    @if($max)
        <small class="cj-qty-msg" id="{{ $msgId }}" role="status" x-show="msg" x-text="msg" x-cloak></small>
    @endif
</div>
