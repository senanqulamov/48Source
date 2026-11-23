@props(['viz', 'side' => 'buy'])
@php use App\Support\Money; $t = $viz['total']; @endphp
<div class="grid viz-row">
    <x-card :title="$side === 'buy' ? 'Requests by status' : 'My requests'" :sub="$side === 'buy' ? 'Everything in the pipeline' : 'Where each invitation stands'">{!! $viz['donut'] !!}</x-card>
    <x-card :title="$side === 'buy' ? 'Awarded spend' : 'Awarded value'" sub="Last 6 months">
        <div class="row" style="align-items:baseline;margin-bottom:4px"><span class="strip-v" style="font-size:30px" data-done="1">{{ $t >= 100000000 ? '$' . number_format(intdiv($t, 10000) / 100, 2) . 'M' : '$' . number_format(intdiv($t, 100)) }}</span><span class="muted sm">{{ $viz['orders'] }} purchase order{{ $viz['orders'] === 1 ? '' : 's' }} · hover the chart for each month</span></div>
        {!! $viz['area'] !!}
    </x-card>
</div>
<div class="grid viz-row">
    <x-card :title="$side === 'buy' ? 'From request to invoice' : 'From invitation to invoice'" sub="How many reach each stage, and the share that moves on">{!! $viz['funnel'] !!}</x-card>
    <x-card :title="$side === 'buy' ? 'Top suppliers by awarded value' : 'My buyers'" :sub="$side === 'buy' ? 'Watch for dependency on one supplier' : 'By awarded value'">{!! $viz['rank'] !!}</x-card>
</div>
