<div>
<x-source.rfq-table :rows="$this->rows" detail-route="rfq.show" status-property="status" :active-status="$status" :status-counts="$this->statusCounts" :buyer-options="$this->buyers" :sort-state="$sort" :search-value="$search" :type-filter-value="$typeFilter" :buyer-filter-value="$buyerFilter" />
    <livewire:rfq.update @updated="$refresh" />
</div>
