<div>
<x-source.rfq-table :rows="$this->rows" detail-route="buyer.rfq.show" status-property="statusFilter" :active-status="$statusFilter ?? 'all'" :buyer="true" :status-counts="$this->statusCounts" :sort-state="$sort" :search-value="$search" :type-filter-value="$typeFilter" />
    <livewire:buyer.rfq.create @created="$refresh" />
    <livewire:buyer.rfq.update @updated="$refresh" />
</div>
