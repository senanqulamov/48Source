<div>
    <x-source.product-directory :rows="$this->rows" role="seller" :market-options="$this->markets" :category-options="$this->categories" />
    <livewire:products.create @created="$refresh" />
    <livewire:products.update @updated="$refresh" />
</div>
