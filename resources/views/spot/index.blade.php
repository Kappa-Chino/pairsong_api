@extends('layout')

@section('content')
    <div class="d-flex align-items-center mb-3">
        <h1 class="mb-0 me-3">スポット管理</h1>
        <a href="{{ route('spot.mapview') }}" class="btn btn-outline-primary">スポットマップビュー</a>
        <a href="{{ route('spot.create') }}" class="btn btn-primary ms-auto">スポットを追加</a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>スポット名</th>
                    <th>緯度</th>
                    <th>経度</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($spots as $spot)
                    <tr>
                        <td>{{ $spot->id }}</td>
                        <td>{{ $spot->name }}</td>
                        <td>{{ $spot->latitude }}</td>
                        <td>{{ $spot->longitude }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('spot.mapview', ['center_id' => $spot->id]) }}" class="btn btn-info btn-sm">地図</a>
                            <a href="{{ route('spot.edit', $spot) }}" class="btn btn-warning btn-sm">編集</a>
                            <form action="{{ route('spot.destroy', $spot) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('このスポットを削除しますか？')">削除</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">登録されているスポットはありません。</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center">{{ $spots->links('pagination::bootstrap-5') }}</div>
@endsection
