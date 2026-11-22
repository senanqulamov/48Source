<div>
<x-source.rfq-table :rows="$this->rows" detail-route="rfq.show" status-property="status" :active-status="$status" />
    <livewire:rfq.update @updated="$refresh" />
</div>
