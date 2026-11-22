<div>
<x-source.rfq-detail :request="$request" :buyer="true" />
    @include('livewire.buyer.rfq.approved-invite-modal')
    <livewire:buyer.rfq.update @updated="$refresh" />
</div>
