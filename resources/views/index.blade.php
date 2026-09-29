@extends('layout')

@section('content')
    <div class="container">
        <h1>管理者ダッシュボード</h1>
        <p>各種管理機能へアクセスしてください。</p>

        <div class="list-group">
            <a href="{{ route('spot.index') }}" class="list-group-item list-group-item-action">
                スポット管理
            </a>
            <a href="{{ route('spot.mapview') }}" class="list-group-item list-group-item-action">
                スポットマップビュー
            </a>
            <a href="{{ route('tripRoute.index') }}" class="list-group-item list-group-item-action">
                旅ルート管理
            </a>
            {{-- 将来的に他の管理機能へのリンクを追加 --}}
        </div>
    </div>
@endsection
