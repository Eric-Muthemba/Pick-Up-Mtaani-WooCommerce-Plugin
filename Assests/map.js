jQuery(function ($) {

    if (!$('#pickupmtaani_map').length) return;

    const map = new google.maps.Map(document.getElementById('pickupmtaani_map'), {
        zoom: 11,
        center: {lat: -1.286389, lng: 36.817223}
    });

    fetch('/wp-json/pickupmtaani/v1/agents')
        .then(r => r.json())
        .then(data => {

            data.forEach(a => {

                const marker = new google.maps.Marker({
                    position: {lat: +a.lat, lng: +a.lng},
                    map
                });

                marker.addListener('click', () => {
                    $('#pickupmtaani_agent').val(a.id);
                    $('#pickupmtaani_display').val(a.name);
                });

            });

        });
});