@extends('layout')

@section('content')
    <div class="d-flex align-items-center mb-3">
        <h1 class="mb-0">スポットマップビュー</h1>
        <a href="{{ route('spot.index') }}" class="btn btn-primary ms-auto">スポット管理へ戻る</a>
    </div>

    @if (empty($googleMapsApiKey))
        <div class="alert alert-warning">Google Maps API キーが未設定です。backend/.env の GOOGLE_MAPS_API_KEY に利用可能なキーを設定してください。</div>
    @else
        <div id="map" style="height: 600px; width: 100%;"></div>
        <script>
            const spots = @json($spots, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            const azaleaIconUrl = @json(asset('assets/img/azalea-marker.png'));

            function initMap() {
                const locatedSpots = spots.filter((spot) => {
                    if (spot.latitude === null || spot.latitude === '' || spot.longitude === null || spot.longitude === '') return false;
                    const lat = Number(spot.latitude);
                    const lng = Number(spot.longitude);
                    return Number.isFinite(lat) && Number.isFinite(lng)
                        && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
                });
                const params = new URLSearchParams(window.location.search);
                const centeredSpot = locatedSpots.find((spot) => String(spot.id) === params.get('center_id'));
                const defaultCenter = { lat: 35.681236, lng: 139.767125 };
                const map = new google.maps.Map(document.getElementById('map'), {
                    center: centeredSpot
                        ? { lat: Number(centeredSpot.latitude), lng: Number(centeredSpot.longitude) }
                        : defaultCenter,
                    zoom: centeredSpot ? 15 : 10,
                });
                const infoWindow = new google.maps.InfoWindow();
                const bounds = new google.maps.LatLngBounds();

                locatedSpots.forEach((spot) => {
                    const position = { lat: Number(spot.latitude), lng: Number(spot.longitude) };
                    const marker = new google.maps.Marker({
                        position,
                        map,
                        title: spot.name,
                        icon: {
                            url: azaleaIconUrl,
                            scaledSize: new google.maps.Size(48, 48),
                            anchor: new google.maps.Point(24, 46),
                        },
                    });
                    bounds.extend(position);
                    marker.addListener('click', () => {
                        const content = document.createElement('div');
                        const title = document.createElement('strong');
                        title.textContent = spot.name;
                        const coordinates = document.createElement('p');
                        coordinates.className = 'mb-0';
                        coordinates.textContent = `緯度: ${spot.latitude} / 経度: ${spot.longitude}`;
                        content.append(title, coordinates);
                        infoWindow.setContent(content);
                        infoWindow.open({ map, anchor: marker });
                    });
                    if (centeredSpot && String(spot.id) === String(centeredSpot.id)) {
                        marker.setAnimation(google.maps.Animation.BOUNCE);
                        window.setTimeout(() => marker.setAnimation(null), 1800);
                    }
                });

                if (!centeredSpot && locatedSpots.length > 0) {
                    map.fitBounds(bounds);
                }
            }
        </script>
        <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsApiKey) }}&callback=initMap"></script>
    @endif
@endsection
