@extends('layout')

@section('content')
    <div class="row mb-3">
        <div class="col-md-12 d-flex align-items-center">
            <h1 class="mb-0 me-3">ルート詳細: {{ $route->name ?? '名称未設定' }} (ID: {{ $route->id }})</h1>
            <a href="{{ route('tripRoute.index') }}" class="btn btn-secondary ms-auto">一覧に戻る</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">ルート情報</h5>
            <p class="card-text">
                <strong>デバイスID:</strong> {{ $route->device_id }}<br>
                <strong>作成日時:</strong> {{ $route->created_at }}<br>
                
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">マップ</h5>
            @if (empty($googleMapsApiKey))
                <div class="alert alert-warning">Google Maps API キーが未設定です。backend/.env の GOOGLE_MAPS_API_KEY に利用可能なキーを設定してください。</div>
            @else
            <div id="map" style="height: 600px; width: 100%;"></div>
            @endif
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            生成された音楽
        </div>
        <div class="card-body">
            @if ($musicUrl)
                <audio controls preload="metadata" class="w-100">
                    <source src="{{ $musicUrl }}" type="audio/mpeg">
                    お使いのブラウザは音声再生に対応していません。
                </audio>
                <a class="btn btn-outline-primary btn-sm mt-2" href="{{ $musicUrl }}" download="route-{{ $route->id }}.mp3">音楽をダウンロード</a>
            @else
                <p class="text-muted mb-0">このルートの音楽はまだ生成されていません。</p>
            @endif
        </div>
    </div>

    <script>
        // PHPからデータを渡す
        const routePoints = {!! json_encode($route->routePoints->map(fn($p) => ['lat' => (float)$p->latitude, 'lng' => (float)$p->longitude])) !!};
        const visitedSpots = @json($visitedSpots, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        const azaleaIconUrl = @json(asset('assets/img/azalea-marker.png'));

        function initMap() {
            // 中心の決定（ポイントがあればその平均、なければ東京駅）
            let center = { lat: 35.681236, lng: 139.767125 };
            if (routePoints.length > 0) {
                center = routePoints[0];
            }

            const map = new google.maps.Map(document.getElementById('map'), {
                center: center,
                zoom: 14,
            });
            const bounds = new google.maps.LatLngBounds();

            // 経路を描画 (Polyline)
            if (routePoints.length > 0) {
                const flightPath = new google.maps.Polyline({
                    path: routePoints,
                    geodesic: true,
                    strokeColor: '#FF0000',
                    strokeOpacity: 1.0,
                    strokeWeight: 4,
                });
                flightPath.setMap(map);

                routePoints.forEach(p => bounds.extend(p));
            }

            const infoWindow = new google.maps.InfoWindow();
            visitedSpots.forEach((spot) => {
                if (spot.latitude === null || spot.latitude === '' || spot.longitude === null || spot.longitude === '') return;
                const position = { lat: Number(spot.latitude), lng: Number(spot.longitude) };
                if (!Number.isFinite(position.lat) || !Number.isFinite(position.lng)
                    || position.lat < -90 || position.lat > 90
                    || position.lng < -180 || position.lng > 180) return;
                bounds.extend(position);

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
                marker.addListener('click', () => {
                    const title = document.createElement('strong');
                    title.textContent = spot.name;
                    infoWindow.setContent(title);
                    infoWindow.open({ map, anchor: marker });
                });
            });

            if (routePoints.length > 0 || visitedSpots.length > 0) {
                map.fitBounds(bounds);
            }

        }
    </script>
    @if (!empty($googleMapsApiKey))
        <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsApiKey) }}&callback=initMap"></script>
    @endif
@endsection
