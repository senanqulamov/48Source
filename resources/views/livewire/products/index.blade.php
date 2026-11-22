<div>
    <x-source.product-directory :rows="$this->rows" role="admin"  :category-options="$this->categories" />
    <livewire:products.create @created="$refresh" />
    <livewire:products.update @updated="$refresh" />
</div>
