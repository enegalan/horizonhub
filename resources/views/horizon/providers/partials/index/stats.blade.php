@foreach($deliveryStats as $providerType => $count)
    @php
        $tone = '';
        if ($providerType !== 'total') {
            $provider = \App\Models\NotificationProvider::getProviders()[$providerType];
            $tone = $provider::meta()['color'];
        }
    @endphp
    <x-stat-card label="{{ ucfirst($providerType) }}" :value="$count" tone="{{ $tone }}" />
@endforeach
