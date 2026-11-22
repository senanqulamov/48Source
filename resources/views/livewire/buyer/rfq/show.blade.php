<div>
<x-source.rfq-detail :request="$request" :buyer="true" />
    @include('livewire.buyer.rfq.approved-invite-modal')
    <livewire:buyer.rfq.update @updated="$refresh" />
    <livewire:procurement.award-decision :request="$request" :key="'award-buyer-'.$request->id" />
</div>
