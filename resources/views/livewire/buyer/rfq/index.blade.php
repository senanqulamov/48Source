<div>
<x-source.rfq-table :rows="$this->rows" detail-route="buyer.rfq.show" status-property="statusFilter" :active-status="$statusFilter ?? 'all'" :buyer="true" />
    <livewire:buyer.rfq.create @created="$refresh" />
    <livewire:buyer.rfq.update @updated="$refresh" />
</div>
