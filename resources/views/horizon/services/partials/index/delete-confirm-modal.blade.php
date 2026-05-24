<x-horizon.delete-confirm-modal
    entity="Service"
    title="Delete service"
    resource-label="service"
    :fixed-name="($detailService ?? null)?->name"
    :destroy-url="($detailService ?? null) ? route('horizon.services.destroy', $detailService) : null"
/>
