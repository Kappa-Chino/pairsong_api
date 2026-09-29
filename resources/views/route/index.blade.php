@extends('layout')

@section('content')
    <div class="row">
        <div class="col-md-12 d-flex align-items-center mb-3">
            <h1 class="mb-0 me-3">旅ルート管理</h1>
        </div>
    </div>

    <table class="table table-striped mt-3">
        <thead>
            <tr>
                <th>ID</th>
                <th>名前</th>
                <th>デバイスID</th>
                <th>作成日時</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($routes as $route)
                <tr>
                    <td>{{ $route->id }}</td>
                    <td>{{ $route->name ?? '名称未設定' }}</td>
                    <td>{{ $route->device_id }}</td>
                    <td>{{ $route->created_at }}</td>
                    <td>
                        <a href="{{ route('tripRoute.show', $route->id) }}" class="btn btn-info btn-sm me-1">詳細・マップ</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ページネーションリンク --}}
    @if ($routes->total() > 0)
        <div class="d-flex justify-content-center mt-4">
            <p class="text-muted">
                Showing {{ $routes->firstItem() }} to {{ $routes->lastItem() }} of {{ $routes->total() }} results
            </p>
        </div>
    @endif
    <div class="d-flex justify-content-center">
        {!! $routes->links('pagination::bootstrap-5') !!}
    </div>
@endsection
