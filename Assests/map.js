jQuery(function ($) {
    if (!$('#pickupmtaani_map').length) return;
    if (typeof google === 'undefined' || !google.maps) return;

    const cfg = window.pickupMtaaniMapConfig || {};
    const restUrl = cfg.restUrl || '/wp-json/pickupmtaani/v1/agents';
    const center = cfg.defaultCenter || {lat: -1.286389, lng: 36.817223};

    const mapWrap = $('#pickupmtaani_agent_picker_wrap');
    const agentField = $('#pickupmtaani_agent');
    const agentDisplayField = $('#pickupmtaani_display');
    const deliveryOptionSelector = 'input[name="pickupmtaani_delivery_option"]';

    const map = new google.maps.Map(document.getElementById('pickupmtaani_map'), {
        zoom: 11,
        center
    });

    function updateDeliveryOptionUi() {
        const mode = $(deliveryOptionSelector + ':checked').val() || 'pickup_agent';
        const isPickup = mode === 'pickup_agent';

        mapWrap.toggle(isPickup);

        if (!isPickup) {
            agentField.val('');
            agentDisplayField.val('');
        }
    }

    $(document.body).on('change', deliveryOptionSelector, updateDeliveryOptionUi);
    updateDeliveryOptionUi();

    fetch(restUrl, {credentials: 'same-origin'})
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data)) return;

            data.forEach(a => {
                if (typeof a.lat === 'undefined' || typeof a.lng === 'undefined') return;

                const marker = new google.maps.Marker({
                    position: {lat: +a.lat, lng: +a.lng},
                    map
                });

                marker.addListener('click', () => {
                    agentField.val(a.id);
                    agentDisplayField.val(a.name);
                });

            });

        });
});
