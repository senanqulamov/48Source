<div>
<x-source.quotation-form :request="$quote->request" :items="$items" mode="edit" :currency-code="$currency" :can-choose-currency="true" :cancel-url="route('supplier.quotes.index')" />
</div>
