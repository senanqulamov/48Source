<div>
<x-source.quotation-form :request="$invitation->request" :items="$items" :currency-code="auth()->user()?->currency ?? 'USD'" :cancel-url="route('supplier.quotes.index')" />
</div>
