<div class="stack">
    <header class="ph"><div><h1 class="ph-t">{{ __('Product categories') }}</h1><p class="ph-d">{{ __('Maintain the categories used for product sourcing and RFQs.') }}</p></div>
        <div class="ph-a"><livewire:categories.create @created="$refresh" /></div></header>
    <section class="card"><div class="dt">
        <div class="dt-top">
            <label class="dt-search"><x-source.icon name="search" :size="16" /><span class="sr">{{ __('Search categories') }}</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Category name') }}"></label>
        </div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('Category') }}</th><th class="num">{{ __('Products') }}</th><th>{{ __('Created') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @forelse($this->rows as $category)
                <tr wire:key="category-{{ $category->id }}">
                    <td><a class="t1 link" href="{{ route('categories.show', $category) }}">{{ $category->name }}</a></td>
                    <td class="num mono">{{ $category->products_count }}</td>
                    <td class="mono sm">{{ $category->created_at?->format('d M Y') ?? '—' }}</td>
                    <td><div class="row">
                        @can('edit_categories')
                            <button type="button" class="btn btn-sm" wire:click="$dispatch('load::category', { category: '{{ $category->id }}' })">{{ __('Edit') }}</button>
                        @endcan
                        @can('delete_categories')
                            <livewire:categories.delete :category="$category" :key="'category-delete-'.$category->id" @deleted="$refresh" />
                        @endcan
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty">{{ __('No categories found') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div><div class="dt-foot">{{ $this->rows->links() }}</div>
    </div></section>
    <livewire:categories.update @updated="$refresh" />
</div>